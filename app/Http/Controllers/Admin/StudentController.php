<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GuardianContact;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $academicYear = AcademicYear::active();
        $academicYearId = $academicYear?->id;

        $filters = [
            'q' => $request->string('q')->trim()->value(),
            'classroom' => $request->string('classroom')->value(),
            'status' => $request->string('status', StudentStatus::Active->value)->value(),
        ];

        $students = Student::query()
            ->with(['classrooms' => fn (BelongsToMany $query) => $query->wherePivot('academic_year_id', $academicYearId), 'guardianContacts'])
            ->search($filters['q'])
            ->when($filters['status'] !== 'semua', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['classroom'] === 'tanpa-kelas', fn (Builder $query) => $query->whereDoesntHave(
                'classrooms',
                fn (Builder $query) => $query->where('classroom_student.academic_year_id', $academicYearId),
            ))
            ->when(ctype_digit($filters['classroom']), fn (Builder $query) => $query->whereHas(
                'classrooms',
                fn (Builder $query) => $query->whereKey((int) $filters['classroom']),
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Student $student): array => [
                'id' => $student->id,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'name' => $student->name,
                'gender' => $student->gender->value,
                'status' => $student->status->value,
                'statusLabel' => $student->status->label(),
                'classroom' => $student->classrooms->first()?->only('id', 'name'),
                'studyClassroomId' => $student->classrooms->first()?->pivot->study_classroom_id,
                'guardians' => $student->guardianContacts->map(fn (GuardianContact $contact): array => [
                    'name' => $contact->name ?? '',
                    'relation' => $contact->relation ?? '',
                    'phone' => GuardianContact::displayPhone($contact->phone),
                    'consented' => $contact->consented_at !== null,
                ])->values(),
            ]);

        return Inertia::render('Admin/Students/Index', [
            'academicYear' => $academicYear?->only('id', 'name'),
            'students' => $students,
            'filters' => $filters,
            'classrooms' => $academicYear
                ? $academicYear->classrooms()->orderBy('grade')->orderBy('name')->get(['id', 'name', 'has_room'])
                : [],
            'genders' => collect(Gender::cases())->map(fn (Gender $gender): array => ['value' => $gender->value, 'label' => $gender->label()]),
            'relations' => collect(GuardianContact::RELATIONS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = DB::transaction(function () use ($request): Student {
            $student = Student::create($request->safe()->except('classroom_id', 'study_classroom_id', 'guardians'));
            $this->place($student, $request->validated('classroom_id'), $request->validated('study_classroom_id'));
            $this->syncGuardians($student, $request->validated('guardians', []));

            return $student;
        });

        return back()->with('success', "{$student->name} ditambahkan.");
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student): void {
            $student->update($request->safe()->except('classroom_id', 'study_classroom_id', 'guardians'));
            $this->place($student, $request->validated('classroom_id'), $request->validated('study_classroom_id'));
            $this->syncGuardians($student, $request->validated('guardians', []));
        });

        return back()->with('success', "Data {$student->name} diperbarui.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return back()->with('success', "{$student->name} dihapus.");
    }

    /**
     * Replace the student's parent contacts with the ones in the form. A
     * number that stays consented keeps its original consent date.
     *
     * @param  list<array{name?: ?string, relation?: ?string, phone: string, consented?: bool}>  $guardians
     */
    private function syncGuardians(Student $student, array $guardians): void
    {
        $existing = $student->guardianContacts()->get()->keyBy('phone');
        $student->guardianContacts()->whereNotIn('phone', array_column($guardians, 'phone'))->delete();

        foreach ($guardians as $guardian) {
            $consented = (bool) ($guardian['consented'] ?? false);

            $student->guardianContacts()->updateOrCreate(['phone' => $guardian['phone']], [
                'name' => $guardian['name'] ?? null,
                'relation' => ($guardian['relation'] ?? null) ?: null,
                'consented_at' => $consented ? ($existing->get($guardian['phone'])?->consented_at ?? now()) : null,
            ]);
        }
    }

    /**
     * Put the student in the given classroom of the active academic year. A
     * student of a class without a room also gets the room they sit in.
     */
    private function place(Student $student, ?int $classroomId, ?int $studyClassroomId = null): void
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return;
        }

        $classroom = $classroomId ? Classroom::find($classroomId) : null;
        $student->placeIn($classroom, $academicYear);

        if ($classroom && ! $classroom->has_room && $studyClassroomId && $studyClassroomId !== $classroom->id) {
            $student->classrooms()->updateExistingPivot($classroom->id, ['study_classroom_id' => $studyClassroomId]);
        }
    }
}
