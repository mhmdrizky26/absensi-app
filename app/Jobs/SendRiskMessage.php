<?php

namespace App\Jobs;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\RiskMessage;
use App\Models\Student;
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

        self::deliver($message, $client);
    }

    /**
     * Send the message now and record the result on it. Used by the queue and
     * by the "Kirim WA ke orang tua" button.
     */
    public static function deliver(RiskMessage $message, WhatsAppClient $client): bool
    {
        try {
            $providerId = $client->sendRiskAlert($message->phone, $message->level, self::parameters($message->student, $message->reason));
            $message->update(['status' => RiskMessage::STATUS_SENT, 'provider_message_id' => $providerId, 'sent_at' => now(), 'error' => null]);

            return true;
        } catch (Throwable $exception) {
            $message->update(['status' => RiskMessage::STATUS_FAILED, 'error' => mb_substr($exception->getMessage(), 0, 500)]);

            return false;
        }
    }

    /**
     * The values for the message text: name, class, reason, homeroom teacher, school.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    public static function parameters(Student $student, string $reason, ?Classroom $classroom = null): array
    {
        $academicYear = AcademicYear::active();
        $classroom ??= $academicYear ? $student->classroomIn($academicYear) : null;

        return [
            $student->name,
            $classroom?->name ?? '-',
            $reason,
            $classroom?->homeroomTeacher?->name ?? 'wali kelas',
            (string) config('app.school_name'),
        ];
    }
}
