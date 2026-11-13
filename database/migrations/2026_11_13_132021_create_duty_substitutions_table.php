<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-day swaps in the duty schedule: on this date the substitute is guru
     * piket instead of the scheduled teacher.
     */
    public function up(): void
    {
        Schema::create('duty_substitutions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('absent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('substitute_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['date', 'absent_user_id']);
            $table->unique(['date', 'substitute_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_substitutions');
    }
};
