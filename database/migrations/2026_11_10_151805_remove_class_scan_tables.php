<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance moved from per-class scanning (class codes, one session per
     * class per day) to the gate tablets. Marks recorded by class scans stay;
     * only their link to the old session is dropped.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_session_id');
        });

        Schema::dropIfExists('attendance_sessions');

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'access_code']);
            $table->dropColumn('access_code');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('access_code', 12)->nullable()->after('name');
            $table->unique(['academic_year_id', 'access_code']);
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'date']);
            $table->index('date');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('attendance_session_id')->nullable()->after('recorded_at')->constrained()->nullOnDelete();
        });
    }
};
