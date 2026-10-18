<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRecollectionScheduleRequest;
use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\PasswordResetRequest;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\ScheduleChange;
use App\Models\User;
use App\Services\AccountId;
use App\Services\AutomaticCertificate;
use App\Services\CertificateTemplate;
use App\Services\CfoService as Cfo;
use App\Services\StudentIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function state(Request $request)
    {
        $resources = array_filter(explode(',', (string) $request->query('resources', '')));
        if (! $request->user()->isSuperAdmin() && array_intersect($resources, ['settings', 'activity_logs'])) {
            abort(403);
        }
        $all = $resources === [] || in_array('year', $resources);
        $has = fn (string ...$names) => $all || array_intersect($resources, $names) !== [];
        $users = User::query()->orderBy('name');
        if (! $request->user()->isSuperAdmin()) {
            $users->where('role', User::ROLE_STUDENT);
        }

        return response()->json(array_filter([
            'current_year' => now()->year,
            'server_date' => today()->toDateString(),
            'archive_timezone' => config('app.timezone'),
            'users' => $has('users') ? $users->get() : null,
            'academic_population' => $has('users', 'schedules', 'academic_options', 'registrations')
                ? User::where('role', User::ROLE_STUDENT)->select('department', 'course', 'major', 'year_level', 'section')->distinct()->get() : null,
            'registrations' => $has('registrations', 'attendance', 'users', 'schedules') ? Registration::currentYear()->with(['user', 'schedule.courses', 'certificate'])->latest()->get() : null,
            'history' => $request->boolean('include_history') ? Registration::historical()->with(['user', 'schedule.courses', 'certificate', 'certificateRecord'])->orderByDesc('year')->latest()->get() : null,
            'schedules' => $has('schedules', 'registrations', 'schedule_changes') ? Schedule::withScheduleLinks()->orderBy('date')->get()->each(fn ($schedule) => $schedule->setAttribute('has_linked_records', $schedule->has_linked_records)) : null,
            'requests' => $has('schedule_changes', 'users', 'schedules', 'registrations') ? $this->changeRequests() : null,
            'resets' => $has('password_resets', 'users') ? PasswordResetRequest::with(['user', 'reviewer:id,name'])->latest()->get() : null,
            'academic_options' => $has('academic_options') ? DB::table('academic_options')->orderBy('type')->orderBy('name')->get() : null,
            'course_year_sections' => $has('academic_options', 'course_year_sections') ? DB::table('course_year_sections')->orderBy('course_id')->orderBy('year_level')->orderBy('section')->get() : null,
            'settings' => $request->user()->isSuperAdmin() && $has('settings') ? Cfo::settings('office', ['name' => 'Christian Formation Office', 'school' => 'Christian Formation Office']) : null,
            'template' => $request->user()->isSuperAdmin() && $has('settings') ? CertificateTemplate::current() : null,
            'notifications' => $has('notifications') ? DB::table('cfo_notifications')->where('user_id', $request->user()->id)->latest()->limit(50)->get() : null,
            'announcements' => $has('announcements') ? Announcement::with('author')->orderByDesc('published_at')->get() : null,
            'logs' => $request->user()->isSuperAdmin() && $has('activity_logs') ? DB::table('activity_logs')->leftJoin('users', 'users.id', '=', 'activity_logs.actor_id')->select('activity_logs.*', 'users.name as actor')->orderByDesc('activity_logs.id')->limit(200)->get() : null,
        ], fn ($value) => $value !== null))->header('Cache-Control', 'private, no-store');
    }

    // Current-year change requests. Pending ones carry the schedules the admin may assign.
    private function changeRequests()
    {
        return ScheduleChange::whereHas('registration', fn ($q) => $q->currentYear())
            ->with(['registration.user', 'registration.schedule.courses', 'currentSchedule', 'requestedSchedule.courses', 'assignedSchedule.courses'])
            ->latest()->get()
            ->each(function (ScheduleChange $change) {
                $eligible = $change->status === 'Pending' && $change->registration
                    ? Cfo::eligibleChangeSchedules($change->registration)->map(fn (Schedule $schedule) => [
                        'id' => $schedule->id, 'title' => $schedule->title, 'date' => $schedule->date, 'time' => $schedule->time,
                        'venue' => $schedule->venue, 'slots_left' => max(0, ($schedule->slot_limit ?? $schedule->capacity) - $schedule->registrations_count),
                    ])->values() : [];
                $change->setAttribute('eligible_schedules', $eligible);
            });
    }

    public function user(Request $request, User $user)
    {
        abort_unless($user->role === 'Student', 403);
        if ($request->exists('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email', '')))]);
        }
        $input = $request->validate([
            'student_id' => ['sometimes', 'bail', 'required', 'string', 'max:50', ...($request->input('student_id') !== $user->student_id ? ['regex:'.AccountId::PATTERN] : []), Rule::unique('users', 'student_id')->ignore($user->id)],
            'first_name' => 'required|string|max:80', 'middle_name' => 'nullable|string|max:80', 'last_name' => 'required|string|max:80',
            'email' => ['sometimes', 'required', 'email', 'ends_with:@gmail.com', 'max:190', Rule::unique('users', 'email')->ignore($user->id), Rule::unique('users', 'original_email')->ignore($user->id)],
            'course' => ['nullable', 'string', 'max:190', Rule::exists('academic_options', 'name')->where('type', 'course')],
            'year_level' => ['nullable', 'string', 'max:190', Rule::exists('academic_options', 'name')->where('type', 'year_level')],
            'section' => ['nullable', 'string', 'max:190', Rule::exists('academic_options', 'name')->where('type', 'section')],
            'status' => 'required|in:Pending,Approved,Rejected,Inactive', 'rejection_reason' => 'nullable|string|max:2000',
        ], ['student_id.regex' => AccountId::MESSAGE]);
        if (array_key_exists('email', $input)) {
            $input['email'] = strtolower(trim($input['email']));
            $input['original_email'] = $input['email'];
            $input['login_email'] = Str::before($input['email'], '@').'@cfo.office.com';
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
            if ($user->status !== 'Approved') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
            Cfo::audit('account.reviewed', 'user', $user->id, ['status' => $user->status]);
        });

        return response()->json(['message' => 'Student account saved.']);
    }

    public function createStaff(Request $request)
    {
        if ($request->exists('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email', '')))]);
        }
        $input = $request->validate([
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('users', 'student_id')],
            'first_name' => 'required|string|max:80',
            'middle_name' => 'nullable|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
        ]);
        Cfo::require(! User::where(function ($query) use ($input) {
            $query->where('original_email', $input['email'])
                ->orWhere('login_email', $input['email'])
                ->orWhere('system_username', $input['email']);
        })->exists(), 'This email is already used by another account.');
        StudentIdentity::assertCanRegisterAdmin([
            'student_id' => $input['employee_id'], 'email' => $input['email'],
            'first_name' => $input['first_name'], 'middle_name' => $input['middle_name'] ?? null,
            'last_name' => $input['last_name'],
        ]);
        $temporary = Str::random(12).'Aa1!';
        $staff = DB::transaction(function () use ($input, $temporary) {
            $staff = User::create([
                'name' => trim(implode(' ', array_filter([$input['first_name'], $input['middle_name'] ?? '', $input['last_name']]))),
                'first_name' => $input['first_name'], 'middle_name' => $input['middle_name'] ?? null, 'last_name' => $input['last_name'],
                'student_id' => $input['employee_id'], 'email' => $input['email'], 'original_email' => $input['email'],
                'system_username' => $input['email'], 'login_email' => $input['email'],
                'role' => User::ROLE_CFO_STAFF, 'status' => 'Approved', 'password' => $temporary,
                'password_change_required' => true, 'password_change_expires_at' => now()->addDay(),
            ]);
            Cfo::audit('staff.created', 'user', $staff->id, ['role' => User::ROLE_CFO_STAFF]);

            return $staff;
        });

        return response()->json([
            'message' => 'CFO Staff account created. Give the temporary password to the staff member in person; it is shown only once.',
            'user' => $staff, 'temporary_password' => $temporary,
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function updateStaff(Request $request, User $user)
    {
        abort_unless($user->isCfoStaff(), 404);
        $input = $request->validate(['status' => 'required|in:Approved,Inactive']);
        DB::transaction(function () use ($user, $input) {
            $staff = User::lockForUpdate()->findOrFail($user->id);
            $staff->update(['status' => $input['status']]);
            if ($staff->status !== 'Approved') {
                DB::table('sessions')->where('user_id', $staff->id)->delete();
                $staff->setRememberToken(Str::random(60));
                $staff->save();
            }
            Cfo::audit('staff.status_changed', 'user', $staff->id, ['status' => $staff->status]);
        });

        return response()->json(['message' => 'CFO Staff account updated.']);
    }

    public function resetStudentPassword(Request $request, User $user)
    {
        abort_unless($user->role === 'Student', 404);
        $request->validate(['identity_verified' => 'required|accepted']);
        $temporary = Str::random(12).random_int(0, 9);
        $expiresAt = now()->addDay();

        DB::transaction(function () use ($user, $temporary, $expiresAt) {
            $student = User::lockForUpdate()->findOrFail($user->id);
            Cfo::require($student->role === 'Student' && $student->status === 'Approved', 'Only approved student accounts can be reset.');

            // Retain compatibility for previously issued admin temporary passwords,
            // but never let this legacy endpoint replace a session-bound recovery.
            Cfo::require(! PasswordResetRequest::where('user_id', $student->id)
                ->whereIn('status', ['Pending', 'Approved', 'Verified'])->exists(),
                'Review this student\'s request under Password reset requests.');

            $student->password = $temporary;
            $student->password_change_required = true;
            $student->password_change_expires_at = $expiresAt;
            $student->setRememberToken(Str::random(60));
            $student->save();
            DB::table('sessions')->where('user_id', $student->id)->delete();
            Cfo::audit('account.admin_password_reset', 'user', $student->id);
        });

        return response()->json([
            'message' => 'Password reset. Give this temporary password to the student in person; it is shown only once.',
            'temporary_password' => $temporary,
            'expires_at' => $expiresAt->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function schedule(SaveRecollectionScheduleRequest $request, ?Schedule $schedule = null)
    {
        $hasYearBlocks = $request->exists('course_sections_by_year');
        $sections = $request->academicSections();
        $input = $request->validated();
        Cfo::require(! $schedule?->exists || $hasYearBlocks || ! isset($input['status']) || $input['status'] !== 'Closed' || ($schedule->status === 'Closed' && ! $schedule->finalized_at), 'This schedule status cannot be changed in that way.');
        $input['status'] = $input['status'] ?? ($input['active'] ? 'Open' : 'Closed');
        $input['active'] = $input['status'] === 'Open';
        $courseIds = $input['course_ids'];
        $courseMajors = collect($input['course_majors'] ?? ($schedule?->course_majors ?? []))
            ->mapWithKeys(fn ($major, $courseId) => [(string) $courseId => trim((string) $major)])
            ->filter(fn ($major, $courseId) => $major !== '' && in_array((int) $courseId, array_map('intval', $courseIds), true))
            ->all();
        if ($request->exists('course_majors')) {
            $input['course_majors'] = $courseMajors;
        }
        $courseSections = $input['course_sections'] ?? null;
        $yearBlocks = $input['course_sections_by_year'] ?? null;
        unset($input['course_ids'], $input['course_sections'], $input['course_sections_by_year'], $input['course_groups'], $input['save_action']);
        $input['year_levels'] = $input['year_levels'] ?? [$input['year_level']];
        $input['sections'] = $input['sections'] ?? (isset($input['section']) ? [$input['section']] : []);
        $input['year_level'] = $input['year_levels'][0];
        $saved = DB::transaction(function () use ($input, $schedule, $courseIds, $courseMajors, $courseSections, $yearBlocks, $sections) {
            // Venue conflicts can involve disjoint courses. Lock the existing
            // course rows in a consistent order to serialize all schedule saves.
            DB::table('academic_options')->where('type', 'course')->orderBy('id')->lockForUpdate()->get();
            if ($schedule?->exists) {
                $schedule = Schedule::with('courses')->lockForUpdate()->findOrFail($schedule->id);
            }
            if ($schedule?->exists) {
                Cfo::require(! $schedule->finalized_at && $schedule->status !== 'Cancelled', 'Completed and Cancelled schedules are view only.');
            }
            if ($yearBlocks !== null) {
                $allowedStatuses = ['Draft' => ['Draft', 'Open'], 'Open' => ['Open', 'Closed'], 'Closed' => ['Closed', 'Open']];
                if ($schedule?->exists) {
                    Cfo::require(in_array($input['status'], $allowedStatuses[$schedule->status] ?? [], true), 'This schedule status cannot be changed in that way.');
                }
                $mapping = $this->validatedYearBlockSections($yearBlocks, $courseIds, $input['year_levels']);
                if (! $schedule?->exists) {
                    $this->validateAddedAcademicGroups($this->academicGroups($courseIds, $input['year_levels'], [], $mapping), $sections);
                }
            } elseif ($schedule?->exists && (array) $schedule->course_sections_by_year !== []) {
                // Older clients can update metadata without silently flattening a saved block scope.
                Cfo::require($this->normalizedCourseIds($courseIds) === $this->normalizedCourseIds($schedule->courses->pluck('id')->all())
                    && $this->normalizedCourseIds($input['year_levels']) === $this->normalizedCourseIds($schedule->year_levels)
                    && ($courseSections === null || $this->normalizedCourseSections($courseSections) === $this->normalizedCourseSections((array) $schedule->course_sections)),
                    'Use the Edit Schedule page to change groups without losing their year-level sections.');
                $mapping = $this->scheduleCourseSections($schedule);
            } else {
                $mapping = $courseSections === null
                    ? array_intersect_key((array) ($schedule?->course_sections ?? []), array_flip($courseIds))
                    : $this->validatedCourseSections($courseSections, $courseIds, $input['year_levels'], $sections, $schedule);
            }
            if ($courseSections !== null || $yearBlocks !== null || ($schedule?->exists && (array) $schedule->course_sections_by_year !== [])) {
                $input['sections'] = $this->courseSectionUnion($mapping);
            } elseif ($mapping !== []) {
                $input['sections'] = array_values(array_unique([...$input['sections'], ...$this->courseSectionUnion($mapping)]));
            }
            $input['section'] = $input['sections'][0];
            $previousGroups = $schedule?->exists ? $this->scheduleAcademicGroups($schedule) : [];
            $nextGroups = $schedule?->exists ? $this->academicGroups($courseIds, $input['year_levels'], $input['sections'], $mapping) : [];
            if ($yearBlocks !== null && $previousGroups !== []
                && array_diff_key($previousGroups, $nextGroups) === [] && array_diff_key($nextGroups, $previousGroups) === []) {
                // Opening an old schedule and saving metadata keeps its original scope representation.
                $mapping = $this->scheduleCourseSections($schedule);
                $input['sections'] = $schedule->sections;
                $input['section'] = $schedule->section;
            }
            $addedGroups = $schedule?->exists ? array_values(array_diff_key($nextGroups, $previousGroups)) : [];
            if ($schedule?->exists) {
                $this->validateAddedAcademicGroups($addedGroups, $sections);
            }
            $currentMajors = $schedule?->exists
                ? collect($schedule->course_majors ?? [])->mapWithKeys(fn ($major, $id) => [(string) $id => trim((string) $major)])->filter()->sortKeys()->all()
                : [];
            $nextMajors = collect($courseMajors)->sortKeys()->all();
            $pureAddition = $schedule?->exists && $previousGroups !== [] && $addedGroups !== []
                && $schedule->courses->every(fn ($course) => $schedule->sectionsForCourse($course->id) !== [])
                && $currentMajors === $nextMajors
                && array_diff_key($previousGroups, $nextGroups) === [];
            $sameWindow = $schedule?->exists && $input['date'] === $schedule->date
                && $input['start_time'] === $schedule->start_time && $input['end_time'] === $schedule->end_time;
            if ($addedGroups !== []) {
                $this->assertNoAddedScheduleOverlap($input, $addedGroups, $schedule->id);
            }
            if (! ($pureAddition && $sameWindow)) {
                $this->assertNoScheduleOverlap([...$input, 'course_sections' => $mapping], $courseIds, $schedule?->id);
            }

            $input['title'] = $this->generatedScheduleTitle($courseIds, $input['year_levels'], $input['sections'], $mapping, $input['date'], $courseMajors);
            if ($schedule?->exists) {
                Cfo::require(! $schedule->finalized_at && $schedule->status !== 'Cancelled', 'Completed and Cancelled schedules are view only.');
                $limit = $input['slot_limit'] ?? 100000;
                Cfo::require($limit >= $schedule->registrations()->count(), 'The slot limit cannot be below the assigned student count.');
                if ($schedule->registrations()->exists()) {
                    Cfo::require((int) substr($input['date'], 0, 4) === $schedule->year, 'An assigned schedule cannot move to a different year.');
                }
                $currentCourses = $schedule->courses()->pluck('academic_options.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $nextCourses = collect($courseIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
                $currentYears = collect($schedule->year_levels)->sort()->values()->all();
                $nextYears = collect($input['year_levels'])->sort()->values()->all();
                $currentSections = collect($schedule->sections)->sort()->values()->all();
                $nextSections = collect($input['sections'])->sort()->values()->all();
                if (! $pureAddition && ($currentCourses !== $nextCourses || $currentYears !== $nextYears || $currentSections !== $nextSections || $currentMajors !== $nextMajors
                    || $this->normalizedCourseSections($this->scheduleCourseSections($schedule)) !== $this->normalizedCourseSections($mapping))) {
                    $names = DB::table('academic_options')->whereIn('id', $courseIds)->pluck('name');
                    $courseNames = DB::table('academic_options')->whereIn('id', $courseIds)->pluck('id', 'name');
                    $excluded = $schedule->registrations()->with('user')->get()->contains(function ($registration) use ($names, $courseNames, $input, $mapping, $courseMajors) {
                        if (! $registration->user) {
                            return false;
                        }
                        $group = $registration->academicGroup();
                        $courseId = $courseNames[$group['course']] ?? null;

                        $requiredMajor = trim((string) ($courseMajors[(string) $courseId] ?? ''));

                        return ! $names->contains($group['course']) || ($requiredMajor !== '' && strcasecmp($requiredMajor, trim((string) $registration->user->major)) !== 0)
                            || ! in_array($group['year_level'], $input['year_levels'], true)
                            || ! in_array($group['section'], $this->mappedSections($mapping, $courseId, $group['year_level'], $input['sections']), true);
                    });
                    Cfo::require(! $excluded, 'These course, year level or section changes would exclude assigned students. Keep their eligibility or reassign them first.');
                }
                if ($schedule->registrations()->where('attendance', '!=', 'Not Yet Recorded')->exists()) {
                    Cfo::require($input['date'] === $schedule->date && $input['start_time'] === $schedule->start_time && $input['end_time'] === $schedule->end_time && $input['venue'] === $schedule->venue, 'Recorded events retain their date, time, and venue.');
                }
                if (! $sameWindow || $input['venue'] !== $schedule->venue) {
                    $this->assertNoVenueOverlap($input, $schedule->id);
                }
                $schedule->update([...$input, 'capacity' => $input['slot_limit'] ?? 100000]);
            } else {
                $this->assertNoVenueOverlap($input);
                $schedule = Schedule::create([...$input, 'capacity' => $input['slot_limit'] ?? 100000]);
            }
            $schedule->courses()->sync(collect($courseIds)->mapWithKeys(fn ($id) => [$id => ['sections' => $mapping[$id] ?? null]])->all());
            // Register only choices used by a successful save so the same
            // values remain available to existing edit/profile validators.
            $addedReferences = 0;
            foreach (['year_level' => 'year_levels', 'section' => 'sections'] as $type => $key) {
                foreach ($input[$key] as $name) {
                    $addedReferences += DB::table('academic_options')->insertOrIgnore([
                        'type' => $type, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            if ($addedReferences) {
                Cfo::touch('academic_options');
            }
            Cfo::touch('schedules');
            $audit = ['status' => $schedule->status];
            if ($addedGroups !== []) {
                $audit['added_groups'] = array_column($addedGroups, 'label');
                $audit['added_groups_summary'] = 'Added groups: '.implode(', ', $audit['added_groups']);
            }
            Cfo::audit('schedule.saved', 'schedule', $schedule->id, $audit);

            return $this->loadedSchedule($schedule);
        }, 3);

        return response()->json($saved);
    }

    private function assertNoVenueOverlap(array $input, ?int $excludeId = null): void
    {
        $venue = mb_strtolower(trim($input['venue']));
        $conflict = Schedule::query()->where('status', '!=', 'Cancelled')->where('date', $input['date'])
            ->where('start_time', '<', $input['end_time'])->where('end_time', '>', $input['start_time'])
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('id')->get()->first(fn ($other) => mb_strtolower(trim($other->venue)) === $venue);

        if ($conflict) {
            throw ValidationException::withMessages(['venue' => 'Venue "'.trim($input['venue']).'" is already reserved by "'.$conflict->title.'" on '.$conflict->date.' from '.$conflict->start_time.' to '.$conflict->end_time.' at an overlapping time.']);
        }
    }

    private function assertNoScheduleOverlap(array $input, array $courseIds, ?int $excludeId = null): void
    {
        $conflict = Schedule::query()
            ->where('status', '!=', 'Cancelled')
            ->where('date', $input['date'])
            ->where('start_time', '<', $input['end_time'])
            ->where('end_time', '>', $input['start_time'])
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->whereHas('courses', fn ($query) => $query->whereIn('academic_options.id', $courseIds))
            ->with('courses')->get()->contains(function ($other) use ($input, $courseIds) {
                if ($other->year_levels !== [] && array_intersect($other->year_levels, $input['year_levels']) === []) {
                    return false;
                }
                $years = $other->year_levels === [] ? $input['year_levels'] : array_intersect($other->year_levels, $input['year_levels']);
                if ($years === []) {
                    $years = [null];
                }
                foreach ($other->courses as $course) {
                    if (! in_array((int) $course->id, array_map('intval', $courseIds), true)) {
                        continue;
                    }
                    $nextMajor = trim((string) (($input['course_majors'] ?? [])[(string) $course->id] ?? ''));
                    $otherMajor = trim((string) $other->majorForCourse($course->id));
                    if ($nextMajor !== '' && $otherMajor !== '' && strcasecmp($nextMajor, $otherMajor) !== 0) {
                        continue;
                    }
                    foreach ($years as $year) {
                        $previous = $other->sectionsForCourse($course->id, $year);
                        $next = $this->mappedSections($input['course_sections'] ?? [], $course->id, $year, $input['sections']);
                        if ($previous === [] && $other->hasYearSpecificSectionsForCourse($course->id)) {
                            continue;
                        }
                        if ($next === [] && $this->isYearBlockSections($input['course_sections'][$course->id] ?? [])) {
                            continue;
                        }
                        if ($previous === [] || array_intersect($previous, $next) !== []) {
                            return true;
                        }
                    }
                }

                return false;
            });
        Cfo::require(! $conflict, 'This course, year level and section already has a Recollection Day at an overlapping time.');
    }

    private function validatedCourseSections(array $mapping, array $courseIds, array $years, array $globalSections, ?Schedule $schedule = null): array
    {
        if (! $schedule?->exists) {
            Cfo::require(count($years) === 1, 'Choose exactly one year level for per-course sections.');
        }
        Cfo::require($this->normalizedCourseIds(array_keys($mapping)) === $this->normalizedCourseIds($courseIds), 'Choose sections for every selected course, and only those courses.');
        foreach ($courseIds as $id) {
            $selected = $mapping[$id] ?? [];
            Cfo::require($selected !== [] && count(array_unique($selected)) === count($selected), 'Choose at least one distinct section for every selected course.');
            if (! $schedule?->exists) {
                $configured = DB::table('course_year_sections')->where('course_id', $id)->where('year_level', $years[0])->pluck('section')->all();
                $allowed = $configured ?: $globalSections;
                foreach ($selected as $section) {
                    Cfo::require(in_array($section, $allowed, true), 'A selected section does not exist for its course and year level.');
                }
            }
            $mapping[$id] = array_values($selected);
        }

        return $mapping;
    }

    private function validatedYearBlockSections(array $mapping, array $courseIds, array $years): array
    {
        Cfo::require($this->normalizedCourseIds(array_keys($mapping)) === $this->normalizedCourseIds($courseIds), 'Choose sections for every selected course, and only those courses.');
        $usedYears = [];
        foreach ($mapping as $courseId => &$blocks) {
            foreach ($blocks as $year => &$selected) {
                Cfo::require(in_array((string) $year, $years, true), 'Choose exactly one selected year level for each block.');
                Cfo::require($selected !== [] && count(array_unique($selected)) === count($selected), 'Choose at least one distinct section for every selected course in each year-level block.');
                $selected = array_values($selected);
                $usedYears[] = (string) $year;
            }
            unset($selected);
        }
        unset($blocks);
        Cfo::require($this->normalizedCourseIds(array_unique($usedYears)) === $this->normalizedCourseIds($years), 'Each selected year-level block needs at least one course with sections.');

        return $mapping;
    }

    private function scheduleCourseSections(Schedule $schedule): array
    {
        $mapping = [];
        foreach ($schedule->courses as $course) {
            if ($course->pivot->sections !== null) {
                $mapping[$course->id] = $course->pivot->sections;
            }
        }

        return $mapping;
    }

    private function isYearBlockSections(array $sections): bool
    {
        return $sections !== [] && is_array(reset($sections));
    }

    private function mappedSections(array $mapping, int|string|null $courseId, ?string $year, array $fallback): array
    {
        $sections = $mapping[$courseId] ?? $fallback;

        return $this->isYearBlockSections($sections) ? ($sections[$year] ?? []) : $sections;
    }

    private function courseSectionUnion(array $mapping): array
    {
        $sections = [];
        foreach ($mapping as $selected) {
            $sections = [...$sections, ...($this->isYearBlockSections($selected) ? array_merge(...array_values($selected)) : $selected)];
        }

        return array_values(array_unique($sections));
    }

    private function academicGroups(array $courseIds, array $years, array $sections, array $mapping): array
    {
        $names = DB::table('academic_options')->whereIn('id', $courseIds)->pluck('name', 'id');
        $groups = [];
        foreach ($courseIds as $courseId) {
            foreach ($years as $year) {
                foreach ($this->mappedSections($mapping, $courseId, $year, $sections) as $section) {
                    $key = json_encode([(int) $courseId, $year, $section]);
                    $groups[$key] = ['course_id' => (int) $courseId, 'year_level' => $year, 'section' => $section, 'label' => $names[$courseId].' '.$year.$section];
                }
            }
        }

        return $groups;
    }

    private function scheduleAcademicGroups(Schedule $schedule): array
    {
        return $this->academicGroups($schedule->courses->pluck('id')->all(), $schedule->year_levels, $schedule->sections, $this->scheduleCourseSections($schedule));
    }

    private function validateAddedAcademicGroups(array $groups, array $globalSections): void
    {
        $configured = [];
        foreach ($groups as $group) {
            $key = json_encode([$group['course_id'], $group['year_level']]);
            if (! array_key_exists($key, $configured)) {
                $configured[$key] = DB::table('course_year_sections')->where('course_id', $group['course_id'])->where('year_level', $group['year_level'])->pluck('section')->all();
            }
            $allowed = $configured[$key] ?: $globalSections;
            Cfo::require(in_array($group['section'], $allowed, true), $group['label'].' is not a configured section for its course and year level.');
        }
    }

    private function assertNoAddedScheduleOverlap(array $input, array $groups, int $excludeId): void
    {
        $otherSchedules = Schedule::query()->where('status', '!=', 'Cancelled')->where('date', $input['date'])
            ->where('start_time', '<', $input['end_time'])->where('end_time', '>', $input['start_time'])
            ->where('id', '!=', $excludeId)
            ->whereHas('courses', fn ($query) => $query->whereIn('academic_options.id', array_column($groups, 'course_id')))
            ->with('courses')->get();
        foreach ($groups as $group) {
            foreach ($otherSchedules as $other) {
                if (! $other->courses->contains('id', $group['course_id'])) {
                    continue;
                }
                if ($other->year_levels !== [] && ! in_array($group['year_level'], $other->year_levels, true)) {
                    continue;
                }
                $sections = $other->sectionsForCourse($group['course_id'], $group['year_level']);
                if ($sections === [] && $other->hasYearSpecificSectionsForCourse($group['course_id'])) {
                    continue;
                }
                Cfo::require($sections !== [] && ! in_array($group['section'], $sections, true),
                    'This course, year level and section ('.$group['label'].') already has a Recollection Day at an overlapping time: '.$other->title.'.');
            }
        }
    }

    private function generatedScheduleTitle(array $courseIds, array $years, array $sections, array $mapping, string $date, array $courseMajors = []): string
    {
        $yearLabels = array_map(fn ($year) => in_array($year, ['1', '2', '3', '4'], true)
            ? $year.['1' => 'st', '2' => 'nd', '3' => 'rd', '4' => 'th'][$year].' Year' : $year, $years);
        $suffix = ' – '.$date;
        $prefix = $this->shortenGeneratedTitle('Recollection – '.implode(', ', $yearLabels), 190 - $this->titleLength($suffix) - 4).' – ';
        $names = DB::table('academic_options')->whereIn('id', $courseIds)->orderBy('name')->get(['id', 'name']);
        $description = $names->map(function ($course) use ($mapping, $sections, $years, $courseMajors) {
            $selected = $mapping[$course->id] ?? $sections;
            $courseName = $course->name.(! empty($courseMajors[(string) $course->id]) ? ' ('.$courseMajors[(string) $course->id].')' : '');
            if (! $this->isYearBlockSections($selected)) {
                return $courseName.' '.implode(', ', $selected);
            }
            $groups = [];
            foreach ($years as $year) {
                foreach ($selected[$year] ?? [] as $section) {
                    $groups[] = $year.$section;
                }
            }

            return $courseName.' '.implode(', ', $groups);
        })->implode(' / ');

        return $prefix.$this->shortenGeneratedTitle($description, 190 - $this->titleLength($prefix) - $this->titleLength($suffix)).$suffix;
    }

    private function titleLength(string $value): int
    {
        // Match the browser generator's UTF-16 length without splitting a character.
        return (int) (strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) / 2);
    }

    private function shortenGeneratedTitle(string $value, int $limit): string
    {
        if ($this->titleLength($value) <= $limit) {
            return $value;
        }
        $shortened = '';
        $length = 0;
        foreach (mb_str_split($value, 1, 'UTF-8') as $character) {
            $size = $this->titleLength($character);
            if ($length + $size > max(0, $limit - 1)) {
                break;
            }
            $shortened .= $character;
            $length += $size;
        }

        return preg_replace('/[\p{Z}\x{0009}-\x{000D}\x{FEFF}]+$/u', '', $shortened).'…';
    }

    private function normalizedCourseIds(array $ids): array
    {
        $ids = array_map('strval', $ids);
        sort($ids, SORT_STRING);

        return $ids;
    }

    private function normalizedCourseSections(array $mapping): array
    {
        foreach ($mapping as &$sections) {
            if ($this->isYearBlockSections($sections)) {
                foreach ($sections as &$selected) {
                    sort($selected, SORT_STRING);
                }
                unset($selected);
                ksort($sections, SORT_STRING);
            } else {
                sort($sections, SORT_STRING);
            }
        }
        unset($sections);
        ksort($mapping, SORT_STRING);

        return $mapping;
    }

    private function loadedSchedule(Schedule $schedule): Schedule
    {
        $loaded = Schedule::withScheduleLinks()->findOrFail($schedule->id);

        return $loaded->setAttribute('has_linked_records', $loaded->has_linked_records);
    }

    public function scheduleStatus(Request $request, Schedule $schedule)
    {
        $input = $request->validate(['status' => 'required|in:Draft,Open,Closed,Cancelled']);
        $saved = DB::transaction(function () use ($schedule, $input) {
            $courseIds = $schedule->courses()->pluck('academic_options.id')->all();
            DB::table('academic_options')->whereIn('id', $courseIds)->orderBy('id')->lockForUpdate()->get();
            $schedule = Schedule::lockForUpdate()->findOrFail($schedule->id);
            $lockedCourses = collect($courseIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
            $currentCourses = $schedule->courses()->pluck('academic_options.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            Cfo::require($lockedCourses === $currentCourses, 'This schedule changed while saving. Refresh and retry.');
            Cfo::require(! $schedule->finalized_at, 'Completed schedules are view only.');
            $previous = $schedule->status;
            if ($previous === $input['status']) {
                return $this->loadedSchedule($schedule);
            }
            $allowed = ['Open' => ['Closed', 'Cancelled'], 'Draft' => ['Open', 'Cancelled'], 'Closed' => ['Open', 'Cancelled'], 'Cancelled' => []];
            Cfo::require(in_array($input['status'], $allowed[$previous] ?? [], true), 'This schedule status cannot be changed in that way.');
            if ($input['status'] === 'Open') {
                $this->assertNoScheduleOverlap([
                    'date' => $schedule->date, 'start_time' => $schedule->start_time, 'end_time' => $schedule->end_time,
                    'year_levels' => $schedule->year_levels, 'sections' => $schedule->sections,
                    'course_sections' => $this->scheduleCourseSections($schedule),
                ], $courseIds, $schedule->id);
            }
            $schedule->update(['status' => $input['status']]);
            if ($input['status'] === 'Cancelled') {
                // Keep assignments and all registration/history data intact.
                // CFO can explicitly reassign through the existing review flow.
                foreach ($schedule->registrations()->orderBy('id')->get()->unique('user_id') as $registration) {
                    Cfo::notifyStudent($registration->user_id, 'schedule_cancelled',
                        'The recollection schedule "'.$schedule->title.'" on '.$schedule->date.' has been cancelled. Your registration is kept. Please contact the CFO office for reassignment to an available schedule.',
                        'registration', $registration->id);
                }
            }
            Cfo::audit('schedule.status_changed', 'schedule', $schedule->id, ['from' => $previous, 'to' => $schedule->status]);

            return $this->loadedSchedule($schedule);
        }, 3);

        return response()->json($saved);
    }

    public function deleteSchedule(Schedule $schedule)
    {
        DB::transaction(function () use ($schedule) {
            $schedule = Schedule::lockForUpdate()->findOrFail($schedule->id);
            Cfo::require(! $schedule->finalized_at && $schedule->status !== 'Cancelled', 'Completed and Cancelled schedules are view only.');
            Cfo::require(! $schedule->has_linked_records, 'This schedule has linked records. Cancel the schedule instead.');
            Cfo::audit('schedule.deleted', 'schedule', $schedule->id);
            $schedule->delete();
        });

        return response()->json(['message' => 'Unused schedule deleted.']);
    }

    public function registration(Request $request, Registration $registration)
    {
        $input = $request->validate([
            'status' => 'sometimes|in:Pending,Approved,Rejected', 'rejection_reason' => 'required_if:status,Rejected|nullable|string|max:2000',
            'receipt_status' => 'sometimes|in:Pending,Approved,Rejected', 'receipt_reason' => 'required_if:receipt_status,Rejected|nullable|string|max:2000',
            'schedule_id' => 'sometimes|required|integer|exists:recollection_schedules,id',
            'attendance' => 'sometimes|in:Not Yet Recorded,Present,Absent,Late', 'confirm_edit' => 'sometimes|boolean', 'verify_attendance' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($input, $registration, $request) {
            $r = Registration::lockForUpdate()->findOrFail($registration->id);
            $previousStatus = $r->status;
            $previousAttendance = $r->attendance;
            if (array_intersect(['status', 'receipt_status', 'schedule_id'], array_keys($input))) {
                Cfo::require(! $r->is_archived, 'This registration is already in History.');
            }
            if (isset($input['status'])) {
                Cfo::require(! $r->recollection_schedule_id || $input['status'] === 'Approved', 'Assigned registrations must remain approved.');
                $r->status = $input['status'];
                $r->rejection_reason = $input['rejection_reason'] ?? null;
            }
            if (isset($input['receipt_status'])) {
                Cfo::require((bool) $r->receipt_path, 'Upload a receipt before reviewing it.');
                Cfo::require(! $r->recollection_schedule_id || $input['receipt_status'] === 'Approved', 'An assigned registration must retain a verified receipt.');
                $r->receipt_status = $input['receipt_status'];
                $r->receipt_reason = $input['receipt_reason'] ?? null;
                $r->receipt_verified_by = $request->user()->id;
                $r->receipt_verified_at = now();
            }
            if (isset($input['attendance'])) {
                Cfo::require((bool) $r->recollection_schedule_id, 'Assign a schedule before recording attendance.');
                Cfo::require($r->attendance === 'Not Yet Recorded' || ($input['confirm_edit'] ?? false), 'Confirm changes to previously recorded attendance.');
                if ($r->attendance !== $input['attendance'] && ($certificate = $r->certificate)) {
                    $certificate->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'revocation_reason' => 'Attendance corrected; new verification and issuance required.']);
                    Cfo::audit('certificate.revoked', 'certificate', $certificate->id, ['reason' => 'attendance.corrected', 'snapshot' => $certificate->snapshot]);
                }
                $r->attendance = $input['attendance'];
                $r->attendance_recorded_by = $request->user()->id;
                $r->attendance_recorded_at = now();
                $r->attendance_verified_by = null;
                $r->attendance_verified_at = null;
            }
            if ($input['verify_attendance'] ?? false) {
                Cfo::require($r->attendance !== 'Not Yet Recorded', 'Record attendance before verification.');
                $r->attendance_verified_by = $request->user()->id;
                $r->attendance_verified_at = now();
            }
            $r->save();
            if ($previousStatus !== 'Approved' && $r->status === 'Approved') {
                Cfo::notifyStudent($r->user_id, 'registration_approved', 'Your Recollection registration for '.$r->year.' has been approved.', 'registration', $r->id);
            }
            if ($previousAttendance !== $r->attendance && in_array($r->attendance, ['Present', 'Absent'], true)) {
                $type = $r->attendance === 'Present' ? 'attendance_present' : 'attendance_absent';
                Cfo::notifyStudent($r->user_id, $type, 'Your attendance was marked '.$r->attendance.' for the '.$r->year.' Recollection.'.($r->attendance === 'Absent' && $r->year === now()->year ? ' Your registration form is open again. Resubmit it for CFO review and a new schedule.' : ''), 'registration', $r->id);
            }
            if (isset($input['schedule_id']) && (int) $input['schedule_id'] !== (int) $r->recollection_schedule_id) {
                Cfo::assign($r, $input['schedule_id'], adminAssignment: true);
                $closed = ScheduleChange::where('registration_id', $r->id)->where('status', 'Pending')->update(['status' => 'Closed', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
                if ($closed) {
                    Cfo::touch('schedule_changes');
                }
            }
            Cfo::audit('registration.reviewed', 'registration', $r->id, array_intersect_key($input, array_flip(['status', 'receipt_status', 'attendance', 'verify_attendance'])));
            AutomaticCertificate::sync($r);
        });

        return response()->json(['message' => 'Record saved.']);
    }

    public function change(Request $request, ScheduleChange $change)
    {
        $input = $request->validate([
            'status' => 'required|in:Approved,Rejected',
            // The administrator chooses the new schedule. Requests created before this
            // flow existed may still carry the schedule the student asked for.
            'assigned_schedule_id' => 'nullable|integer|exists:recollection_schedules,id',
            // Required when rejecting (the student sees it); an internal note when approving.
            'review_reason' => 'required_if:status,Rejected|nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($change, $input, $request) {
            $r = Registration::lockForUpdate()->findOrFail($change->registration_id);
            $change = ScheduleChange::lockForUpdate()->findOrFail($change->id);
            Cfo::require($change->status === 'Pending', 'This request was already reviewed.');
            Cfo::require($r->recollection_schedule_id === $change->current_schedule_id, 'The current schedule changed. Close this request and submit a new one.');
            $assignedId = null;
            if ($input['status'] === 'Approved') {
                $assignedId = (int) ($input['assigned_schedule_id'] ?? $change->requested_schedule_id ?? 0);
                Cfo::require($assignedId > 0, 'Choose the new recollection schedule to assign.');
                Cfo::require($assignedId !== (int) $change->current_schedule_id, 'Choose a schedule different from the current one.');
                Cfo::assign($r, $assignedId, adminAssignment: true);
            }
            $reviewReason = trim((string) ($input['review_reason'] ?? ''));
            Cfo::require($input['status'] !== 'Rejected' || $reviewReason !== '', 'Enter a reason before rejecting this request.');
            $reviewReason = $reviewReason === '' ? null : $reviewReason;
            $change->update(['status' => $input['status'], 'assigned_schedule_id' => $assignedId, 'review_reason' => $reviewReason, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            Cfo::audit('schedule_change.reviewed', 'schedule_change', $change->id, ['status' => $input['status'], 'assigned_schedule_id' => $assignedId, 'review_reason' => $reviewReason]);
            if ($input['status'] === 'Approved') {
                Cfo::notifyStudent($r->user_id, 'schedule_change_approved', $change->load('assignedSchedule')->student_message, 'schedule_change', $change->id);
            } else {
                Cfo::notifyStudent($r->user_id, 'schedule_change_rejected', $change->student_message, 'schedule_change', $change->id);
            }
        });

        return response()->json(['message' => 'Decision saved.']);
    }

    public function certificate(Request $request, Registration $registration)
    {
        $certificate = DB::transaction(function () use ($registration, $request) {
            $r = Registration::with(['user', 'schedule'])->lockForUpdate()->findOrFail($registration->id);
            Cfo::require($r->attendance === 'Present' && (bool) $r->attendance_verified_at, 'A certificate requires Present and verified attendance.');
            if ($existing = $r->certificate) {
                return $existing;
            }
            $template = Cfo::settings('certificate');
            Cfo::require(! empty($template['title']) && ! empty($template['signatory']), 'Save the official certificate template and signatory first.');
            // Current-year issuance continues to honor office profile corrections.
            // A historical issue uses the identity retained for that registration.
            $identity = $r->year < now()->year ? ($r->student_snapshot ?? []) : [];
            $snapshot = [...CertificateTemplate::defaults(), ...$template, 'year' => $r->year, 'name' => $identity['name'] ?? $r->user->name, 'student_id' => $identity['student_id'] ?? $r->user->student_id, 'date' => $r->schedule->date, 'venue' => $r->schedule->venue, 'event' => $r->schedule->title];
            $c = Certificate::updateOrCreate(['registration_id' => $r->id], ['certificate_number' => sprintf('CFO-%d-%06d', $r->year, $r->id), 'snapshot' => $snapshot, 'issued_by' => $request->user()->id, 'revoked_at' => null, 'revoked_by' => null, 'revocation_reason' => null]);
            Cfo::audit('certificate.issued', 'certificate', $c->id);
            Cfo::notifyStudent($r->user_id, 'certificate_ready', 'Your '.$r->year.' Recollection certificate is ready.', 'registration', $r->id);

            return $c;
        });

        return response()->json($certificate);
    }

    public function announcement(Request $request)
    {
        $input = $request->validate([
            'publication_key' => 'required|uuid',
            'title' => 'required|string|max:190',
            'preview' => 'required|string|max:280',
            'body' => 'required|string|max:10000',
        ]);
        $announcement = DB::transaction(function () use ($input, $request) {
            // Serialize retries from this publisher, retaining the same form key on retry.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($existing = Announcement::where('publication_key', $input['publication_key'])->first()) {
                Cfo::require($existing->created_by === $request->user()->id, 'This publication key is already in use.');
                Cfo::require($existing->published_at !== null, 'This announcement is still a draft. Publish it from the drafts list.');
                Cfo::require($existing->title === $input['title'] && $existing->preview === $input['preview'] && $existing->body === $input['body'], 'This announcement was already published. Open a new form to publish a different announcement.');

                return $existing->load('author');
            }
            $announcement = Announcement::create([
                ...$input,
                'created_by' => $request->user()->id,
                'published_at' => now(),
            ]);
            Cfo::audit('announcement.published', 'announcement', $announcement->id, ['title' => $announcement->title]);

            $this->notifyAnnouncement($announcement);

            return $announcement->load('author');
        });

        return response()->json(['message' => 'Announcement published to students.', 'announcement' => $announcement], 201);
    }

    private function notifyAnnouncement(Announcement $announcement): void
    {
        $message = $announcement->title.' — '.$announcement->preview;
        User::where('role', 'Student')->where('status', 'Approved')->orderBy('id')->pluck('id')
            ->each(fn ($userId) => Cfo::notifyStudent((int) $userId, 'announcement', $message, 'announcement', $announcement->id));
    }

    public function saveAnnouncementDraft(Request $request, ?Announcement $announcement = null)
    {
        $input = $request->validate([
            'publication_key' => 'sometimes|required|uuid',
            'draft_revision' => 'sometimes|required|string|size:64',
            'title' => 'required|string|max:190',
            'preview' => 'required|string|max:280',
            'body' => 'required|string|max:10000',
        ]);
        $revision = $input['draft_revision'] ?? null;
        $publicationKey = $input['publication_key'] ?? (string) Str::uuid();
        unset($input['draft_revision'], $input['publication_key']);
        $draft = DB::transaction(function () use ($input, $request, $announcement, $revision, $publicationKey) {
            if ($announcement) {
                $announcement = Announcement::whereKey($announcement->id)->lockForUpdate()->firstOrFail();
                Cfo::require($announcement->published_at === null, 'Published announcements cannot be edited. Only drafts can be changed.');
                $this->requireAnnouncementRevision($announcement, $revision);
                $announcement->update($input);
            } else {
                // A stable composer key makes retries safe after a lost response.
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if ($existing = Announcement::where('publication_key', $publicationKey)->lockForUpdate()->first()) {
                    Cfo::require($existing->created_by === $request->user()->id, 'This publication key is already in use.');
                    Cfo::require($existing->published_at === null, 'This announcement was already published.');
                    Cfo::require($existing->title === $input['title'] && $existing->preview === $input['preview'] && $existing->body === $input['body'], 'This draft was already saved with different content. Reload announcements before saving again.');

                    return $existing->load('author');
                }
                $announcement = Announcement::create([
                    ...$input,
                    'publication_key' => $publicationKey,
                    'created_by' => $request->user()->id,
                    'published_at' => null,
                ]);
            }
            Cfo::audit('announcement.draft_saved', 'announcement', $announcement->id, ['title' => $announcement->title]);

            return $announcement->load('author');
        });

        return response()->json(['message' => 'Draft saved.', 'announcement' => $draft], $draft->wasRecentlyCreated ? 201 : 200);
    }

    private function requireAnnouncementRevision(Announcement $announcement, ?string $revision): void
    {
        Cfo::require($revision === null || hash_equals($announcement->draft_revision, $revision), 'This draft has changed. Reload announcements before saving or publishing it.');
    }

    public function publishAnnouncementDraft(Request $request, Announcement $announcement)
    {
        $input = $request->validate([
            'draft_revision' => 'sometimes|required|string|size:64',
            'title' => 'required|string|max:190',
            'preview' => 'required|string|max:280',
            'body' => 'required|string|max:10000',
        ]);
        $revision = $input['draft_revision'] ?? null;
        unset($input['draft_revision']);
        $published = DB::transaction(function () use ($input, $announcement, $revision) {
            $announcement = Announcement::whereKey($announcement->id)->lockForUpdate()->firstOrFail();
            Cfo::require($announcement->published_at === null, 'This announcement was already published.');
            $this->requireAnnouncementRevision($announcement, $revision);
            $announcement->update([...$input, 'published_at' => now()]);
            Cfo::audit('announcement.published', 'announcement', $announcement->id, ['title' => $announcement->title]);
            $this->notifyAnnouncement($announcement);

            return $announcement->load('author');
        });

        return response()->json(['message' => 'Announcement published to students.', 'announcement' => $published]);
    }

    public function deleteAnnouncement(Announcement $announcement)
    {
        DB::transaction(function () use ($announcement) {
            $announcement = Announcement::whereKey($announcement->id)->lockForUpdate()->firstOrFail();
            if ($announcement->published_at !== null) {
                DB::table('cfo_notifications')->where('related_type', 'announcement')->where('related_id', $announcement->id)->delete();
                Cfo::touch('notifications');
            }
            Cfo::audit('announcement.deleted', 'announcement', $announcement->id, [
                'title' => $announcement->title,
                'published' => $announcement->published_at !== null,
            ]);
            $announcement->delete();
        });

        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function settings(Request $request, string $key)
    {
        abort_unless(in_array($key, ['office', 'certificate']), 404);
        $rules = $key === 'office'
            ? ['name' => 'required|string|max:190', 'school' => 'required|string|max:190', 'address' => 'nullable|string|max:500', 'head' => 'nullable|string|max:190']
            : CertificateTemplate::rules();
        $input = $request->validate($rules);
        if ($key === 'certificate') {
            $input = CertificateTemplate::normalize($input);
        }
        unset($input['image'], $input['remove_image'], $input['logo_image'], $input['remove_logo']);
        $uploads = [];
        try {
            if ($key === 'certificate') {
                $input = [...CertificateTemplate::current(), ...$input];
                // Keep prior template images for certificates whose issuance snapshot uses them.
                $input['image_path'] = Cfo::settings('certificate')['image_path'] ?? null;
                if ($request->boolean('remove_image')) {
                    $input['image_path'] = null;
                }
                $input['logo_path'] = Cfo::settings('certificate')['logo_path'] ?? null;
                if ($request->boolean('remove_logo')) {
                    $input['logo_path'] = null;
                }
                foreach (['image' => 'image_path', 'logo_image' => 'logo_path'] as $field => $path) {
                    if ($request->hasFile($field)) {
                        $input[$path] = Storage::disk(config('filesystems.upload_disk'))->putFile('certificate-templates', $request->file($field));
                        if (! $input[$path]) {
                            throw new \RuntimeException('The certificate image could not be stored. Please try again.');
                        }
                        $uploads[] = $input[$path];
                    }
                }
            }
            DB::transaction(function () use ($key, $input) {
                DB::table('cfo_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($input), 'created_at' => now(), 'updated_at' => now()]);
                Cfo::audit('settings.updated', $key, null);
                Cfo::touch('settings');
            });
        } catch (\Throwable $exception) {
            // Only files from this failed save are disposable, never earlier snapshots.
            foreach ($uploads as $path) {
                Storage::disk(config('filesystems.upload_disk'))->delete($path);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Settings saved.']);
    }

    public function academic(Request $request)
    {
        $input = $request->validate(['type' => 'required|in:course,year_level,section', 'name' => ['required', 'string', 'max:190', Rule::unique('academic_options')->where('type', $request->input('type'))]]);
        $id = DB::table('academic_options')->insertGetId([...$input, 'created_at' => now(), 'updated_at' => now()]);
        Cfo::touch('academic_options');
        Cfo::audit('academic_option.created', 'academic_option', $id);

        return response()->json(['message' => 'Reference option added.'], 201);
    }

    public function updateAcademic(Request $request, int $id)
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $input = $request->validate(['name' => 'required|string|max:190']);
        $option = DB::transaction(function () use ($id, $input) {
            // Match the registration workflow's lock order: courses, users, registrations.
            $courses = DB::table('academic_options')->where('type', 'course')->orderBy('id')->lockForUpdate()->get();
            $option = $courses->firstWhere('id', $id);
            abort_unless($option, 404);
            $normalize = static fn ($name) => mb_strtolower(trim((string) $name));
            if ($courses->contains(fn ($course) => $course->id !== $option->id && $normalize($course->name) === $normalize($input['name']))) {
                throw ValidationException::withMessages(['name' => 'This course already exists.']);
            }
            $oldName = $option->name;
            if ($oldName === $input['name']) {
                return $option;
            }
            DB::table('academic_options')->where('id', $id)->update(['name' => $input['name'], 'updated_at' => now()]);
            DB::table('users')->select('id', 'course')->whereNotNull('course')->orderBy('id')->lockForUpdate()->chunkById(200, function ($users) use ($oldName, $input, $normalize) {
                $ids = $users->filter(fn ($user) => $normalize($user->course) === $normalize($oldName))->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table('users')->whereIn('id', $ids)->update(['course' => $input['name'], 'updated_at' => now()]);
                }
            });
            // Update both current and archived registration names without changing unrelated snapshot fields.
            DB::table('recollection_registrations')->select('id', 'details', 'student_snapshot')->orderBy('id')->lockForUpdate()->chunkById(200, function ($registrations) use ($oldName, $input, $normalize) {
                foreach ($registrations as $registration) {
                    $changes = [];
                    foreach (['details', 'student_snapshot'] as $column) {
                        $snapshot = json_decode($registration->$column ?? 'null', true);
                        if (is_array($snapshot) && is_string($snapshot['course'] ?? null) && $normalize($snapshot['course']) === $normalize($oldName)) {
                            $snapshot['course'] = $input['name'];
                            $changes[$column] = json_encode($snapshot, JSON_THROW_ON_ERROR);
                        }
                    }
                    if ($changes !== []) {
                        DB::table('recollection_registrations')->where('id', $registration->id)->update([...$changes, 'updated_at' => now()]);
                    }
                }
            });
            foreach (['academic_options', 'users', 'registrations', 'schedules', 'attendance'] as $resource) {
                Cfo::touch($resource);
            }
            Cfo::audit('academic_option.updated', 'academic_option', $id, ['old_name' => $oldName, 'name' => $input['name']]);
            $option->name = $input['name'];

            return $option;
        }, 3);

        return response()->json(['message' => 'Course updated.', 'academic_option' => $option]);
    }

    public function deleteAcademic(int $id)
    {
        DB::transaction(function () use ($id) {
            DB::table('academic_options')->where('type', 'course')->orderBy('id')->lockForUpdate()->get();
            $option = DB::table('academic_options')->find($id);
            abort_unless($option, 404);
            $usedByProfile = $option->type === 'course'
                ? User::whereNotNull('course')->pluck('course')->contains(fn ($name) => mb_strtolower(trim($name)) === mb_strtolower(trim($option->name)))
                : User::where($option->type, $option->name)->exists();
            Cfo::require(! $usedByProfile, 'This option is used by student profiles.');
            if ($option->type === 'course') {
                Cfo::require(! DB::table('recollection_schedule_courses')->where('academic_option_id', $id)->exists(), 'This course is assigned to a Recollection Day.');
            }
            if ($option->type === 'course') {
                Cfo::require(! DB::table('course_year_sections')->where('course_id', $id)->exists(), 'This course has configured sections. Remove its unused sections first.');
            }
            if ($option->type === 'year_level') {
                Cfo::require(! Schedule::where('year_level', $option->name)->orWhereJsonContains('year_levels', $option->name)->exists(), 'This year level is used by a Recollection Day.');
            }
            if ($option->type === 'section') {
                Cfo::require(! Schedule::where('section', $option->name)->orWhereJsonContains('sections', $option->name)->exists(), 'This section is used by a Recollection Day.');
            }
            if ($option->type === 'year_level') {
                Cfo::require(! DB::table('course_year_sections')->where('year_level', $option->name)->exists(), 'This year level is used by configured course sections. Remove the unused configuration first.');
            }
            if ($option->type === 'section') {
                Cfo::require(! DB::table('course_year_sections')->pluck('section')->contains(fn ($section) => mb_strtolower($section) === mb_strtolower($option->name)), 'This section is used by configured course sections. Remove the unused configuration first.');
            }
            DB::table('academic_options')->where('id', $id)->delete();
            Cfo::touch('academic_options');
            Cfo::audit('academic_option.deleted', 'academic_option', $id);
        }, 3);

        return response()->json(['message' => 'Unused reference option removed.']);
    }

    public function courseYearSection(Request $request)
    {
        $request->merge(['section' => is_string($request->input('section')) ? trim($request->input('section')) : $request->input('section')]);
        $years = array_unique([...array_map('strval', range(1, 4)), ...DB::table('academic_options')->where('type', 'year_level')->pluck('name')->all()]);
        $input = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('academic_options', 'id')->where('type', 'course')],
            'year_level' => ['required', 'string', 'max:190', Rule::in($years)],
            'section' => 'required|string|max:190',
        ]);
        $id = DB::transaction(function () use ($input) {
            DB::table('academic_options')->where('id', $input['course_id'])->lockForUpdate()->first();
            $duplicate = DB::table('course_year_sections')->where('course_id', $input['course_id'])->where('year_level', $input['year_level'])
                ->pluck('section')->contains(fn ($section) => mb_strtolower($section) === mb_strtolower($input['section']));
            Cfo::require(! $duplicate, 'This section is already configured for this course and year level.');
            $id = DB::table('course_year_sections')->insertGetId([...$input, 'created_at' => now(), 'updated_at' => now()]);
            // Existing profile/registration validators continue using these global types.
            foreach (['year_level' => $input['year_level'], 'section' => $input['section']] as $type => $name) {
                DB::table('academic_options')->insertOrIgnore(['type' => $type, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
            Cfo::touch('academic_options');
            Cfo::audit('course_year_section.created', 'course_year_section', $id, $input);

            return $id;
        });

        return response()->json(['message' => 'Course section added.', 'course_year_section' => DB::table('course_year_sections')->find($id)], 201);
    }

    public function deleteCourseYearSection(int $id)
    {
        DB::transaction(function () use ($id) {
            $option = DB::table('course_year_sections')->find($id);
            abort_unless($option, 404);
            $course = DB::table('academic_options')->where('id', $option->course_id)->lockForUpdate()->first();
            $option = DB::table('course_year_sections')->where('id', $id)->lockForUpdate()->first();
            abort_unless($option, 404);
            $usedByStudent = User::where('role', 'Student')->where('course', $course->name)->where('year_level', $option->year_level)
                ->pluck('section')->contains(fn ($section) => mb_strtolower((string) $section) === mb_strtolower($option->section));
            Cfo::require(! $usedByStudent, 'This course/year section is used by student profiles and cannot be removed.');
            $usedBySchedule = Schedule::with('courses')->whereHas('courses', fn ($query) => $query->where('academic_options.id', $option->course_id))
                ->get()->contains(function ($schedule) use ($option) {
                    if (! $schedule->includesYearLevel($option->year_level)) {
                        return false;
                    }
                    $sections = $schedule->sectionsForCourse($option->course_id, $option->year_level);
                    if ($sections === [] && $schedule->hasYearSpecificSectionsForCourse($option->course_id)) {
                        return false;
                    }

                    return $sections === [] || in_array(mb_strtolower($option->section), array_map('mb_strtolower', $sections), true);
                });
            Cfo::require(! $usedBySchedule, 'This course/year section is assigned to a Recollection Day and cannot be removed.');
            DB::table('course_year_sections')->where('id', $id)->delete();
            Cfo::touch('academic_options');
            Cfo::audit('course_year_section.deleted', 'course_year_section', $id, ['course_id' => $option->course_id, 'year_level' => $option->year_level, 'section' => $option->section]);
        });

        return response()->json(['message' => 'Unused course section removed.']);
    }

    public function recovery(Request $request, PasswordResetRequest $reset)
    {
        $input = $request->validate(['status' => 'required|in:Approved,Rejected', 'identity_verified' => 'exclude_unless:status,Approved|required|accepted']);
        DB::transaction(function () use ($reset, $input, $request) {
            User::whereKey($reset->user_id)->lockForUpdate()->firstOrFail();
            $reset = PasswordResetRequest::lockForUpdate()->findOrFail($reset->id);
            Cfo::require($reset->status === 'Pending', 'This recovery request is no longer pending.');
            $reset->fill(['status' => $input['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if ($input['status'] === 'Approved') {
                Cfo::require($reset->user->status === 'Approved', 'Only approved accounts can recover their password.');
                $temporary = Str::random(11).random_int(0, 9);
                $reset->temp_password_hash = Hash::make($temporary);
                $reset->expires_at = null;
                Cache::forever('recovery-secret:'.$reset->id, Crypt::encryptString($temporary));
                DB::table('sessions')->where('user_id', $reset->user_id)->delete();
            }
            $reset->save();
            Cfo::audit('recovery.'.strtolower($input['status']), 'password_reset', $reset->id);
        });

        return response()->json(['message' => 'Decision saved. The requesting student page updates automatically. Approved temporary passwords have no time limit and can only be used once.']);
    }
}
