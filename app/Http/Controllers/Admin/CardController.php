<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    /**
     * Cards of the active students in one classroom of the active year.
     */
    public function index(Request $request): Response
    {
        $academicYear = AcademicYear::active();
        $classrooms = $academicYear?->classrooms()->orderBy('grade')->orderBy('name')->get(['id', 'name']) ?? collect();

        /** @var Classroom|null $classroom */
        $classroom = $classrooms->firstWhere('id', $request->integer('classroom')) ?? $classrooms->first();

        $students = $classroom
            ? $classroom->students()
                ->where('status', StudentStatus::Active)
                ->orderBy('name')
                ->get()
                ->map(fn (Student $student): array => $student->toCard())
            : collect();

        return Inertia::render('Admin/Cards/Index', [
            'academicYear' => $academicYear?->only('id', 'name'),
            'classrooms' => $classrooms,
            'classroom' => $classroom?->only('id', 'name'),
            'students' => $students,
        ]);
    }
}
