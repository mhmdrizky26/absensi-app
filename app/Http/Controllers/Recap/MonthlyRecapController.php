<?php

namespace App\Http\Controllers\Recap;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\GuardianContact;
use App\Models\Student;
use App\Support\AttendanceReport;
use App\Support\RecapScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyRecapController extends Controller
{
    /**
     * One class's marks for a month: students × school days.
     */
    public function index(Request $request, RecapScope $scope, AttendanceReport $report): Response
    {
        $user = $request->user();
        $classroom = $scope->classroomFor($user, $request->filled('classroom') ? $request->integer('classroom') : null);
        $month = self::parseMonth($request->string('bulan')->value());

        return Inertia::render('Recap/Monthly', [
            'classrooms' => $scope->classroomsFor($user)->map->only('id', 'name')->values(),
            'classroom' => $classroom ? [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'homeroomTeacher' => $classroom->homeroomTeacher?->name,
            ] : null,
            'month' => $month->format('Y-m'),
            'today' => today()->toDateString(),
            'grid' => $classroom ? $report->monthGrid($classroom, $month) : null,
            'parentContacts' => $classroom && $user->hasRole(Role::WaliKelas) ? $this->parentContacts($classroom) : null,
            'relations' => collect(GuardianContact::RELATIONS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    /**
     * Active students of the class with their parents' WhatsApp numbers, for the
     * wali kelas's "Nomor orang tua" dialog.
     *
     * @return list<array{id: int, name: string, nis: string, contacts: list<string>}>
     */
    private function parentContacts(Classroom $classroom): array
    {
        return $classroom->students()
            ->where('status', StudentStatus::Active)
            ->with('guardianContacts')
            ->orderBy('name')
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'contacts' => $student->guardianContacts->map(fn (GuardianContact $contact): string => GuardianContact::displayPhone($contact->phone))->values()->all(),
            ])
            ->all();
    }

    /**
     * "2026-09" → first day of that month; anything else → this month.
     */
    public static function parseMonth(?string $value): CarbonImmutable
    {
        return $value && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)
            ? CarbonImmutable::parse("{$value}-01")
            : CarbonImmutable::today()->startOfMonth();
    }
}
