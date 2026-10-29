<?php

namespace App\Http\Controllers\Recap;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\SendRiskMessage;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\GuardianContact;
use App\Models\RiskEpisode;
use App\Models\RiskMessage;
use App\Models\Student;
use App\Support\AttendanceReport;
use App\Support\AttendanceRisk;
use App\Support\AttendanceWindow;
use App\Support\RecapScope;
use App\Support\WhatsAppClient;
use App\Support\WhatsAppSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EarlyWarningController extends Controller
{
    public function __construct(private WhatsAppSettings $whatsAppSettings) {}

    /**
     * Students whose attendance this semester matches one of the early
     * warning rules. A wali kelas sees their own class only.
     */
    public function index(Request $request, RecapScope $scope, AttendanceRisk $risk, WhatsAppSettings $whatsApp): Response
    {
        $user = $request->user();
        $academicYear = AcademicYear::active();
        $classrooms = $scope->classroomsFor($user);
        $selectedId = $user->hasRole(Role::WaliKelas) ? null : ($request->integer('classroom') ?: null);
        $target = $selectedId ? new EloquentCollection([$scope->classroomFor($user, $selectedId)]) : $classrooms;

        $students = [];
        $period = null;

        if ($academicYear && $target->isNotEmpty()) {
            [$from, $to, $semester] = $academicYear->semesterSoFar(today());
            $students = $this->withParentNotice($risk->flagged($target, $from, $to));

            if ($user->hasRole(Role::Admin, Role::GuruBk)) {
                $students = $this->withMessagePreview($students, $target);
            }
            $period = ['label' => "Semester {$semester} {$academicYear->name}", 'from' => $from->toDateString(), 'to' => $to->toDateString()];
        }

        return Inertia::render('Recap/EarlyWarning', [
            'students' => $students,
            'rules' => $risk->rules(),
            'period' => $period,
            'classrooms' => $user->hasRole(Role::WaliKelas) ? [] : $classrooms->map(fn (Classroom $classroom): array => $classroom->only('id', 'name'))->values(),
            'filters' => ['classroom' => $selectedId],
            'homeroom' => $user->hasRole(Role::WaliKelas) ? $classrooms->first()?->name : null,
            'isHomeroomTeacher' => $user->hasRole(Role::WaliKelas),
            'whatsappEnabled' => $whatsApp->enabled(),
            'canSendWhatsApp' => $user->hasRole(Role::Admin, Role::GuruBk),
            'canFollowUp' => $user->hasRole(Role::Admin, Role::GuruBk),
        ]);
    }

    /**
     * For admins and guru BK: the parent numbers that agreed to messages and
     * the exact text the "Kirim WA ke orang tua" button would send.
     *
     * @param  list<array<string, mixed>>  $students
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return list<array<string, mixed>>
     */
    private function withMessagePreview(array $students, EloquentCollection $classrooms): array
    {
        $classrooms->load('homeroomTeacher:id,name');
        $byId = $classrooms->keyBy('id');
        $contacts = GuardianContact::query()->consented()->whereIn('student_id', array_column($students, 'id'))->get()->groupBy('student_id');
        $models = Student::query()->whereKey(array_column($students, 'id'))->get()->keyBy('id');

        return array_map(function (array $student) use ($byId, $contacts, $models): array {
            $reason = implode(' ', array_slice(array_column($student['reasons'], 'text'), 0, 2));
            $text = $student['level'] === AttendanceRisk::LEVEL_HIGH ? $this->whatsAppSettings->textHigh() : $this->whatsAppSettings->textWatch();

            return [
                ...$student,
                'contacts' => $contacts->get($student['id'], collect())->map(fn (GuardianContact $contact): string => GuardianContact::displayPhone($contact->phone))->values()->all(),
                'messagePreview' => WhatsAppClient::riskText($text, SendRiskMessage::parameters($models[$student['id']], $reason, $byId->get($student['classroomId']))),
            ];
        }, $students);
    }

    /**
     * Add whether, and when, the parents were told about the student's current
     * risk episode, whether it was handled, and whether the student is new on the list.
     *
     * @param  list<array<string, mixed>>  $students
     * @return list<array<string, mixed>>
     */
    private function withParentNotice(array $students): array
    {
        $episodes = RiskEpisode::query()
            ->open()
            ->whereIn('student_id', array_column($students, 'id'))
            ->with(['messages' => fn ($query) => $query->latest('id'), 'handler:id,name'])
            ->get()
            ->keyBy('student_id');

        return array_map(function (array $student) use ($episodes): array {
            $episode = $episodes->get($student['id']);
            $lastBatch = $episode?->notified_at
                ? $episode->messages->filter(fn (RiskMessage $message): bool => $message->created_at->greaterThanOrEqualTo($episode->notified_at->copy()->subMinute()))
                : collect();

            return [
                ...$student,
                // Baru: belum tercatat pemeriksaan harian, atau masuk daftar dalam 7 hari terakhir.
                'isNew' => ! $episode || $episode->started_on->greaterThanOrEqualTo(today()->subDays(7)),
                'notice' => $episode?->notified_at ? [
                    'notifiedAt' => $episode->notified_at->toIso8601String(),
                    'statuses' => $lastBatch->pluck('status')->unique()->values()->all(),
                ] : null,
                'followUp' => $episode?->handled_at ? [
                    'at' => $episode->handled_at->toIso8601String(),
                    'by' => $episode->handler?->name,
                    'note' => $episode->handled_note,
                ] : null,
            ];
        }, $students);
    }

    /**
     * One student's recap for the semester so far, for the modal opened from
     * the list: totals, every school day's mark grouped by month, and notes.
     */
    public function show(Request $request, Student $student, RecapScope $scope, AttendanceWindow $window, AttendanceReport $report): JsonResponse
    {
        $academicYear = AcademicYear::active();
        abort_if($academicYear === null, 404);

        $classroom = $student->classroomIn($academicYear);
        abort_unless($classroom && $scope->canCorrect($request->user(), $classroom), 403, 'Anda tidak punya akses ke siswa ini.');

        [$from, $to, $semester] = $academicYear->semesterSoFar(today());

        $marks = Attendance::query()
            ->where('student_id', $student->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (Attendance $attendance): string => $attendance->date->toDateString());

        $counts = array_fill_keys(AttendanceReport::STATUSES, 0);

        foreach ($marks as $mark) {
            $counts[$mark->status->value]++;
        }

        $months = collect($window->schoolDaysBetween($from, $to))
            ->groupBy(fn (CarbonImmutable $day): string => $day->format('Y-m'))
            ->map(function ($days) use ($marks, $report): array {
                $monthCounts = array_fill_keys(AttendanceReport::STATUSES, 0);
                $cells = $days->map(function (CarbonImmutable $day) use ($marks, &$monthCounts): array {
                    $status = $marks->get($day->toDateString())?->status->value;

                    if ($status) {
                        $monthCounts[$status]++;
                    }

                    return ['date' => $day->toDateString(), 'day' => $day->day, 'weekday' => $day->translatedFormat('D'), 'status' => $status];
                })->values()->all();

                return ['label' => $days->first()->translatedFormat('F Y'), 'days' => $cells, 'counts' => $monthCounts, 'rate' => $report->rate($monthCounts)];
            })
            ->values();

        return response()->json([
            'name' => $student->name,
            'nis' => $student->nis,
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name],
            'period' => "Semester {$semester} {$academicYear->name}",
            'counts' => $counts,
            'rate' => $report->rate($counts),
            'months' => $months,
            'notes' => $marks
                ->filter(fn (Attendance $attendance): bool => $attendance->status->value !== 'H' && filled($attendance->note))
                ->map(fn (Attendance $attendance): array => [
                    'date' => $attendance->date->toDateString(),
                    'status' => $attendance->status->value,
                    'statusLabel' => $attendance->status->label(),
                    'note' => $attendance->note,
                ])
                ->values(),
        ]);
    }
}
