<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing administrators keep all of their current access. A Super
        // Admin can deliberately create lower-privileged CFO Staff afterward.
        DB::table('users')->where('role', 'Admin')->update(['role' => 'SuperAdmin']);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['CFOStaff', 'SuperAdmin'])->update(['role' => 'Admin']);
    }
};
