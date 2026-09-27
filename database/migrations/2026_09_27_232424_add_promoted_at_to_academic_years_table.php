<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When students were moved up into this academic year. Set once, so the
     * promotion can never run twice for the same year.
     */
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->timestamp('promoted_at')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn('promoted_at');
        });
    }
};
