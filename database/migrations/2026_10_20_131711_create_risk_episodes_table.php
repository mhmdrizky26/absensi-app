<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('semester', 10);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('level', 12);
            $table->json('reasons');
            $table->date('streak_ended_on')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'ended_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_episodes');
    }
};
