<?php

use App\Support\RandomCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every student a QR card. Existing students get a token here; new
     * students get one when they are created (see Student::booted()).
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->char('qr_token', 12)->nullable()->unique()->after('status');
            $table->unsignedSmallInteger('qr_version')->default(1)->after('qr_token');
            $table->timestamp('qr_issued_at')->nullable()->after('qr_version');
            $table->timestamp('qr_revoked_at')->nullable()->after('qr_issued_at');
        });

        $usedTokens = [];

        DB::table('students')->whereNull('qr_token')->orderBy('id')->select('id')->chunkById(500, function ($students) use (&$usedTokens): void {
            foreach ($students as $student) {
                do {
                    $token = RandomCode::generate(12);
                } while (isset($usedTokens[$token]));

                $usedTokens[$token] = true;

                DB::table('students')->where('id', $student->id)->update([
                    'qr_token' => $token,
                    'qr_issued_at' => now(),
                ]);
            }
        });

        Schema::table('students', function (Blueprint $table) {
            $table->char('qr_token', 12)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['qr_token', 'qr_version', 'qr_issued_at', 'qr_revoked_at']);
        });
    }
};
