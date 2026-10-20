<?php

namespace App\Actions;

use App\Jobs\SendRiskMessage;
use App\Models\AcademicYear;
use App\Models\GuardianContact;
use App\Models\RiskEpisode;
use App\Models\RiskMessage;
use App\Support\AttendanceRisk;
use App\Support\WhatsAppSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compares today's early-warning list with the open risk episodes and tells
 * parents when it matters: when a student enters the list, and again when
 * they rise to high risk or start a new run of Alpa. A student who leaves the
 * list closes their episode, so coming back later starts a new one.
 *
 * Only marks up to yesterday count, giving wali kelas the day to enter
 * sick and leave letters before anything is sent.
 */
class TrackRiskEpisodes
{
    private int $queuedToday = 0;

    /** Seconds between queued messages, so a batch does not look like spam. */
    public const SECONDS_BETWEEN_MESSAGES = 10;

    private int $queuedThisRun = 0;

    public function __construct(private AttendanceRisk $risk, private WhatsAppSettings $settings) {}

    /**
     * @return array{opened: int, closed: int, notified: int, messages: int}
     */
    public function handle(CarbonInterface $today): array
    {
        $summary = ['opened' => 0, 'closed' => 0, 'notified' => 0, 'messages' => 0];
        $today = CarbonImmutable::parse($today->toDateString());
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return $summary;
        }

        [$from, $semesterTo, $semester] = $academicYear->semesterSoFar($today);

        // Episode dari semester atau tahun ajaran sebelumnya selesai dengan sendirinya.
        $summary['closed'] += RiskEpisode::query()
            ->open()
            ->where(fn (Builder $query) => $query->where('academic_year_id', '!=', $academicYear->id)->orWhere('semester', '!=', $semester))
            ->update(['ended_on' => $today]);

        $to = $semesterTo->min($today->subDay());

        if ($to->lessThan($from)) {
            return $summary;
        }

        $flagged = collect($this->risk->flagged($academicYear->classrooms()->get(), $from, $to))->keyBy('id');
        $open = RiskEpisode::query()->open()->where('academic_year_id', $academicYear->id)->where('semester', $semester)->get()->keyBy('student_id');

        foreach ($open as $studentId => $episode) {
            if (! $flagged->has($studentId)) {
                $episode->update(['ended_on' => $today]);
                $summary['closed']++;
            }
        }

        $this->queuedToday = RiskMessage::query()
            ->whereIn('status', [RiskMessage::STATUS_QUEUED, RiskMessage::STATUS_SENT, RiskMessage::STATUS_FAILED])
            ->whereDate('created_at', $today)
            ->count();

        foreach ($flagged as $studentId => $row) {
            $episode = $open->get($studentId);
            $trigger = null;

            if (! $episode) {
                $episode = RiskEpisode::create([
                    'student_id' => $studentId,
                    'academic_year_id' => $academicYear->id,
                    'semester' => $semester,
                    'started_on' => $today,
                    'level' => $row['level'],
                    'reasons' => $row['reasons'],
                    'streak_ended_on' => $row['streakEndedOn'],
                ]);
                $summary['opened']++;
                $trigger = RiskMessage::TRIGGER_NEW;
            } else {
                $previousStreakEnd = $episode->streak_ended_on?->toDateString();

                if ($this->settings->notifyOnEscalation() && $episode->level === AttendanceRisk::LEVEL_WATCH && $row['level'] === AttendanceRisk::LEVEL_HIGH) {
                    $trigger = RiskMessage::TRIGGER_ESCALATED;
                } elseif ($this->settings->notifyOnNewStreak() && $row['streakEndedOn'] !== null && ($previousStreakEnd === null || $row['streakEndedOn'] > $previousStreakEnd)) {
                    $trigger = RiskMessage::TRIGGER_NEW_STREAK;
                }

                $episode->update([
                    'level' => $episode->level === AttendanceRisk::LEVEL_HIGH ? AttendanceRisk::LEVEL_HIGH : $row['level'],
                    'reasons' => $row['reasons'],
                    'streak_ended_on' => $row['streakEndedOn'] ?? $previousStreakEnd,
                ]);
            }

            if ($trigger) {
                $summary['messages'] += $this->notify($episode, $row, $trigger);
                $summary['notified']++;
            }
        }

        return $summary;
    }

    /**
     * Record one message per parent number that agreed to messages, and queue
     * it unless the sender is in test mode or today's limit is reached.
     *
     * @param  array{level: string, reasons: list<array{level: string, text: string}>}  $row
     */
    private function notify(RiskEpisode $episode, array $row, string $trigger): int
    {
        $reason = implode(' ', array_slice(array_column($row['reasons'], 'text'), 0, 2));
        $base = [
            'risk_episode_id' => $episode->id,
            'student_id' => $episode->student_id,
            'trigger' => $trigger,
            'level' => $row['level'],
            'reason' => mb_substr($reason, 0, 500),
        ];

        $episode->update(['notified_at' => now()]);
        $contacts = GuardianContact::query()->consented()->where('student_id', $episode->student_id)->get();

        if ($contacts->isEmpty()) {
            RiskMessage::create([...$base, 'status' => RiskMessage::STATUS_NO_CONTACT]);

            return 0;
        }

        foreach ($contacts as $contact) {
            $status = match (true) {
                $this->settings->testMode() => RiskMessage::STATUS_TEST,
                $this->queuedToday >= $this->settings->dailyLimit() => RiskMessage::STATUS_LIMITED,
                default => RiskMessage::STATUS_QUEUED,
            };

            $message = RiskMessage::create([...$base, 'guardian_contact_id' => $contact->id, 'phone' => $contact->phone, 'status' => $status]);

            if ($status === RiskMessage::STATUS_QUEUED) {
                $this->queuedToday++;
                SendRiskMessage::dispatch($message)->delay(now()->addSeconds($this->queuedThisRun++ * self::SECONDS_BETWEEN_MESSAGES));
            }
        }

        return $contacts->count();
    }
}
