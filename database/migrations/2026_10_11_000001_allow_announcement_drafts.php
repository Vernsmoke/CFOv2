<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    // Drafts remain hidden from students until they have a publication timestamp.
    public function up(): void
    {
        Schema::table('cfo_announcements', fn (Blueprint $table) => $table->timestamp('published_at')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('cfo_announcements')->whereNull('published_at')->exists()) {
            throw new RuntimeException('Publish or delete draft announcements before rolling this migration back.');
        }
        Schema::table('cfo_announcements', fn (Blueprint $table) => $table->timestamp('published_at')->nullable(false)->change());
    }
};
