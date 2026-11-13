<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subject teachers now log in with their own account to see today's
     * attendance of any class, so classes no longer need a view code.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['academic_year_id', 'view_code']);
            $table->dropColumn('view_code');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('view_code', 20)->nullable()->after('name');
            $table->unique(['academic_year_id', 'view_code']);
        });
    }
};
