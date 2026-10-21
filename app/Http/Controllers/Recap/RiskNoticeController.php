<?php

namespace App\Http\Controllers\Recap;

use App\Http\Controllers\Controller;
use App\Jobs\SendRiskMessage;
use App\Models\AcademicYear;
use App\Models\GuardianContact;
use App\Models\RiskEpisode;
use App\Models\RiskMessage;
use App\Models\Student;
use App\Support\AttendanceRisk;
use App\Support\WhatsAppClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;

class RiskNoticeController extends Controller
{
    /**
     * Send the risk message to the student's parents right away, at an admin's
     * or guru BK's request. It is logged like the automatic ones and marks the
     * current risk episode as notified.
     */
    public function store(Student $student, AttendanceRisk $risk, WhatsAppClient $client): RedirectResponse
    {
        $academicYear = AcademicYear::active();
        $classroom = $academicYear ? $student->classroomIn($academicYear)?->load('homeroomTeacher') : null;
        abort_if($classroom === null, 404);

        [$from, $to, $semester] = $academicYear->semesterSoFar(today());
        $row = collect($risk->flagged(new EloquentCollection([$classroom]), $from, $to))->firstWhere('id', $student->id);

        if (! $row) {
            return back()->with('error', "{$student->name} sedang tidak masuk daftar siswa berisiko.");
        }

        $contacts = GuardianContact::query()->consented()->where('student_id', $student->id)->get();

        if ($contacts->isEmpty()) {
            return back()->with('error', "Belum ada nomor WA orang tua {$student->name} yang bersedia dihubungi. Tambahkan di menu Siswa.");
        }

        $episode = RiskEpisode::query()->open()->where('student_id', $student->id)->where('academic_year_id', $academicYear->id)->where('semester', $semester)->first()
            ?? RiskEpisode::create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'semester' => $semester,
                'started_on' => today(),
                'level' => $row['level'],
                'reasons' => $row['reasons'],
                'streak_ended_on' => $row['streakEndedOn'],
            ]);

        $reason = mb_substr(implode(' ', array_slice(array_column($row['reasons'], 'text'), 0, 2)), 0, 500);
        $sent = 0;
        $errors = [];

        foreach ($contacts as $contact) {
            $message = RiskMessage::create([
                'risk_episode_id' => $episode->id,
                'student_id' => $student->id,
                'guardian_contact_id' => $contact->id,
                'phone' => $contact->phone,
                'trigger' => RiskMessage::TRIGGER_MANUAL,
                'level' => $row['level'],
                'reason' => $reason,
                'status' => RiskMessage::STATUS_QUEUED,
            ]);
            $message->setRelation('student', $student);

            if (SendRiskMessage::deliver($message, $client)) {
                $sent++;
            } else {
                $errors[] = GuardianContact::displayPhone($contact->phone).': '.$message->error;
            }
        }

        $episode->update(['notified_at' => now(), 'level' => $episode->level === AttendanceRisk::LEVEL_HIGH ? AttendanceRisk::LEVEL_HIGH : $row['level'], 'reasons' => $row['reasons']]);

        if ($errors !== []) {
            return back()->with('error', "Pesan untuk orang tua {$student->name}: {$sent} terkirim, ".count($errors).' gagal. '.implode(' · ', $errors));
        }

        return back()->with('success', "Pesan risiko terkirim ke {$sent} nomor orang tua {$student->name}.");
    }
}
