<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requests to excuse a student for a school activity (competitions,
     * representing the school, …). They become Dispensasi marks only after
     * both the wali kelas and a guru piket approve.
     */
    public function up(): void
    {
        Schema::create('dispensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 10)->default('menunggu')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('homeroom_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('homeroom_approved_at')->nullable();
            $table->foreignId('duty_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('duty_approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'status']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('dispensation_id')->nullable()->after('attachment_path')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispensation_id');
        });

        Schema::dropIfExists('dispensations');
    }
};
