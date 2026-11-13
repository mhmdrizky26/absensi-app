<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Wali kelas and guru piket are no longer roles: a teacher is wali kelas
     * by leading a class and guru piket on the days of the duty schedule, so
     * one account can be both. Both old roles become "guru".
     */
    public function up(): void
    {
        DB::table('users')->whereIn('role', ['wali_kelas', 'guru_piket'])->update(['role' => 'guru']);
    }

    /**
     * Teachers leading a class go back to wali kelas, the others to guru piket.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('role', 'guru')
            ->whereIn('id', DB::table('classrooms')->whereNotNull('homeroom_teacher_id')->select('homeroom_teacher_id'))
            ->update(['role' => 'wali_kelas']);
        DB::table('users')->where('role', 'guru')->update(['role' => 'guru_piket']);
    }
};
