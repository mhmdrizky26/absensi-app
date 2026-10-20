<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->string('trigger', 12);
            $table->string('level', 12);
            $table->string('reason', 500);
            $table->string('status', 12)->index();
            $table->string('error', 500)->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_messages');
    }
};
