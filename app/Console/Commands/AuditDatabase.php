<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditDatabase extends Command
{
    protected $signature = 'cfo:audit-database';
    protected $description = 'Read-only schema, foreign-key, and record-integrity audit (no personal data output).';

    public function handle(): int
    {
        $errors = [];
        $links = [
            'recollection_registrations' => ['user_id' => 'users', 'recollection_schedule_id' => 'recollection_schedules', 'receipt_verified_by' => 'users', 'attendance_recorded_by' => 'users', 'attendance_verified_by' => 'users'],
            'schedule_change_requests' => ['registration_id' => 'recollection_registrations', 'current_schedule_id' => 'recollection_schedules', 'requested_schedule_id' => 'recollection_schedules', 'assigned_schedule_id' => 'recollection_schedules', 'reviewed_by' => 'users'],
            'certificates' => ['registration_id' => 'recollection_registrations', 'issued_by' => 'users', 'revoked_by' => 'users'],
            'password_reset_requests' => ['user_id' => 'users', 'reviewed_by' => 'users'],
            'cfo_notifications' => ['user_id' => 'users'],
            'cfo_announcements' => ['created_by' => 'users'],
            'activity_logs' => ['actor_id' => 'users'],
            'recollection_schedule_courses' => ['schedule_id' => 'recollection_schedules', 'academic_option_id' => 'academic_options'],
        ];
        $tables = array_unique(['users', 'academic_options', 'recollection_schedules', 'cfo_settings', 'cfo_resource_versions', ...array_keys($links)]);
        $counts = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) { $errors[] = "Missing table: $table"; continue; }
            $counts[] = [$table, DB::table($table)->count()];
        }
        foreach (['users' => ['original_email', 'login_email', 'password_change_required', 'password_change_expires_at'], 'recollection_schedules' => ['year_level', 'section', 'slot_limit'], 'recollection_registrations' => ['attendance_recorded_at'], 'certificates' => ['revoked_at', 'revocation_reason']] as $table => $columns) {
            foreach ($columns as $column) if (! Schema::hasColumn($table, $column)) $errors[] = "Missing column: $table.$column";
        }
        foreach ($links as $table => $columns) {
            if (! Schema::hasTable($table)) continue;
            $keys = Schema::getForeignKeys($table);
            foreach ($columns as $column => $parent) {
                if (! Schema::hasColumn($table, $column)) { $errors[] = "Missing column: $table.$column"; continue; }
                $found = collect($keys)->contains(fn ($key) => $key['columns'] === [$column] && $key['foreign_table'] === $parent && $key['foreign_columns'] === ['id']);
                if (! $found) $errors[] = "Missing foreign key: $table.$column -> $parent.id";
                if (! Schema::hasTable($parent)) continue;
                $orphans = DB::table("$table as child")->leftJoin("$parent as parent", "child.$column", '=', 'parent.id')->whereNotNull("child.$column")->whereNull('parent.id')->count();
                if ($orphans) $errors[] = "Orphans: $table.$column ($orphans)";
            }
        }
        foreach (['users' => [['email'], ['student_id'], ['system_username'], ['original_email'], ['login_email']], 'recollection_registrations' => [['user_id', 'year', 'attempt_no']], 'certificates' => [['registration_id'], ['certificate_number']], 'academic_options' => [['type', 'name']], 'recollection_schedule_courses' => [['schedule_id', 'academic_option_id']]] as $table => $groups) {
            if (! Schema::hasTable($table)) continue;
            $indexes = Schema::getIndexes($table);
            foreach ($groups as $columns) {
                if (! collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === $columns)) $errors[] = 'Missing unique index: '.$table.'.'.implode('+', $columns);
            }
        }
        if ($errors !== []) return $this->report($counts, $errors);
        if (DB::table('recollection_registrations')->whereNull('archived_at')->select('user_id', 'year')->groupBy('user_id', 'year')->havingRaw('COUNT(*) > 1')->exists()) {
            $errors[] = 'Multiple active registration attempts for the same student and year.';
        }
        if (Schema::hasTable('recollection_schedule_courses')) {
            $invalid = DB::table('recollection_schedule_courses as link')->join('academic_options as option', 'option.id', '=', 'link.academic_option_id')->where('option.type', '!=', 'course')->count();
            if ($invalid) $errors[] = "Schedule links to non-course options: $invalid";
        }
        foreach (['course', 'year_level', 'section'] as $type) {
            $invalid = DB::table('users')->whereNotNull($type)->where($type, '!=', '')->whereNotIn($type, DB::table('academic_options')->where('type', $type)->select('name'))->count();
            if ($invalid) $errors[] = "Student profile values missing from academic options ($type): $invalid";
            if ($type !== 'course') {
                $invalid = DB::table('recollection_schedules')->whereNotNull($type)->where($type, '!=', '')->whereNotIn($type, DB::table('academic_options')->where('type', $type)->select('name'))->count();
                if ($invalid) $errors[] = "Schedule values missing from academic options ($type): $invalid";
            }
        }
        $invalid = DB::table('recollection_registrations as r')->join('recollection_schedules as s', 's.id', '=', 'r.recollection_schedule_id')->where(function ($q) { $q->where('r.status', '!=', 'Approved')->orWhere('r.receipt_status', '!=', 'Approved'); })->count();
        if ($invalid) $errors[] = "Assigned registrations without approval/verified receipts: $invalid";
        $certificates = DB::table('certificates as c')->join('recollection_registrations as r', 'r.id', '=', 'c.registration_id');
        if (Schema::hasColumn('certificates', 'revoked_at')) $certificates->whereNull('c.revoked_at');
        $invalid = $certificates->where(fn ($q) => $q->where('r.attendance', '!=', 'Present')->orWhereNull('r.attendance_verified_at')->orWhereNull('r.attendance_verified_by')->orWhereNull('r.recollection_schedule_id'))->count();
        if ($invalid) $errors[] = "Active certificates without verified Present attendance: $invalid";
        $legacy = DB::table('recollection_schedules as s')->whereNotExists(function ($query) {
            $query->selectRaw('1')->from('recollection_schedule_courses as link')->whereColumn('link.schedule_id', 's.id');
        })->get(['s.id', 's.title', 's.date', 's.year_level', 's.section']);
        if ($legacy->isNotEmpty()) {
            $this->warn('Configuration still needed: legacy schedules without course links are hidden from student course browsing. Set their intended course, year level and section in the admin editor.');
            $this->table(['Schedule ID', 'Title', 'Date', 'Year level', 'Section'], $legacy->map(fn ($row) => (array) $row)->all());
        }
        return $this->report($counts, $errors);
    }

    private function report(array $counts, array $errors): int
    {
        $this->line('Connection: '.DB::getDriverName().' / '.DB::getDatabaseName());
        $this->table(['Table', 'Rows'], $counts);
        foreach ($errors as $error) $this->error($error);
        if ($errors === []) $this->info('PASS: required schema, foreign keys, unique indexes, and checked record relationships are valid.');
        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
