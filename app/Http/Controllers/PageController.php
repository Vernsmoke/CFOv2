<?php
namespace App\Http\Controllers;

use App\Models\{Certificate, Registration, Schedule};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function login(Request $request)
    {
        if ($request->user()) {
            if ($request->user()->password_change_required) return redirect('/force-password');
            return redirect($request->user()->role === 'Admin' ? '/admin' : '/student#dashboard');
        }
        return view('login-create-forgotpassword', ['cfoBackend' => ['user' => null, 'csrf_token' => csrf_token()]]);
    }

    public function forcePassword(Request $request)
    {
        $user = $request->user();
        abort_unless($request->session()->get('force_password_change') === true && $user->password_change_required, 403);

        return response()->view('force-password', [
            'user' => $user,
            'expired' => ! $user->password_change_expires_at || $user->password_change_expires_at->isPast(),
        ])->header('Cache-Control', 'no-store');
    }

    public function page(Request $request)
    {
        return response()->view($request->user()->role === 'Admin' ? 'admin' : 'student.index', ['cfoBackend' => [
            'user' => $request->user(),
            'csrf_token' => csrf_token(),
            ...($request->user()->role === 'Admin' ? ['notice' => $request->session()->get('schedule_notice')] : []),
            'live_transport' => config('cfo.live_transport'),
        ]])->header('Cache-Control', 'no-store');
    }

    public function scheduleEdit(Request $request, int $id)
    {
        $schedule = Schedule::withScheduleLinks()->find($id);
        if (! $schedule) return redirect('/admin#admin-recollection-schedule')->with('schedule_notice', 'The schedule was not found.');
        if ($schedule->finalized_at || $schedule->status === 'Cancelled') {
            return redirect('/admin#admin-recollection-schedule')->with('schedule_notice', $schedule->finalized_at
                ? 'Completed schedules are view only.' : 'Cancelled schedules are view only.');
        }
        $courseIds = $schedule->courses->pluck('id', 'name');
        $assigned = $schedule->registrations()->with('user:id,course,year_level,section')->get()
            ->filter(fn ($registration) => $registration->user && isset($courseIds[$registration->academicGroup()['course']]))
            ->groupBy(function ($registration) use ($courseIds) {
                $group = $registration->academicGroup();
                return json_encode([$courseIds[$group['course']], $group['year_level'], $group['section']]);
            })
            ->map(function ($registrations) use ($courseIds) {
                $group = $registrations->first()->academicGroup();
                return ['course_id' => $courseIds[$group['course']], 'year_level' => $group['year_level'],
                    'section' => $group['section'], 'count' => $registrations->count()];
            })->values();
        $schedule->setAttribute('assigned_academic_groups', $assigned);
        return response()->view('admin', ['cfoBackend' => [
            'user' => $request->user(), 'csrf_token' => csrf_token(), 'edit_schedule' => $schedule,
            'live_transport' => config('cfo.live_transport'),
        ]])->header('Cache-Control', 'no-store');
    }

    public function receipt(Request $request, Registration $registration)
    {
        abort_unless($request->user()->role === 'Admin' || $request->user()->id === $registration->user_id, 403);
        $disk = Storage::disk(config('filesystems.upload_disk'));
        abort_unless($registration->receipt_path && $disk->exists($registration->receipt_path), 404);
        return $disk->response($registration->receipt_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function certificate(Request $request, Certificate $certificate)
    {
        abort_if($certificate->revoked_at !== null, 404);
        abort_unless($request->user()->role === 'Admin' || $request->user()->id === $certificate->registration->user_id, 403);
        abort_unless($certificate->registration->attendance === 'Present' && $certificate->registration->attendance_verified_at, 404);
        $svg = \App\Services\CertificateTemplate::render(\App\Services\AutomaticCertificate::content($certificate->registration), $certificate->certificate_number);
        return response($svg)->header('Content-Type', 'image/svg+xml')->header('Content-Disposition', ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$certificate->certificate_number.'.svg"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function historyRecord(Request $request, Registration $registration)
    {
        abort_unless($request->user()->role === 'Admin' || $request->user()->id === $registration->user_id, 403);
        $registration->refresh();
        abort_unless($registration->is_archived, 404);
        $registration->load(['user', 'schedule.courses', 'certificate', 'certificateRecord', 'changes']);
        if ($request->user()->role !== 'Admin') {
            foreach ($registration->changes as $change) {
                if ($change->status === 'Approved') $change->makeHidden('review_reason');
            }
        }
        $actors = \App\Models\User::whereIn('id', array_filter([
            $registration->receipt_verified_by, $registration->attendance_recorded_by,
            $registration->attendance_verified_by, $registration->certificateRecord?->issued_by,
        ]))->pluck('name', 'id');

        return response()->json(['registration' => $registration, 'actors' => $actors])
            ->header('Cache-Control', 'private, no-store');
    }
}
