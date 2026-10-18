<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationVerificationCode;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\AcademicCatalog;
use App\Services\AccountId;
use App\Services\CfoService as Cfo;
use App\Services\RecoveryRequestLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const REGISTRATION_EMAIL_VERIFICATION = 'registration_email_verification';

    public static function passwordRule(): Password
    {
        return Password::min(8)->mixedCase()->numbers()->symbols();
    }

    public function sendRegistrationEmailCode(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $email]);
        $request->validate([
            'email' => ['required', 'email', 'ends_with:@gmail.com', 'max:190', 'unique:users,email'],
        ], ['email.ends_with' => 'Enter a valid Gmail address ending in @gmail.com.']);

        $state = $request->session()->get(self::REGISTRATION_EMAIL_VERIFICATION);
        if (($state['email'] ?? null) === $email && (int) ($state['sent_at'] ?? 0) > now()->subMinute()->timestamp) {
            $retryAfter = max(1, 60 - (now()->timestamp - (int) $state['sent_at']));

            return response()->json(['message' => 'Please wait '.$retryAfter.' seconds before requesting another code.'], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Mail::to($email)->send(new RegistrationVerificationCode($code));
        $request->session()->put(self::REGISTRATION_EMAIL_VERIFICATION, [
            'email' => $email,
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'sent_at' => now()->timestamp,
            'attempts' => 0,
            'verified_at' => null,
        ]);

        return response()->json(['message' => 'A 6-digit verification code was sent to '.$email.'.', 'expires_in' => 600]);
    }

    public function verifyRegistrationEmailCode(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $email]);
        $input = $request->validate([
            'email' => ['required', 'email', 'ends_with:@gmail.com', 'max:190'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'email.ends_with' => 'Enter a valid Gmail address ending in @gmail.com.',
            'code.regex' => 'Enter the 6-digit verification code.',
        ]);
        $state = $request->session()->get(self::REGISTRATION_EMAIL_VERIFICATION);
        if (! $state || ($state['email'] ?? null) !== $email || (int) ($state['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::REGISTRATION_EMAIL_VERIFICATION);

            throw ValidationException::withMessages(['code' => 'This verification code has expired. Request a new code.']);
        }
        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;
        if ($state['attempts'] > 5) {
            $request->session()->forget(self::REGISTRATION_EMAIL_VERIFICATION);

            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Request a new code.']);
        }
        $candidate = hash_hmac('sha256', $input['code'], (string) config('app.key'));
        if (! hash_equals((string) $state['code_hash'], $candidate)) {
            $request->session()->put(self::REGISTRATION_EMAIL_VERIFICATION, $state);

            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }
        $state['verified_at'] = now()->timestamp;
        $request->session()->put(self::REGISTRATION_EMAIL_VERIFICATION, $state);

        return response()->json(['message' => 'Gmail address verified. You can continue creating your account.']);
    }

    public function register(Request $request)
    {
        $normalizedEmail = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $normalizedEmail]);
        $input = $request->validate([
            'student_id' => ['bail', 'required', 'string', 'regex:'.AccountId::PATTERN, 'unique:users'],
            'first_name' => ['required', 'string', 'max:80'], 'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'ends_with:@gmail.com', 'max:190', 'unique:users'],
            'department' => ['required', 'string', 'max:190'],
            'course' => ['required', 'string', 'max:190'],
            'major' => ['nullable', 'string', 'max:190'],
            'year_level' => ['required', Rule::in(config('academic-programs.year_levels', []))],
            'section' => ['required', Rule::in(config('academic-programs.sections', []))],
            'password' => ['required', 'confirmed', self::passwordRule()],
            'role' => ['sometimes', Rule::in([User::ROLE_STUDENT])],
        ], ['student_id.regex' => AccountId::MESSAGE, 'email.ends_with' => 'Enter a valid Gmail address ending in @gmail.com.']);
        $verification = $request->session()->get(self::REGISTRATION_EMAIL_VERIFICATION);
        if (! $verification || ($verification['email'] ?? null) !== $normalizedEmail || empty($verification['verified_at']) || (int) ($verification['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['email' => 'Verify this Gmail address before creating your account.']);
        }
        $input['email'] = $normalizedEmail;
        $input = [...$input, ...AcademicCatalog::normalizeSelection($input)];
        $input['role'] = User::ROLE_STUDENT;
        $input['original_email'] = $normalizedEmail;
        $input['login_email'] = Str::before($normalizedEmail, '@').'@cfo.office.com';
        $input['system_username'] = $input['login_email'];
        Cfo::require(! User::where(function ($query) use ($input) {
            $query->where('email', $input['login_email'])
                ->orWhere('original_email', $input['login_email'])
                ->orWhere('login_email', $input['login_email'])
                ->orWhere('system_username', $input['login_email']);
        })->exists(), 'This system username is already registered. Contact the CFO.');
        $input['name'] = trim(implode(' ', array_filter([$input['first_name'], $input['middle_name'] ?? '', $input['last_name']])));
        DB::transaction(function () use ($input) {
            $user = User::create([...$input, 'status' => 'Pending']);
            Cfo::audit('account.registered', 'user', $user->id);
            Cfo::notifyAdmins('A student account is awaiting office verification.');
        });
        $request->session()->forget(self::REGISTRATION_EMAIL_VERIFICATION);

        return response()->json(['message' => 'Your account is pending verification. Visit the Christian Formation Office for approval. Your login email is '.$input['login_email'].'.', 'login_email' => $input['login_email']], 201);
    }

    public function login(Request $request)
    {
        $input = $request->validate(['email' => 'required|string|max:190', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
        $key = 'login:'.hash('sha256', strtolower(trim($input['email'])).'|'.$request->ip());
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many login attempts. Try again in one minute.');
        RateLimiter::hit($key, 60);
        $email = strtolower(trim($input['email']));
        $user = DB::transaction(function () use ($email, $input) {
            $user = User::where(function ($query) use ($email) {
                $query->where('email', $email)
                    ->orWhere('original_email', $email)
                    ->orWhere('login_email', $email)
                    ->orWhere('system_username', $email);
            })->lockForUpdate()->first();
            Cfo::require($user && Hash::check($input['password'], $user->password), 'The credentials are incorrect.');
            Cfo::require($user->status === 'Approved', $user->status === 'Rejected' ? 'Account rejected: '.$user->rejection_reason : 'Your account is '.$user->status.'. Visit the CFO for verification.');
            if ($user->password_change_required) {
                Cfo::require($user->password_change_expires_at && $user->password_change_expires_at->isFuture(), 'This temporary password has expired. Visit the CFO to request another reset.');
            }
            // Serialize login with approval and password replacement. Only valid credentials
            // may close a request; merely submitting recovery never changes the password.
            PasswordResetRequest::where('user_id', $user->id)
                ->whereIn('status', ['Pending', 'Approved', 'Verified'])->lockForUpdate()->get()
                ->each(function (PasswordResetRequest $reset) {
                    $reset->update(['status' => 'Cancelled', 'temp_password_hash' => null, 'expires_at' => now()]);
                    Cache::forget('recovery-secret:'.$reset->id);
                    Cfo::audit('recovery.cancelled_after_login', 'password_reset', $reset->id);
                });

            return $user;
        });
        Auth::login($user, $request->boolean('remember'));
        $request->session()->forget('recovery_verified');
        $request->session()->regenerate();
        if ($user->password_change_required) {
            RateLimiter::clear($key);
            $request->session()->put('force_password_change', true);

            return response()->json(['user' => $user, 'csrf_token' => csrf_token(), 'redirect' => '/force-password']);
        }
        RateLimiter::clear($key);

        return response()->json(['user' => $user, 'csrf_token' => csrf_token(), 'redirect' => $user->hasOfficeAccess() ? '/admin' : '/student#dashboard']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.', 'csrf_token' => csrf_token()]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $normalizedEmail = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $normalizedEmail]);
        $emailRules = $user->isStudent()
            ? ['required', 'email', 'ends_with:@gmail.com', 'max:190', Rule::unique('users', 'email')->ignore($user->id), Rule::unique('users', 'original_email')->ignore($user->id)]
            : ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)];
        $input = $request->validate([
            'first_name' => 'required|string|max:80', 'middle_name' => 'nullable|string|max:80', 'last_name' => 'required|string|max:80',
            'email' => $emailRules,
            'current_password' => 'required|current_password', 'password' => ['nullable', 'confirmed', self::passwordRule()],
        ]);
        unset($input['current_password']);
        if (empty($input['password'])) {
            unset($input['password']);
        }
        $input['email'] = $normalizedEmail;
        $input['original_email'] = $normalizedEmail;
        Cfo::require(! User::where('id', '!=', $user->id)->where(function ($query) use ($normalizedEmail) {
            $query->where('email', $normalizedEmail)->orWhere('original_email', $normalizedEmail)->orWhere('login_email', $normalizedEmail)->orWhere('system_username', $normalizedEmail);
        })->exists(), 'This email is already used by another account.');
        if ($user->isStudent()) {
            $input['original_email'] = $normalizedEmail;
            $input['login_email'] = Str::before($normalizedEmail, '@').'@cfo.office.com';
            $input['system_username'] = $input['login_email'];
            Cfo::require(! User::where('id', '!=', $user->id)->where(function ($query) use ($input) {
                $query->where('email', $input['login_email'])
                    ->orWhere('original_email', $input['login_email'])
                    ->orWhere('login_email', $input['login_email'])
                    ->orWhere('system_username', $input['login_email']);
            })->exists(), 'This system username is already registered.');
        }
        $input['name'] = trim(implode(' ', array_filter([$input['first_name'], $input['middle_name'] ?? '', $input['last_name']])));
        DB::transaction(function () use ($user, $input) {
            $user->update($input);
            if (isset($input['password'])) {
                $user->setRememberToken(Str::random(60));
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
            }
            Cfo::audit('profile.updated', 'user', $user->id);
        });
        if ($user->isStudent() && isset($input['password'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['requires_login' => true, 'redirect' => '/login?password_changed=1', 'csrf_token' => csrf_token(), 'message' => 'Password changed. Sign in again with your new password.']);
        }

        return response()->json(['user' => $user->fresh(), 'csrf_token' => csrf_token(), 'message' => 'Profile saved.']);
    }

    public function forgot(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email', '')))]);
        $request->validate(['email' => 'required|email|max:190']);
        $email = (string) $request->string('email');
        $limit = RecoveryRequestLimiter::status($email, consume: true);
        $request->session()->put('recovery_email', $email);
        if (! $limit['allowed']) {
            return response()->json([
                'message' => 'You have reached the limit of 5 password reset requests. A 1-hour cooldown starts after the fifth request. Try again in '.(int) ceil($limit['retry_after'] / 60).' minute(s).',
                'recovery_limit' => $limit,
            ], 429)->header('Retry-After', (string) $limit['retry_after']);
        }
        $message = 'If the account is eligible, your request has been recorded. Visit the CFO Office, Miraculous Building, near the chapel, in person for identity verification and admin approval. Keep this page open on this device; your temporary password will appear here automatically after approval.';
        $owned = $this->recovery($request)->whereHas('user', fn ($query) => $query->where(function ($query) use ($email) {
            $query->where('email', $email)
                ->orWhere('original_email', $email)
                ->orWhere('login_email', $email)
                ->orWhere('system_username', $email);
        }))->first();
        if ($owned) {
            if (in_array($owned->fresh()->status, ['Pending', 'Approved', 'Verified'], true)) {
                return response()->json(['message' => $message, 'recovery_limit' => $limit]);
            }
        }
        $secret = Str::random(64);
        $request->session()->put('recovery_secret', $secret);
        $request->session()->forget('recovery_verified');
        DB::transaction(function () use ($email, $secret) {
            $user = User::where(function ($query) use ($email) {
                $query->where('email', $email)
                    ->orWhere('original_email', $email)
                    ->orWhere('login_email', $email)
                    ->orWhere('system_username', $email);
            })->where('status', 'Approved')->lockForUpdate()->first();
            if (! $user) {
                return;
            }
            // Do not let an unauthenticated repeat request cancel an approved recovery.
            if (PasswordResetRequest::where('user_id', $user->id)->whereIn('status', ['Pending', 'Approved', 'Verified'])->exists()) {
                return;
            }
            $reset = PasswordResetRequest::create(['user_id' => $user->id, 'recovery_hash' => hash('sha256', $secret)]);
            Cfo::audit('recovery.requested', 'password_reset', $reset->id);
            Cfo::notifyAdmins('A password recovery request needs in-person verification.');
        });

        return response()->json(['message' => $message, 'recovery_limit' => $limit]);
    }

    private function recovery(Request $request)
    {
        return PasswordResetRequest::where('recovery_hash', hash('sha256', (string) $request->session()->get('recovery_secret', '')));
    }

    public function recoveryStatus(Request $request)
    {
        $reset = $this->recovery($request)->first();
        $data = ['status' => 'Pending'];
        if ($email = $request->session()->get('recovery_email')) {
            $data['recovery_limit'] = RecoveryRequestLimiter::status($email);
        }
        if ($reset) {
            $reset->refresh();
            $data['status'] = $reset->status;
            if ($data['status'] === 'Approved') {
                $data['expires_at'] = null;
                $data['expires_in'] = null;
                $data['login_email'] = $reset->user->login_email ?: ($reset->user->system_username ?: $reset->user->email);
                $encrypted = Cache::pull('recovery-secret:'.$reset->id);
                if ($encrypted) {
                    $data['temporary_password'] = Crypt::decryptString($encrypted);
                }
            }
        }

        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    public function verifyRecovery(Request $request)
    {
        $request->validate(['temporary_password' => 'required|string|max:100']);
        $reset = $this->recovery($request)->first();
        $key = 'recovery-verify:'.hash('sha256', (string) $request->session()->get('recovery_secret')).'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many attempts. Please wait.');
        RateLimiter::hit($key, 60);
        $valid = DB::transaction(function () use ($reset, $request) {
            if (! $reset) {
                return false;
            }
            User::whereKey($reset->user_id)->lockForUpdate()->firstOrFail();
            $locked = PasswordResetRequest::lockForUpdate()->find($reset->id);
            if ($locked->status !== 'Approved' || ! Hash::check($request->string('temporary_password'), $locked->temp_password_hash)) {
                Cfo::audit('recovery.verification_failed', 'password_reset', $locked->id);

                return false;
            }
            $locked->update(['status' => 'Verified', 'verified_at' => now(), 'temp_password_hash' => null]);
            $request->session()->put('recovery_verified', $locked->id);
            Cache::forget('recovery-secret:'.$locked->id);
            Cfo::audit('recovery.verified', 'password_reset', $locked->id);

            return true;
        });
        Cfo::require($valid, 'The temporary password is invalid or already used.');

        return response()->json(['message' => 'Create your new permanent password.']);
    }

    public function resetPassword(Request $request)
    {
        $input = $request->validate(['password' => ['required', 'confirmed', self::passwordRule()]]);
        DB::transaction(function () use ($request, $input) {
            $ownerId = $this->recovery($request)->value('user_id');
            $user = $ownerId ? User::lockForUpdate()->find($ownerId) : null;
            $reset = $this->recovery($request)->lockForUpdate()->first();
            Cfo::require($reset && $reset->id === $request->session()->get('recovery_verified') && $reset->status === 'Verified' && $reset->verified_at, 'Verify a valid temporary password first.');
            Cfo::require($user && $user->status === 'Approved', 'This account is not approved. Contact the CFO.');
            $user->password = $input['password'];
            $user->password_change_required = false;
            $user->password_change_expires_at = null;
            $user->setRememberToken(Str::random(60));
            $user->save();
            $reset->update(['status' => 'Completed', 'used_at' => now(), 'temp_password_hash' => null]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Cfo::audit('recovery.completed', 'password_reset', $reset->id);
        });
        $request->session()->forget(['recovery_secret', 'recovery_verified']);
        $request->session()->regenerate();

        return response()->json(['message' => 'Password changed. Sign in with your new password.', 'csrf_token' => csrf_token()]);
    }

    public function forcePassword(Request $request)
    {
        $input = $request->validate(['password' => ['required', 'confirmed', self::passwordRule()]]);
        $user = $request->user();

        DB::transaction(function () use ($request, $input, $user) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            Cfo::require($request->session()->get('force_password_change') === true && $locked->password_change_required, 'A one-time password change is not pending.');
            Cfo::require($locked->password_change_expires_at && $locked->password_change_expires_at->isFuture(), 'This temporary password has expired. Visit the CFO to request another reset.');
            $locked->password = $input['password'];
            $locked->password_change_required = false;
            $locked->password_change_expires_at = null;
            $locked->setRememberToken(Str::random(60));
            $locked->save();
            DB::table('sessions')->where('user_id', $locked->id)->where('id', '!=', $request->session()->getId())->delete();
            Cfo::audit('account.temporary_password_changed', 'user', $locked->id);
        });

        $request->session()->forget('force_password_change');
        if ($user->isStudent()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['requires_login' => true, 'redirect' => '/login?password_changed=1', 'csrf_token' => csrf_token(), 'message' => 'Password changed. Sign in again with your new password.']);
        }
        $request->session()->regenerate();

        return response()->json(['message' => 'Your new password is saved.', 'redirect' => $user->hasOfficeAccess() ? '/admin' : '/student#dashboard', 'csrf_token' => csrf_token()]);
    }
}
