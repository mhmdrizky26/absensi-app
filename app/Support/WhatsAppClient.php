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
    /** Penanda yang bisa dipakai di isi pesan, sesuai urutan nilai dari SendRiskMessage::parameters(). */
    public const PLACEHOLDERS = ['nama', 'kelas', 'alasan', 'wali_kelas', 'sekolah'];

    public const DEFAULT_TEXT_HIGH = 'Yth. Bapak/Ibu wali dari {nama} (kelas {kelas}). Kehadiran Ananda memerlukan perhatian segera: {alasan} Mohon segera menghubungi wali kelas, {wali_kelas}. Pesan otomatis dari {sekolah}, nomor ini tidak menerima balasan.';

    public const DEFAULT_TEXT_WATCH = 'Yth. Bapak/Ibu wali dari {nama} (kelas {kelas}). Kami informasikan kehadiran Ananda perlu diperhatikan: {alasan} Bila ada kendala, silakan menghubungi wali kelas, {wali_kelas}. Pesan otomatis dari {sekolah}, nomor ini tidak menerima balasan.';

    public const DEFAULT_TEXT_TEST = 'Pesan uji dari aplikasi absensi {sekolah}: notifikasi WhatsApp untuk orang tua sudah tersambung.';

    public function __construct(private WhatsAppSettings $settings) {}

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
        return $this->send($to, self::riskText($level === AttendanceRisk::LEVEL_HIGH ? $this->settings->textHigh() : $this->settings->textWatch(), $parameters));
    }

    /**
     * A short message to check that the linked number can send.
     */
    public function sendTest(string $to): string
    {
        return $this->send($to, self::riskText($this->settings->textTest(), ['', '', '', '', (string) config('app.school_name')]));
    }

    /**
     * Fill the {nama}, {kelas}, {alasan}, {wali_kelas} and {sekolah} placeholders.
     *
     * @param  list<string>  $parameters  values in PLACEHOLDERS order
     */
    public static function riskText(string $text, array $parameters): string
    {
        foreach (self::PLACEHOLDERS as $index => $placeholder) {
            $value = trim(preg_replace('/\s+/', ' ', (string) ($parameters[$index] ?? ''))) ?: '-';
            $text = str_ireplace('{'.$placeholder.'}', $value, $text);
        }

        // Gelar seperti "S.Pd." sudah diakhiri titik: jangan jadi "S.Pd..".
        return preg_replace('/(?<!\.)\.\.(?!\.)/', '.', $text);
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
