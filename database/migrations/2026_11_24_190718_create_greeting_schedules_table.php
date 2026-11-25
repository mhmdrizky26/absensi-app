<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The weekly sapa pagi schedule: which teachers greet students at the
     * gate on which weekday (1 = Senin … 5 = Jumat), the same every week.
     * They may record attendance from when the gate opens until jam masuk.
     */
    public function up(): void
    {
        Schema::create('greeting_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->timestamps();

            $table->unique(['user_id', 'weekday']);
            $table->index('weekday');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greeting_schedules');
    }
};
