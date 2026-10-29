<?php

namespace App\Console\Commands;

use App\Actions\TrackRiskEpisodes;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('risiko:kirim-wa {--paksa : Jalankan sekarang walau belum jamnya, sudah jalan hari ini, atau notifikasi dimatikan}')]
#[Description('Perbarui daftar siswa berisiko (hapus yang sudah membaik) dan kabari orang tua lewat WhatsApp bila dinyalakan')]
class SendRiskAlerts extends Command
{
    /**
     * Called every minute by the scheduler; does its work once per school
     * day at the configured time.
     */
    public function handle(WhatsAppSettings $settings, TrackRiskEpisodes $trackRiskEpisodes): int
    {
        if (! $this->option('paksa') && ! $settings->isDue(now())) {
            return self::SUCCESS;
        }

        $summary = $trackRiskEpisodes->handle(today(), notify: $settings->enabled());
        $settings->markRun(today());

        $this->info(sprintf(
            '%d siswa baru berisiko, %d membaik (dihapus), %d siswa dikabari (%d pesan%s).',
            $summary['opened'],
            $summary['closed'],
            $summary['notified'],
            $summary['messages'],
            ! $settings->enabled() ? ', notifikasi WA mati' : ($settings->testMode() ? ', mode uji: tidak dikirim' : ''),
        ));

        return self::SUCCESS;
    }
}
