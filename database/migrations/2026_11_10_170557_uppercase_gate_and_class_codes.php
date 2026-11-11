<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tab codes and class codes are kept in capitals (TAB1, 7A) so they read
     * the same on every screen. Typing them stays case-insensitive.
     */
    public function up(): void
    {
        DB::table('gate_stations')->update(['code' => DB::raw('UPPER(code)')]);
        DB::table('classrooms')->whereNotNull('view_code')->update(['view_code' => DB::raw('UPPER(view_code)')]);
    }

    public function down(): void
    {
        DB::table('gate_stations')->update(['code' => DB::raw('LOWER(code)')]);
        DB::table('classrooms')->whereNotNull('view_code')->update(['view_code' => DB::raw('LOWER(view_code)')]);
    }
};
