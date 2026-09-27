<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(): Response
    {
        $academicYear = AcademicYear::active();

        $classrooms = $academicYear
            ? $academicYear->classrooms()
                ->with('homeroomTeacher:id,name')
                ->withCount('students')
                ->orderBy('grade')
                ->orderBy('name')
                ->get()
                ->map(fn (Classroom $classroom): array => [
                    'id' => $classroom->id,
                    'grade' => $classroom->grade,
                    'name' => $classroom->name,
                    'section' => Str::after($classroom->name, '-'),
                    'accessCode' => $classroom->access_code,
                    'studentsCount' => $classroom->students_count,
                    'homeroomTeacher' => $classroom->homeroomTeacher?->only('id', 'name'),
                ])
            : collect();

        $teachers = User::query()
            ->where('role', Role::WaliKelas)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $teacher): array => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'classroomId' => data_get($classrooms->firstWhere('homeroomTeacher.id', $teacher->id), 'id'),
            ]);

        return Inertia::render('Admin/Classrooms/Index', [
            'academicYear' => $academicYear?->only('id', 'name'),
            'classrooms' => $classrooms,
            'teachers' => $teachers,
            'codePrefix' => config('attendance.class_code_prefix'),
            'grades' => collect(Classroom::GRADES)->map(fn (string $label, int $grade): array => ['value' => $grade, 'label' => $label])->values(),
        ]);
    }

    public function store(ClassroomRequest $request): RedirectResponse
    {
        $academicYearId = $request->academicYearId();

        if (! $academicYearId) {
            return back()->with('error', 'Aktifkan tahun ajaran terlebih dahulu.');
        }

        $classroom = new Classroom([
            ...$request->safe()->only(['grade', 'name', 'homeroom_teacher_id']),
            'academic_year_id' => $academicYearId,
        ]);
        // Kode kosong berarti dibuat otomatis (lihat Classroom::booted()).
        $classroom->access_code = $request->validated('access_code');
        $classroom->save();

        return back()->with('success', "Kelas {$classroom->name} ditambahkan.");
    }

    public function update(ClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $classroom->fill($request->safe()->only(['grade', 'name', 'homeroom_teacher_id']));

        if ($request->validated('access_code')) {
            $classroom->access_code = $request->validated('access_code');
        }

        $classroom->save();

        return back()->with('success', "Kelas {$classroom->name} diperbarui.");
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        if ($classroom->students()->exists()) {
            return back()->with('error', "Kelas {$classroom->name} masih berisi siswa. Pindahkan siswanya dulu sebelum menghapus.");
        }

        $classroom->delete();

        return back()->with('success', "Kelas {$classroom->name} dihapus.");
    }
}
