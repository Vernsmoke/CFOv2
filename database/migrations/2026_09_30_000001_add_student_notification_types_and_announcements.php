<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfo_notifications', function (Blueprint $table) {
            $table->text('message')->change();
        });

        Schema::table('cfo_notifications', function (Blueprint $table) {
            $table->string('type', 64)->nullable()->after('message');
            $table->string('related_type', 64)->nullable()->after('type');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->index(['user_id', 'read_at'], 'cfo_notifications_user_read_idx');
        });

        Schema::table('schedule_change_requests', function (Blueprint $table) {
            $table->text('review_reason')->nullable()->after('status');
        });

        Schema::create('cfo_announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('publication_key')->unique();
            $table->string('title', 190);
            $table->string('preview', 280);
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->index();
            $table->timestamps();
        });

        DB::table('cfo_resource_versions')->insertOrIgnore([
            'resource' => 'announcements',
            'version' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cfo_announcements');
        Schema::table('schedule_change_requests', function (Blueprint $table) {
            $table->dropColumn('review_reason');
        });
        Schema::table('cfo_notifications', function (Blueprint $table) {
            $table->dropIndex('cfo_notifications_user_read_idx');
            $table->dropColumn(['type', 'related_type', 'related_id']);
        });
        // Keep TEXT on rollback to preserve long notification messages.
        DB::table('cfo_resource_versions')->where('resource', 'announcements')->delete();
    }
};
