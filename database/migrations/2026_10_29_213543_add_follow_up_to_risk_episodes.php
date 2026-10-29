<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin or guru BK can mark a risky student as handled, with a short note.
     */
    public function up(): void
    {
        Schema::table('risk_episodes', function (Blueprint $table) {
            $table->timestamp('handled_at')->nullable()->after('notified_at');
            $table->foreignId('handled_by')->nullable()->after('handled_at')->constrained('users')->nullOnDelete();
            $table->string('handled_note', 300)->nullable()->after('handled_by');
        });
    }

    public function down(): void
    {
        Schema::table('risk_episodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['handled_at', 'handled_note']);
        });
    }
};
