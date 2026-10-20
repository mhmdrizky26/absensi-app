<?php

namespace App\Jobs;

use App\Models\AcademicYear;
use App\Models\RiskMessage;
use App\Support\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one queued risk message to a parent over WhatsApp. A failure is kept
 * on the message so the admin can see why and send it again.
 */
class SendRiskMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public RiskMessage $message) {}

    public function handle(WhatsAppClient $client): void
    {
        $message = $this->message->fresh(['student']);

        if (! $message || $message->status !== RiskMessage::STATUS_QUEUED) {
            return;
        }

        $academicYear = AcademicYear::active();
        $classroom = $academicYear ? $message->student->classroomIn($academicYear)?->load('homeroomTeacher') : null;
        try {
            $providerId = $client->sendRiskAlert($message->phone, $message->level, [
                $message->student->name,
                $classroom?->name ?? '-',
                $message->reason,
                $classroom?->homeroomTeacher?->name ?? 'wali kelas',
                config('app.school_name'),
            ]);

            $message->update(['status' => RiskMessage::STATUS_SENT, 'provider_message_id' => $providerId, 'sent_at' => now(), 'error' => null]);
        } catch (Throwable $exception) {
            $message->update(['status' => RiskMessage::STATUS_FAILED, 'error' => mb_substr($exception->getMessage(), 0, 500)]);
        }
    }
}
