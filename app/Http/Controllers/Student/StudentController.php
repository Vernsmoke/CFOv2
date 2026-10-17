<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\{Announcement, Registration, Schedule, ScheduleChange, User};
use App\Services\CfoService as Cfo;
use App\Services\StudentRegistrationOptions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class StudentController extends Controller
{
    public function page(Request $request)
    {
        return response()->view('student.index', ['cfoBackend' => [
            'user' => $request->user(),
            'csrf_token' => csrf_token(),
            'live_transport' => config('cfo.live_transport'),
        ]])->header('Cache-Control', 'no-store');
    }

    public function state(Request $request)
    {
        $resources = array_filter(explode(',', (string) $request->query('resources', '')));
        $all = $resources === [] || in_array('year', $resources);
        $has = fn (string ...$names) => $all || array_intersect($resources, $names) !== [];
        $user = $request->user()->fresh();
        $course = trim((string) $user->course);
        $matchingCourse = fn ($query) => $query->whereRaw('LOWER(TRIM(academic_options.name)) = ?', [mb_strtolower($course)]);
        $state = ['current_year' => now()->year, 'server_date' => today()->toDateString()];
        $state['certificate_revision'] = hash('sha256', json_encode(DB::table('cfo_resource_versions')->whereIn('resource', ['settings', 'users', 'registrations', 'schedules'])->orderBy('resource')->pluck('version', 'resource')));

        if ($has('registrations', 'attendance', 'schedules')) {
            $state['registrations'] = $user->registrations()->with(['schedule.courses', 'certificate', 'certificateRecord'])->orderByDesc('year')->orderByDesc('attempt_no')->get();
            $latest = $state['registrations']->firstWhere('year', now()->year);
            foreach ($state['registrations'] as $row) $row->setAttribute('can_reregister', $row->id === $latest?->id && $row->attendance === 'Absent' && (bool) $row->recollection_schedule_id);
        }
        if ($has('schedules', 'users', 'registrations')) {
            $latest = isset($state['registrations']) ? $state['registrations']->firstWhere('year', now()->year)
                : $user->registrations()->where('year', now()->year)->orderByDesc('attempt_no')->first();
            $group = $latest?->academicGroup();
            $group ??= ['year_level' => $user->year_level, 'section' => $user->section];
            $group['course'] = $course;
            $state['schedules'] = Schedule::open()
                ->whereDate('date', '>=', today())
                ->whereHas('courses', $matchingCourse)
                ->with(['courses' => $matchingCourse])->withCount(['registrations' => fn ($query) => $query->active()])->orderBy('date')->get()
                ->filter(fn ($schedule) => $schedule->year_levels !== [] && $schedule->sections !== []
                    && $schedule->includesYearLevel($group['year_level'])
                    && $schedule->includesSectionForCourse($schedule->courses->first()->name, $group['section'], $group['year_level'])
                    && $schedule->registrations_count < ($schedule->slot_limit ?? $schedule->capacity))->values();
        }
        if ($has('registration_options', 'schedules', 'users', 'registrations', 'academic_options')) {
            $state['registration_course'] = $course;
            $state['registration_courses'] = DB::table('academic_options')->where('type', 'course')->orderBy('name')->pluck('name');
            $state['registration_schedules'] = StudentRegistrationOptions::schedules(null)->map(fn ($schedule) => [
                'courses' => $schedule->courses->map(fn ($course) => ['id' => $course->id, 'name' => $course->name]),
                'year_levels' => $schedule->year_levels,
                'course_sections_by_year' => $schedule->course_sections_by_year,
                'course_sections' => $schedule->course_sections,
                'sections' => $schedule->sections,
            ]);
        }
        if ($has('schedule_changes', 'schedules', 'registrations')) {
            $state['requests'] = ScheduleChange::whereHas('registration', fn ($q) => $q->where('user_id', $user->id)->currentYear())
                ->with(['currentSchedule.courses', 'requestedSchedule.courses', 'assignedSchedule.courses'])->latest()->get()
                ->each(function (ScheduleChange $change) {
                    if ($change->status === 'Approved') $change->makeHidden('review_reason');
                });
        }
        if ($has('notifications', 'registrations')) {
            $notificationQuery = DB::table('cfo_notifications')->where('user_id', $user->id)->whereIn('type', Cfo::STUDENT_NOTIFICATION_TYPES);
            $unviewed = (clone $notificationQuery)->whereNull('read_at')->orderByDesc('created_at')->get();
            $viewed = (clone $notificationQuery)->whereNotNull('read_at')->orderByDesc('created_at')->limit(50)->get();
            $targets = [
                'schedule_change_approved' => '#schedule-change-request',
                'schedule_change_rejected' => '#schedule-change-request',
                'registration_approved' => '#dashboard?focus=registration',
                'certificate_ready' => '#certificate',
                'attendance_present' => '#dashboard?focus=attendance',
                'attendance_absent' => '#dashboard?focus=attendance',
                'announcement' => '#announcements',
                'schedule_cancelled' => '#dashboard?focus=registration',
            ];
            $timestamp = fn ($value) => $value ? Carbon::parse($value, config('app.timezone'))->toIso8601String() : null;
            // Prefer the viewed version if another tab viewed a row between the two reads.
            $notifications = $viewed->concat($unviewed)->unique('id')->sortByDesc('id')->values();
            $years = $user->registrations()->pluck('year', 'id');
            $archived = $user->registrations()->historical()->pluck('id')->flip();
            $requestYears = ScheduleChange::whereIn('registration_id', $years->keys())->pluck('registration_id', 'id');
            $target = function ($notification) use ($targets, $years, $requestYears, $archived) {
                $registrationId = $notification->related_type === 'schedule_change'
                    ? ($requestYears[$notification->related_id] ?? null) : $notification->related_id;
                if (in_array($notification->related_type, ['registration', 'schedule_change'], true)
                    && isset($archived[$registrationId])) return '#recollection-history';
                if ($notification->type === 'announcement') return '#announcements?announcement='.$notification->related_id;
                return $targets[$notification->type];
            };
            $state['notification_unviewed_count'] = $notifications->whereNull('read_at')->count();
            $state['notifications'] = $notifications->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'message' => $notification->message,
                'target' => $target($notification),
                'related_type' => $notification->related_type,
                'related_id' => $notification->related_id,
                'read_at' => $timestamp($notification->read_at),
                'created_at' => $timestamp($notification->created_at),
            ]);
        }
        if ($has('announcements')) {
            $state['announcements'] = Announcement::whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->get(['id', 'title', 'preview', 'body', 'published_at']);
        }
        if ($has('academic_options', 'registration_options')) $state['academic_options'] = DB::table('academic_options')
            ->orderBy('name')->get();
        if ($has('users')) $state['user'] = $user;

        return response()->json($state)->header('Cache-Control', 'private, no-store');
    }

    public function viewNotifications(Request $request)
    {
        $input = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'required|integer|min:1|distinct']);
        $viewed = DB::table('cfo_notifications')
            ->where('user_id', $request->user()->id)
            ->whereIn('type', Cfo::STUDENT_NOTIFICATION_TYPES)
            ->whereIn('id', $input['ids'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($viewed > 0) Cfo::touch('notifications');

        return response()->json(['viewed' => $viewed])->header('Cache-Control', 'private, no-store');
    }

    public function chooseSchedule(Request $request, Registration $registration)
    {
        $input = $request->validate(['schedule_id' => 'required|integer|exists:recollection_schedules,id']);

        DB::transaction(function () use ($request, $registration, $input) {
            $r = Registration::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($registration->id);
            Cfo::require(! $r->recollection_schedule_id, 'A Recollection Day is already selected for this registration.');
            Cfo::assign($r, (int) $input['schedule_id']);
            Cfo::audit('schedule.student_registered', 'registration', $r->id, ['schedule_id' => (int) $input['schedule_id']]);
        });

        return response()->json(['message' => 'You are registered for this Recollection Day.']);
    }

    private function normalizeRegistrationPhone(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value);
        if (preg_match('/^639[0-9]{9}$/D', $digits)) return '0'.substr($digits, 2);
        if (preg_match('/^9[0-9]{9}$/D', $digits)) return '0'.$digits;

        return $digits;
    }

    private function registrationRules(): array
    {
        $rules = ['details' => 'required|array:name,nickname,age,sex,course,yearLevel,section,yearSection,email,phone,emergencyName,relationship,emergencyPhone,medical,allergies,medications,religion,parish,baptism,communion,confirmation,ministry,firstRecollection,previousRecollections,expectations,reflection,participation,photos', 'receipt' => 'nullable|file|mimes:jpg,jpeg,png|max:5120'];
        foreach (['name', 'sex', 'emergencyName', 'relationship'] as $field) $rules['details.'.$field] = 'required|string|max:190';
        foreach (['phone', 'emergencyPhone'] as $field) $rules['details.'.$field] = ['bail', 'required', 'string', 'regex:/^09[0-9]{9}$/D', 'not_regex:/^09([0-9])\1{8}$/D'];
        $rules['details.emergencyPhone'][] = 'different:details.phone';
        $rules['details.course'] = 'required|string|max:190';
        $rules['details.yearSection'] = 'required|string|max:380';
        $rules['details.yearLevel'] = 'required_with:details.section|string|max:190';
        $rules['details.section'] = 'required_with:details.yearLevel|string|max:190';
        $rules['details.religion'] = 'required|string|max:3000';
        foreach (['nickname', 'medical', 'allergies', 'medications', 'parish', 'ministry', 'previousRecollections', 'expectations', 'reflection'] as $field) $rules['details.'.$field] = 'nullable|string|max:3000';
        foreach (['baptism', 'communion', 'confirmation', 'firstRecollection'] as $field) $rules['details.'.$field] = 'nullable|in:Yes,No';
        return [...$rules, 'details.age' => 'required|integer|between:10,100', 'details.email' => 'required|email|max:190', 'details.participation' => 'accepted', 'details.photos' => 'accepted'];
    }

    private function validateRegistration(Request $request, bool $requireReceipt = false): array
    {
        $details = $request->input('details');
        if (is_array($details)) {
            foreach (['phone', 'emergencyPhone'] as $field) {
                if (isset($details[$field]) && is_string($details[$field])) $details[$field] = $this->normalizeRegistrationPhone($details[$field]);
            }
            $request->merge(['details' => $details]);
        }

        $rules = $this->registrationRules();
        if ($requireReceipt) $rules['receipt'] = 'required|file|mimes:jpg,jpeg,png|max:5120';

        $messages = [];
        foreach (['phone', 'emergencyPhone'] as $field) {
            foreach (['required', 'string', 'regex', 'not_regex'] as $rule) $messages['details.'.$field.'.'.$rule] = 'Enter a valid mobile number like 09123456789.';
        }

        $input = $request->validate($rules, [...$messages,
            'details.religion.required' => 'Please select your religion.',
            'details.emergencyPhone.different' => 'Emergency contact number must be different from your own number.',
            'details.course.required' => 'Select your course/program from the available choices.',
            'details.course.string' => 'Select one course/program from the available choices.',
            'details.yearSection.required' => 'Select your year level and section from the available choices.',
            'details.yearSection.string' => 'Select one year level and section from the available choices.',
            'receipt.required' => 'Upload a new receipt for this registration.',
        ]);
        $input['details'] = StudentRegistrationOptions::normalize($input['details']);

        return $input;
    }

    public function register(Request $request)
    {
        $requireReceipt = $request->user()->registrations()->where('year', '<', now()->year)->exists();
        $input = $this->validateRegistration($request, $requireReceipt);
        $path = null;
        try {
            $registration = DB::transaction(function () use ($request, $input, &$path) {
                $input['details'] = StudentRegistrationOptions::lockAndNormalize($input['details']);
                $student = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                Cfo::require(! Registration::where('user_id', $request->user()->id)->where('year', now()->year)->exists(), 'You already registered for this year. Use History to view your record.');
                if ($request->hasFile('receipt')) $path = Storage::disk(config('filesystems.upload_disk'))->putFile('receipts', $request->file('receipt'));
                $registration = Registration::create(['user_id' => $request->user()->id, 'year' => now()->year, 'details' => $input['details'], 'receipt_path' => $path, 'receipt_status' => $path ? 'Pending' : 'Not uploaded']);
                if (! $registration->is_archived && $registration->year === now()->year) {
                    $student->update(['course' => $input['details']['course'], 'year_level' => $input['details']['yearLevel'], 'section' => $input['details']['section']]);
                }
                Cfo::audit('registration.created', 'registration', $registration->id);
                Cfo::notifyAdmins('A yearly registration is awaiting review.');
                return $registration;
            });
        } catch (\Throwable $e) { if ($path) Storage::disk(config('filesystems.upload_disk'))->delete($path); throw $e; }
        return response()->json($registration, 201);
    }

    public function updateRegistration(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        $input = $this->validateRegistration($request);
        $newPath = null;
        $oldPath = null;
        try {
            $saved = DB::transaction(function () use ($request, $input, $registration, &$newPath, &$oldPath) {
                $input['details'] = StudentRegistrationOptions::lockAndNormalize($input['details']);
                $student = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                // The admin review uses this same row lock: approval always wins over a later edit.
                $record = Registration::lockForUpdate()->findOrFail($registration->id);
                Cfo::require(! $record->is_archived && $record->year === now()->year && $record->status === 'Pending', 'Only a Pending registration for the current year can be edited. Approved and historical registrations are locked.');
                if ($request->hasFile('receipt')) {
                    Cfo::require(in_array($record->receipt_status, ['Not uploaded', 'Pending', 'Rejected']), 'This receipt has already been approved. Its verified file cannot be replaced.');
                    $newPath = Storage::disk(config('filesystems.upload_disk'))->putFile('receipts', $request->file('receipt'));
                    if (! $newPath) throw new \RuntimeException('The receipt could not be stored. Please try again.');
                    $oldPath = $record->receipt_path;
                    $record->fill(['receipt_path' => $newPath, 'receipt_status' => 'Pending', 'receipt_reason' => null, 'receipt_verified_by' => null, 'receipt_verified_at' => null]);
                }
                $changed = array_values(array_filter(array_unique([...array_keys($record->details ?? []), ...array_keys($input['details'])]), fn ($key) => ($record->details[$key] ?? null) != ($input['details'][$key] ?? null)));
                $record->details = $input['details'];
                $record->student_snapshot = [...($record->student_snapshot ?? []), 'name' => $input['details']['name'], 'course' => $input['details']['course'], 'year_level' => $input['details']['yearLevel'], 'section' => $input['details']['section'], 'submitted_year_section' => $input['details']['yearSection']];
                if ($record->isDirty()) {
                    $record->save();
                    Cfo::audit('registration.student_updated', 'registration', $record->id, ['fields' => $changed, 'receipt_replaced' => (bool) $newPath]);
                }
                $student->update(['course' => $input['details']['course'], 'year_level' => $input['details']['yearLevel'], 'section' => $input['details']['section']]);
                return $record;
            });
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk(config('filesystems.upload_disk'))->delete($newPath);
            throw $exception;
        }
        if ($oldPath) Storage::disk(config('filesystems.upload_disk'))->delete($oldPath);
        return response()->json(['message' => 'Registration changes saved. Your registration is still awaiting admin approval.', 'registration' => $saved]);
    }

    public function retryRegistration(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        $input = $this->validateRegistration($request);
        $path = null;
        try {
            $new = DB::transaction(function () use ($request, $registration, $input, &$path) {
                $input['details'] = StudentRegistrationOptions::lockAndNormalize($input['details']);
                // Serialize new submissions and retries for this student, including double clicks.
                $student = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $previous = Registration::lockForUpdate()->findOrFail($registration->id);
                $latest = Registration::where('user_id', $previous->user_id)->where('year', now()->year)->orderByDesc('attempt_no')->first();
                Cfo::require($previous->year === now()->year && $latest?->id === $previous->id && $previous->attendance === 'Absent' && (bool) $previous->recollection_schedule_id,
                    'Only your latest current-year registration marked Absent can be submitted again. Refresh your registration status.');
                if ($request->hasFile('receipt')) {
                    $path = Storage::disk(config('filesystems.upload_disk'))->putFile('receipts', $request->file('receipt'));
                    if (! $path) throw new \RuntimeException('The receipt could not be stored. Please try again.');
                } elseif ($previous->receipt_path && Storage::disk(config('filesystems.upload_disk'))->exists($previous->receipt_path)) {
                    // Separate files ensure later replacement cannot remove the historical receipt.
                    $path = 'receipts/'.\Illuminate\Support\Str::uuid().'.'.pathinfo($previous->receipt_path, PATHINFO_EXTENSION);
                    if (! Storage::disk(config('filesystems.upload_disk'))->copy($previous->receipt_path, $path)) throw new \RuntimeException('The receipt could not be retained. Please upload it again.');
                }
                if (! $previous->is_archived) $previous->update(['archived_at' => now(), 'archived_by' => $request->user()->id, 'archive_reason' => 'absent_reregistered']);
                \App\Services\AutomaticCertificate::sync($previous);
                ScheduleChange::where('registration_id', $previous->id)->where('status', 'Pending')->update(['status' => 'Closed', 'reviewed_at' => now()]);
                Cfo::touch('schedule_changes');
                Cfo::touch('schedules');
                Cfo::touch('attendance');
                $new = Registration::create(['user_id' => $previous->user_id, 'year' => now()->year, 'attempt_no' => $previous->attempt_no + 1,
                    'details' => $input['details'], 'receipt_path' => $path, 'receipt_status' => $path ? 'Pending' : 'Not uploaded']);
                if (! $new->is_archived && $new->year === now()->year) {
                    $student->update(['course' => $input['details']['course'], 'year_level' => $input['details']['yearLevel'], 'section' => $input['details']['section']]);
                }
                Cfo::audit('registration.resubmitted_after_absence', 'registration', $new->id, ['previous_registration_id' => $previous->id, 'previous_schedule_id' => $previous->recollection_schedule_id]);
                Cfo::notifyAdmins('A student marked Absent has resubmitted a recollection registration for review and a new schedule.');
                return $new->refresh();
            });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk(config('filesystems.upload_disk'))->delete($path);
            throw $exception;
        }
        return response()->json(['message' => 'Registration resubmitted. The CFO will review it and assign a new schedule.', 'registration' => $new], 201);
    }

    public function receipt(Request $request, Registration $registration)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        $request->validate(['receipt' => 'required|file|mimes:jpg,jpeg,png|max:5120']);
        $new = Storage::disk(config('filesystems.upload_disk'))->putFile('receipts', $request->file('receipt'));
        try {
            $old = DB::transaction(function () use ($registration, $new) {
                $r = Registration::lockForUpdate()->findOrFail($registration->id);
                Cfo::require(! $r->is_archived, 'Historical receipts are retained and cannot be replaced.');
                Cfo::require(in_array($r->receipt_status, ['Not uploaded', 'Rejected']), 'Only missing or rejected receipts can be replaced.');
                $old = $r->receipt_path;
                $r->update(['receipt_path' => $new, 'receipt_status' => 'Pending', 'receipt_reason' => null, 'receipt_verified_by' => null, 'receipt_verified_at' => null]);
                Cfo::audit('receipt.uploaded', 'registration', $r->id);
                Cfo::notifyAdmins('A receipt is awaiting review.');
                return $old;
            });
        } catch (\Throwable $e) { Storage::disk(config('filesystems.upload_disk'))->delete($new); throw $e; }
        if ($old) Storage::disk(config('filesystems.upload_disk'))->delete($old);
        return response()->json(['message' => 'Receipt submitted.']);
    }

    public function change(Request $request)
    {
        // Students only explain why; the CFO assigns the new schedule when approving.
        // requested_schedule_id is still accepted (optional) for older clients.
        $input = $request->validate(['registration_id' => 'required|integer', 'requested_schedule_id' => 'nullable|integer|exists:recollection_schedules,id', 'reason' => 'required|string|min:10|max:1000']);
        $change = DB::transaction(function () use ($request, $input) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $r = Registration::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($input['registration_id']);
            Cfo::require(! $r->is_archived && $r->year === now()->year, 'Only an active current year registration can request a schedule change.');
            Cfo::require($r->recollection_schedule_id && $r->attendance === 'Not Yet Recorded', 'Only an assigned, unrecorded recollection can be changed.');
            Cfo::require(! ScheduleChange::whereHas('registration', fn ($q) => $q->where('user_id', $r->user_id)->currentYear())->where('status', 'Pending')->exists(), 'You already have a pending change request.');
            if (! empty($input['requested_schedule_id'])) {
                $target = Schedule::lockForUpdate()->findOrFail($input['requested_schedule_id']);
                Cfo::require($target->id !== $r->recollection_schedule_id && $target->year === $r->year && $target->isOpen() && $target->date >= today()->toDateString(), 'Choose a different Open, unfinalized schedule in the same year.');
                $targetCourses = $target->courses()->pluck('name');
                $group = $r->academicGroup();
                Cfo::require($targetCourses->isEmpty() || $targetCourses->contains($group['course']), 'Choose a Recollection Day assigned to your course.');
                Cfo::require($target->includesYearLevel($group['year_level']), 'Choose a Recollection Day assigned to your year level.');
                Cfo::require($target->includesSectionForCourse($group['course'], $group['section'], $group['year_level']), 'Choose a Recollection Day assigned to your section.');
                Cfo::require($target->registrations()->active()->count() < ($target->slot_limit ?? $target->capacity), 'This Recollection Day is full.');
            }
            $change = ScheduleChange::create([...$input, 'current_schedule_id' => $r->recollection_schedule_id]);
            Cfo::audit('schedule_change.requested', 'schedule_change', $change->id);
            Cfo::notifyAdmins('A schedule change request is awaiting review.');
            return $change;
        });
        return response()->json($change, 201);
    }

    public function cancelChange(Request $request, ScheduleChange $change)
    {
        $change = DB::transaction(function () use ($request, $change) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            // Match the administrator's registration-then-request lock order.
            Registration::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($change->registration_id);
            $change = ScheduleChange::lockForUpdate()->findOrFail($change->id);
            Cfo::require($change->status === 'Pending', 'Only a pending schedule change request can be cancelled.');
            $change->update(['status' => 'Cancelled']);
            Cfo::audit('schedule_change.cancelled', 'schedule_change', $change->id);
            Cfo::notifyAdmins('A student cancelled a schedule change request.');
            return $change->load(['currentSchedule', 'requestedSchedule', 'assignedSchedule']);
        });
        return response()->json(['message' => 'Your schedule change request was cancelled.', 'request' => $change]);
    }
}
