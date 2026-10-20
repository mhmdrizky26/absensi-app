<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('relation', 20)->nullable();
            $table->string('phone', 20);
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_contacts');
    }
};
