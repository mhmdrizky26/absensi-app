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
    public const BROWSE_LIMIT = 50;

    public const SEARCH_LIMIT = 20;

    /**
     * Find active students by name, NIS or NISN for staff forms; with an empty
     * query, list them so the form shows students right away. A wali kelas
     * only finds students of their own class. With `date`, students already
     * excused or dispensed on that day are left out and counted as `hidden`.
     */
    public function index(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return response()->json(['students' => [], 'hidden' => 0, 'total' => 0]);
        }

        $user = $request->user();
        $homeroomId = $user->hasRole(Role::WaliKelas) ? ($user->currentHomeroom()?->id ?? 0) : null;

        $matching = Student::query()
            ->where('status', StudentStatus::Active)
            ->when($term !== '', fn (Builder $query) => $query->search($term))
            ->whereHas('classrooms', fn (Builder $query) => $query
                ->where('classrooms.academic_year_id', $academicYear->id)
                ->when($homeroomId !== null, fn (Builder $query) => $query->whereKey($homeroomId)));

        $date = $request->date('date');
        $available = (clone $matching)->when($date, fn (Builder $query) => $query->notExcusedOn($date));

        $total = (clone $available)->count();

        $students = $available
            ->with(['classrooms' => fn (BelongsToMany $query) => $query->wherePivot('academic_year_id', $academicYear->id)])
            ->orderBy('name')
            ->limit($term === '' ? self::BROWSE_LIMIT : self::SEARCH_LIMIT)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'classroom' => $student->classrooms->first()?->name,
            ]);

        return response()->json([
            'students' => $students,
            'total' => $total,
            'hidden' => $date ? $matching->count() - $total : 0,
        ]);
    }
}
