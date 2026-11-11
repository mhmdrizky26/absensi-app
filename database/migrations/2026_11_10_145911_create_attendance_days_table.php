<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per school day once it is finished ("Selesaikan absen"): from
     * then on students without a mark are Alpa.
     */
    public function up(): void
    {
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->timestamp('finalized_at');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
