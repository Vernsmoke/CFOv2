<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->index(['year', 'archived_at', 'created_at', 'id'], 'registrations_active_date_index');
            $table->index(['year', 'archived_at', 'receipt_status'], 'registrations_active_receipt_index');
        });
    }

    public function down(): void
    {
        Schema::table('recollection_registrations', function (Blueprint $table) {
            $table->dropIndex('registrations_active_date_index');
            $table->dropIndex('registrations_active_receipt_index');
        });
    }
};
