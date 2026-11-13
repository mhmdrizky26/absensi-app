<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * School days held online (sekolah daring): the gate stays shut and every
     * student without a mark is recorded Hadir automatically.
     */
    public function up(): void
    {
        Schema::create('online_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('description', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_days');
    }
};
