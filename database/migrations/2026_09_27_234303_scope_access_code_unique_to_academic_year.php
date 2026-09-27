<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Class codes follow a fixed pattern (ESSAR7A), so the same code comes
     * back every year. It only has to be unique within one academic year,
     * because a code only opens classes of the active year.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['access_code']);
            $table->unique(['academic_year_id', 'access_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'access_code']);
            $table->unique(['access_code']);
        });
    }
};
