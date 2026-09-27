<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins may now type their own class code (4–12 characters), so the
     * column no longer has a fixed length of 6.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('access_code', 12)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->char('access_code', 6)->change();
        });
    }
};
