<?php
namespace App\Http\Middleware;

use App\Models\PasswordResetRequest;
use App\Services\CfoMaintenance;
use Closure;
use Illuminate\Http\Request;

class EnsureCfoAccess
{
    public function handle(Request $request, Closure $next, ?string $role = null)
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->status === 'Approved', 403, 'Your account is not approved.');
        abort_if($user->password_change_required, 403, 'Change your temporary password before accessing the system.');
        abort_if($role && $user->role !== $role, 403);
        abort_if(PasswordResetRequest::where('user_id', $user->id)->whereIn('status', ['Approved', 'Verified'])->exists(), 403, 'Complete your office password recovery before accessing the system.');
        CfoMaintenance::runOncePerSessionDay($request, $user);
        return $next($request);
    }
}
