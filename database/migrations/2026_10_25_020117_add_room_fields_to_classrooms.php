<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A class without its own room (e.g. VIII-K) keeps its students, wali
     * kelas and rapor, but each student sits in another class's room for the
     * year: study_classroom_id on their placement.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('has_room')->default(true)->after('name');
        });

        Schema::table('classroom_student', function (Blueprint $table) {
            $table->foreignId('study_classroom_id')->nullable()->after('classroom_id')->constrained('classrooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classroom_student', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_classroom_id');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('has_room');
        });
    }
};
