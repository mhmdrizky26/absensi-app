<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TrackRiskEpisodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WhatsAppSettingRequest;
use App\Http\Requests\Admin\WhatsAppTextRequest;
use App\Jobs\SendRiskMessage;
use App\Models\AcademicYear;
use App\Models\GuardianContact;
use App\Models\RiskMessage;
use App\Models\Setting;
use App\Support\WhatsAppClient;
use App\Support\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WhatsAppController extends Controller
{
    /**
     * Settings, connection test and the log of messages to parents.
     */
    public function index(Request $request, WhatsAppSettings $settings, WhatsAppClient $client): Response
    {
        $status = $request->string('status')->value();
        $academicYear = AcademicYear::active();

        $messages = RiskMessage::query()
            ->with('student:id,name,nis')
            ->when(array_key_exists($status, RiskMessage::STATUS_LABELS), fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (RiskMessage $message): array => [
                'id' => $message->id,
                'createdAt' => $message->created_at->toIso8601String(),
                'sentAt' => $message->sent_at?->toIso8601String(),
                'student' => $message->student->name,
                'nis' => $message->student->nis,
                'classroom' => $academicYear ? $message->student->classroomIn($academicYear)?->name : null,
                'trigger' => RiskMessage::TRIGGER_LABELS[$message->trigger] ?? $message->trigger,
                'level' => $message->level,
                'reason' => $message->reason,
                'phone' => $message->phone ? GuardianContact::displayPhone($message->phone) : null,
                'status' => $message->status,
                'statusLabel' => RiskMessage::STATUS_LABELS[$message->status] ?? $message->status,
                'error' => $message->error,
                'canResend' => $message->phone !== null && in_array($message->status, [RiskMessage::STATUS_FAILED, RiskMessage::STATUS_LIMITED, RiskMessage::STATUS_TEST], true),
            ]);

        return Inertia::render('Admin/WhatsApp', [
            'settings' => $settings->toArray(),
            'texts' => $settings->texts(),
            'defaultTexts' => ['high' => WhatsAppClient::DEFAULT_TEXT_HIGH, 'watch' => WhatsAppClient::DEFAULT_TEXT_WATCH, 'test' => WhatsAppClient::DEFAULT_TEXT_TEST],
            'placeholders' => WhatsAppClient::PLACEHOLDERS,
            'schoolName' => config('app.school_name'),
            'lastRunOn' => $settings->lastRunOn(),
            'connection' => [
                'configured' => $client->isConfigured(),
            ],
            'stats' => [
                'contacts' => GuardianContact::query()->consented()->count(),
                'studentsWithContact' => GuardianContact::query()->consented()->distinct()->count('student_id'),
                'sent' => RiskMessage::query()->where('status', RiskMessage::STATUS_SENT)->count(),
                'failed' => RiskMessage::query()->where('status', RiskMessage::STATUS_FAILED)->count(),
            ],
            'messages' => $messages,
            'filters' => ['status' => $status],
            'statuses' => collect(RiskMessage::STATUS_LABELS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function update(WhatsAppSettingRequest $request): RedirectResponse
    {
        $flag = fn (string $key): string => $request->boolean($key) ? '1' : '0';

        Setting::put([
            WhatsAppSettings::ENABLED => $flag('enabled'),
            WhatsAppSettings::TEST_MODE => $flag('test_mode'),
            WhatsAppSettings::SEND_AT => $request->validated('send_at'),
            WhatsAppSettings::DAILY_LIMIT => (string) $request->integer('daily_limit'),
            WhatsAppSettings::ON_ESCALATION => $flag('on_escalation'),
            WhatsAppSettings::ON_NEW_STREAK => $flag('on_new_streak'),
        ]);

        return back()->with('success', 'Pengaturan notifikasi WhatsApp disimpan.');
    }

    /**
     * Save the message texts. A text equal to the default is stored empty so
     * later changes to the default still apply.
     */
    public function updateTexts(WhatsAppTextRequest $request): RedirectResponse
    {
        $defaults = [WhatsAppSettings::TEXT_HIGH => WhatsAppClient::DEFAULT_TEXT_HIGH, WhatsAppSettings::TEXT_WATCH => WhatsAppClient::DEFAULT_TEXT_WATCH, WhatsAppSettings::TEXT_TEST => WhatsAppClient::DEFAULT_TEXT_TEST];
        $values = [
            WhatsAppSettings::TEXT_HIGH => trim($request->validated('high')),
            WhatsAppSettings::TEXT_WATCH => trim($request->validated('watch')),
            WhatsAppSettings::TEXT_TEST => trim($request->validated('test')),
        ];

        Setting::put(collect($values)->map(fn (string $value, string $key): ?string => $value === $defaults[$key] ? null : $value)->all());

        return back()->with('success', 'Isi pesan WhatsApp disimpan.');
    }

    /**
     * Send a test message to one number to check the connection.
     */
    public function test(Request $request, WhatsAppClient $client): RedirectResponse
    {
        $request->merge(['phone' => GuardianContact::normalizePhone($request->string('phone')->value()) ?? $request->string('phone')->value()]);
        $request->validate(['phone' => ['required', 'regex:/^628\d{7,11}$/']], ['phone.regex' => 'Nomor WA tidak valid. Contoh: 0812 3456 7890.'], ['phone' => 'Nomor WA']);

        try {
            $client->sendTest($request->string('phone')->value());
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pesan uji terkirim ke '.GuardianContact::displayPhone($request->string('phone')->value()).'. Cek WhatsApp di nomor itu.');
    }

    /**
     * Run today's check right away instead of waiting for the scheduled time.
     */
    public function run(TrackRiskEpisodes $trackRiskEpisodes, WhatsAppSettings $settings): RedirectResponse
    {
        $summary = $trackRiskEpisodes->handle(today());
        $settings->markRun(today());

        return back()->with('success', sprintf(
            'Pemeriksaan selesai: %d siswa baru berisiko, %d keluar dari daftar, %d siswa dikabari (%d pesan)%s.',
            $summary['opened'],
            $summary['closed'],
            $summary['notified'],
            $summary['messages'],
            $settings->testMode() ? ' — mode uji, pesan hanya dicatat' : '',
        ));
    }

    public function resend(RiskMessage $riskMessage): RedirectResponse
    {
        abort_if($riskMessage->phone === null, 422);

        $riskMessage->update(['status' => RiskMessage::STATUS_QUEUED, 'error' => null]);
        SendRiskMessage::dispatch($riskMessage);

        return back()->with('success', 'Pesan dimasukkan ke antrean untuk dikirim ulang.');
    }
}
