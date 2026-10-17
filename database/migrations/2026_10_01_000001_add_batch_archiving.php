<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users');
            $table->string('archive_reason')->nullable();
        });
        Schema::table('recollection_schedules', fn (Blueprint $table) => $table->timestamp('finalized_at')->nullable());
        DB::table('recollection_registrations')->where('year', '<', now()->year)
            ->update(['archived_at' => now(), 'archive_reason' => 'year_rollover']);
    }

    public function down(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archived_at', 'archive_reason']);
        });
        Schema::table('recollection_schedules', fn (Blueprint $table) => $table->dropColumn('finalized_at'));
    }
};
