<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablets at the school gate ("Tab 1", "Tab 2", …). Each logs in once with
     * its code and stays logged in until the admin revokes it.
     */
    public function up(): void
    {
        Schema::create('gate_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 30)->unique();
            $table->string('login_token', 64);
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedSmallInteger('pending_scans')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_stations');
    }
};
