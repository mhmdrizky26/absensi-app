<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        $academicYears = AcademicYear::query()
            ->withCount('classrooms')
            ->orderByDesc('name')
            ->get()
            ->map(fn (AcademicYear $academicYear): array => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'oddSemesterStartsOn' => $academicYear->odd_semester_starts_on->toDateString(),
                'evenSemesterStartsOn' => $academicYear->even_semester_starts_on->toDateString(),
                'endsOn' => $academicYear->ends_on->toDateString(),
                'isActive' => $academicYear->is_active,
                'classroomsCount' => $academicYear->classrooms_count,
            ]);

        return Inertia::render('Admin/AcademicYears/Index', [
            'academicYears' => $academicYears,
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $academicYear = AcademicYear::create($request->validated());

        if (! AcademicYear::query()->where('is_active', true)->exists()) {
            $academicYear->activate();
        }

        return back()->with('success', "Tahun ajaran {$academicYear->name} ditambahkan.");
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->validated());

        return back()->with('success', "Tahun ajaran {$academicYear->name} diperbarui.");
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active) {
            return back()->with('error', 'Tahun ajaran yang sedang aktif tidak bisa dihapus.');
        }

        if ($academicYear->classrooms()->exists()) {
            return back()->with('error', "Tahun ajaran {$academicYear->name} masih memiliki kelas, jadi tidak bisa dihapus.");
        }

        $academicYear->delete();

        return back()->with('success', "Tahun ajaran {$academicYear->name} dihapus.");
    }
}
