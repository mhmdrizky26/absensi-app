<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A short code per class ("7A") that subject teachers use to see who is
     * present in that class today. Unique within an academic year.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('view_code', 20)->nullable()->after('name');
            $table->unique(['academic_year_id', 'view_code']);
        });

        $romans = ['VII' => 7, 'VIII' => 8, 'IX' => 9];

        foreach (DB::table('classrooms')->get(['id', 'grade', 'name']) as $classroom) {
            $section = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', substr((string) strrchr($classroom->name, '-'), 1)));
            $grade = $classroom->grade ?: ($romans[strtok($classroom->name, '-')] ?? '');

            DB::table('classrooms')->where('id', $classroom->id)->update(['view_code' => substr("{$grade}{$section}", 0, 20) ?: null]);
        }
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'view_code']);
            $table->dropColumn('view_code');
        });
    }
};
