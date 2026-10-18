<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecollectionRegistrationFilters;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecollectionRegistrationController extends Controller
{
    private function baseQuery(): Builder
    {
        return Registration::currentYear()->whereNull('recollection_schedule_id')
            ->whereHas('user', fn ($q) => $q->where('role', 'Student'));
    }

    private function yearSectionSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(year_level, '') || COALESCE(section, '')"
            : "CONCAT(COALESCE(year_level, ''), COALESCE(section, ''))";
    }

    private function filtered(array $filters): Builder
    {
        $query = $this->baseQuery();
        $query->whereHas('user', function (Builder $student) use ($filters) {
            if ($departments = $filters['departments'] ?? []) {
                $catalog = \App\Services\AcademicCatalog::courses();
                $names = array_column(array_filter($catalog, fn ($course) => in_array($course['department'], $departments, true)), 'name');
                $student->where(function (Builder $scope) use ($departments, $names, $catalog) {
                    $scope->whereIn('course', $names)->orWhere(function (Builder $legacy) use ($departments, $catalog) {
                        $legacy->whereNotIn('course', array_column($catalog, 'name'));
                        if (! in_array('Other / Legacy Courses', $departments, true)) $legacy->whereIn('department', $departments);
                    });
                });
                if ($courses = $filters['courses'] ?? []) {
                    $student->whereIn('course', $courses);
                    if ($majors = $filters['majors'] ?? []) {
                        $student->where(function (Builder $scope) use ($majors) {
                            foreach ($majors as $major) {
                                [$course, $name] = json_decode($major, true);
                                $scope->orWhere(fn (Builder $pair) => $pair->where('course', $course)->where('major', $name));
                            }
                        });
                    }
                }
            }
            if ($filters['course'] ?? null) {
                $student->where('course', $filters['course']);
            }
            if ($filters['year_section'] ?? null) {
                $student->whereRaw('('.$this->yearSectionSql().') = ?', [$filters['year_section']]);
            }
            // Each word can match any displayed student field. Treat LIKE wildcards literally.
            foreach (preg_split('/[\s,]+/u', trim($filters['search'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
                $student->where(function (Builder $part) use ($like) {
                    foreach (['name', 'first_name', 'last_name', 'student_id', 'course', 'year_level', 'section'] as $column) {
                        $part->orWhereRaw($column." LIKE ? ESCAPE '!'", [$like]);
                    }
                    $part->orWhereRaw('('.$this->yearSectionSql().") LIKE ? ESCAPE '!'", [$like]);
                });
            }
        });
        if ($filters['status'] ?? null) {
            $query->whereIn('receipt_status', Registration::RECEIPT_LABELS[$filters['status']]);
        }
        if (($filters['schedule_status'] ?? null) === 'scheduled') {
            $query->whereNotNull('recollection_schedule_id');
        } elseif (($filters['schedule_status'] ?? null) === 'not_yet_scheduled') {
            $query->whereNull('recollection_schedule_id');
        }
        if ($filters['date_from'] ?? null) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if ($filters['date_to'] ?? null) {
            $query->where('created_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());
        }

        return $query->with('user:id,name,first_name,last_name,student_id,course,year_level,section')
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    public function index(RecollectionRegistrationFilters $request)
    {
        $filters = $request->validated();
        // Capture before reading records so concurrent student submissions trigger a refresh.
        $liveVersions = DB::table('cfo_resource_versions')->orderBy('resource')->pluck('version', 'resource')->all();
        $liveVersions['year'] = now()->year;
        $counts = $this->baseQuery()->selectRaw('receipt_status, COUNT(*) AS aggregate')->groupBy('receipt_status')->pluck('aggregate', 'receipt_status');
        $stats = ['Total Registrations' => $counts->sum()];
        foreach (Registration::RECEIPT_LABELS as $label => $statuses) {
            $stats[$label] = collect($statuses)->sum(fn ($status) => (int) ($counts[$status] ?? 0));
        }
        $students = User::where('role', 'Student')->whereHas('registrations', fn ($q) => $q->currentYear()->whereNull('recollection_schedule_id'));
        $courses = (clone $students)->whereNotNull('course')->where('course', '!=', '')->distinct()->orderBy('course')->pluck('course');
        $academicPopulation = (clone $students)->select('department', 'course', 'major', 'year_level', 'section')->distinct()->get();
        $yearSections = (clone $students)->select('year_level', 'section')->distinct()->get()->toBase()
            ->map(fn ($student) => ($student->year_level ?? '').($student->section ?? ''))->filter()->unique()->sort()->values();
        // Preserve a deep-linked filter even after its last matching student moves to Attendance.
        if ($filters['course'] ?? null) {
            $courses = $courses->push($filters['course'])->unique()->sort()->values();
        }
        if ($filters['year_section'] ?? null) {
            $yearSections = $yearSections->push($filters['year_section'])->unique()->sort()->values();
        }
        $registrations = $this->filtered($filters)->paginate($filters['per_page'] ?? 10)->withQueryString();
        // Scheduling the last row on a page must not leave the admin on an empty page.
        if ($registrations->isEmpty() && $registrations->total() > 0 && $registrations->currentPage() > 1) {
            return redirect()->route('admin.registrations.index', [...$filters, 'page' => $registrations->lastPage()]);
        }

        return response()->view('admin.registrations.index', compact('filters', 'stats', 'courses', 'yearSections', 'registrations', 'liveVersions', 'academicPopulation'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Registration $registration)
    {
        abort_unless($registration->year === now()->year && ! $registration->is_archived, 404);
        $registration->load(['user', 'schedule']);
        abort_unless($registration->user?->role === 'Student', 404);
        $schedules = Schedule::where('active', true)->whereNull('finalized_at')->where('date', '>=', today()->toDateString())
            ->where('date', '<', now()->startOfYear()->addYear()->toDateString())
            ->withCount(['registrations' => fn ($q) => $q->active()])->orderBy('date')->get();

        return response()->view('admin.registrations.details', compact('registration', 'schedules'))->header('Cache-Control', 'private, no-store');
    }

    public function export(RecollectionRegistrationFilters $request)
    {
        $query = $this->filtered($request->validated());

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Student Name', 'ID Number', 'Course', 'Year & Section', 'Department', 'Major', 'Date Registered', 'Status'], ',', '"', '');
            // Chunked eager loading bounds memory while retaining the table's ordering.
            foreach ($query->lazy(500) as $row) {
                $group = $row->academicProfile();
                $values = [$row->student_display_name, $row->user->student_id, $group['course'],
                    ($group['year_level'] ?? '').($group['section'] ?? ''), $group['department'], $group['major'], $row->created_at?->format('Y-m-d H:i:s'), $row->receipt_label];
                $values = array_map(function ($value) {
                    $value = (string) $value;

                    // Prevent spreadsheet formula execution, including whitespace-prefixed formulas.
                    return preg_match('/^[\s\x00-\x1f]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
                }, $values);
                fputcsv($output, $values, ',', '"', '');
            }
            fclose($output);
        }, 'recollection-registrations-'.now()->year.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
