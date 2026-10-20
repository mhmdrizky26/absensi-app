<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends WhatsApp text messages to parents through Fonnte (fonnte.com), a
 * gateway that sends from an ordinary WhatsApp number linked by QR. The
 * number may be banned by WhatsApp, so use a spare one.
 */
class WhatsAppClient
{
    /** {{1}} nama siswa, {{2}} kelas, {{3}} alasan, {{4}} wali kelas, {{5}} sekolah. */
    public const TEXT_HIGH = 'Yth. Bapak/Ibu wali dari {{1}} (kelas {{2}}). Kehadiran Ananda memerlukan perhatian segera: {{3}} Mohon segera menghubungi wali kelas, {{4}}. Pesan otomatis dari {{5}}, nomor ini tidak menerima balasan.';

    public const TEXT_WATCH = 'Yth. Bapak/Ibu wali dari {{1}} (kelas {{2}}). Kami informasikan kehadiran Ananda perlu diperhatikan: {{3}} Bila ada kendala, silakan menghubungi wali kelas, {{4}}. Pesan otomatis dari {{5}}, nomor ini tidak menerima balasan.';

    public function isConfigured(): bool
    {
        return filled(config('services.fonnte.token'));
    }

    /**
     * Tell a parent about their child's attendance risk; returns Fonnte's message id.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: string}  $parameters  name, class, reason, homeroom teacher, school
     */
    public function sendRiskAlert(string $to, string $level, array $parameters): string
    {
        return $this->send($to, self::riskText($level === AttendanceRisk::LEVEL_HIGH ? self::TEXT_HIGH : self::TEXT_WATCH, $parameters));
    }

    /**
     * A short message to check that the linked number can send.
     */
    public function sendTest(string $to): string
    {
        return $this->send($to, 'Pesan uji dari aplikasi absensi '.config('app.school_name').': notifikasi WhatsApp untuk orang tua sudah tersambung.');
    }

    /**
     * Fill the {{n}} placeholders of a risk text.
     *
     * @param  list<string>  $parameters
     */
    public static function riskText(string $text, array $parameters): string
    {
        foreach (array_values($parameters) as $index => $value) {
            $text = str_replace('{{'.($index + 1).'}}', trim(preg_replace('/\s+/', ' ', $value)) ?: '-', $text);
        }

        return $text;
    }

    private function send(string $to, string $message): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Fonnte belum diatur: isi FONNTE_TOKEN di file .env dengan token device dari fonnte.com.');
        }

        $response = Http::withHeaders(['Authorization' => config('services.fonnte.token')])
            ->asForm()
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.fonnte.com/send', ['target' => $to, 'message' => $message, 'countryCode' => '62']);

        if ($response->failed() || $response->json('status') !== true) {
            $reason = $response->json('reason') ?? $response->json('detail') ?? $response->body();

            throw new RuntimeException(mb_substr("Fonnte menolak (HTTP {$response->status()}): {$reason}", 0, 480));
        }

        return (string) ($response->json('id.0') ?? $response->json('id') ?? '');
    }
}
