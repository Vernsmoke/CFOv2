<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recollection_schedules', function (Blueprint $table) {
            $table->string('status', 20)->default('Closed')->index();
        });

        // Only the new status is backfilled. Existing registrations, snapshots,
        // finalized batches, timestamps, and active values remain untouched.
        DB::table('recollection_schedules')->where('active', true)->update(['status' => 'Open']);
    }

    public function down(): void
    {
        Schema::table('recollection_schedules', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
