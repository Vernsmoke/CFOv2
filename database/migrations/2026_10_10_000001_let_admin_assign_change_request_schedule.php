<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // Students now submit only a reason. The administrator picks the new
        // schedule when approving, so the student's requested schedule is optional.
        Schema::table('schedule_change_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_schedule_id')->nullable()->change();
        });

        if (! Schema::hasColumn('schedule_change_requests', 'assigned_schedule_id')) {
            Schema::table('schedule_change_requests', function (Blueprint $table) {
                $table->foreignId('assigned_schedule_id')->nullable()->after('requested_schedule_id')->constrained('recollection_schedules');
            });
        }

        // Requests approved under the old flow were assigned the schedule the student chose.
        DB::table('schedule_change_requests')
            ->where('status', 'Approved')->whereNull('assigned_schedule_id')->whereNotNull('requested_schedule_id')
            ->update(['assigned_schedule_id' => DB::raw('requested_schedule_id')]);
    }

    public function down(): void
    {
        // Preserve the administrator's approved assignment for the legacy flow.
        if (Schema::hasColumn('schedule_change_requests', 'assigned_schedule_id')) {
            DB::table('schedule_change_requests')->whereNull('requested_schedule_id')->whereNotNull('assigned_schedule_id')
                ->update(['requested_schedule_id' => DB::raw('assigned_schedule_id')]);
        }
        DB::table('schedule_change_requests')->whereNull('requested_schedule_id')
            ->update(['requested_schedule_id' => DB::raw('current_schedule_id')]);

        if (Schema::hasColumn('schedule_change_requests', 'assigned_schedule_id')) {
            Schema::table('schedule_change_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_schedule_id');
            });
        }

        Schema::table('schedule_change_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_schedule_id')->nullable(false)->change();
        });
    }
};
