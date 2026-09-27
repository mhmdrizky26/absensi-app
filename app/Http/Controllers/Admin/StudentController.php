<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
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
            ->with(['classrooms' => fn (BelongsToMany $query) => $query->wherePivot('academic_year_id', $academicYearId)])
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
            ]);

        return Inertia::render('Admin/Students/Index', [
            'academicYear' => $academicYear?->only('id', 'name'),
            'students' => $students,
            'filters' => $filters,
            'classrooms' => $academicYear
                ? $academicYear->classrooms()->orderBy('grade')->orderBy('name')->get(['id', 'name'])
                : [],
            'genders' => collect(Gender::cases())->map(fn (Gender $gender): array => ['value' => $gender->value, 'label' => $gender->label()]),
            'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = DB::transaction(function () use ($request): Student {
            $student = Student::create($request->safe()->except('classroom_id'));
            $this->place($student, $request->validated('classroom_id'));

            return $student;
        });

        return back()->with('success', "{$student->name} ditambahkan.");
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student): void {
            $student->update($request->safe()->except('classroom_id'));
            $this->place($student, $request->validated('classroom_id'));
        });

        return back()->with('success', "Data {$student->name} diperbarui.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return back()->with('success', "{$student->name} dihapus.");
    }

    /**
     * Put the student in the given classroom of the active academic year.
     */
    private function place(Student $student, ?int $classroomId): void
    {
        $academicYear = AcademicYear::active();

        if ($academicYear) {
            $student->placeIn($classroomId ? Classroom::find($classroomId) : null, $academicYear);
        }
    }
}
