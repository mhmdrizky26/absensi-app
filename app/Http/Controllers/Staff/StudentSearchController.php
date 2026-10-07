<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentSearchController extends Controller
{
    /**
     * Find active students by name, NIS or NISN for staff forms. A wali kelas
     * only finds students of their own class. With `date`, students already
     * excused or dispensed on that day are left out and counted as `hidden`.
     */
    public function index(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();
        $academicYear = AcademicYear::active();

        if (mb_strlen($term) < 2 || ! $academicYear) {
            return response()->json(['students' => []]);
        }

        $user = $request->user();
        $homeroomId = $user->hasRole(Role::WaliKelas) ? ($user->currentHomeroom()?->id ?? 0) : null;

        $matching = Student::query()
            ->where('status', StudentStatus::Active)
            ->search($term)
            ->whereHas('classrooms', fn (Builder $query) => $query
                ->where('classrooms.academic_year_id', $academicYear->id)
                ->when($homeroomId !== null, fn (Builder $query) => $query->whereKey($homeroomId)));

        $date = $request->date('date');
        $available = (clone $matching)->when($date, fn (Builder $query) => $query->notExcusedOn($date));

        $students = $available
            ->with(['classrooms' => fn (BelongsToMany $query) => $query->wherePivot('academic_year_id', $academicYear->id)])
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'classroom' => $student->classrooms->first()?->name,
            ]);

        return response()->json([
            'students' => $students,
            'hidden' => $date ? $matching->count() - (clone $available)->count() : 0,
        ]);
    }
}
