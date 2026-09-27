<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassroomBatchRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ClassroomBatchController extends Controller
{
    /**
     * Create sections A, B, C, … for each chosen grade in the active academic
     * year. Classes that already exist are left untouched.
     */
    public function store(ClassroomBatchRequest $request): RedirectResponse
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return back()->with('error', 'Aktifkan tahun ajaran terlebih dahulu.');
        }

        $existingNames = $academicYear->classrooms()->pluck('name')->all();
        $sections = array_slice(range('A', 'Z'), 0, $request->integer('count'));
        $created = 0;

        DB::transaction(function () use ($request, $academicYear, $existingNames, $sections, &$created): void {
            foreach ($request->array('grades') as $grade) {
                foreach ($sections as $section) {
                    $name = Classroom::nameFor((int) $grade, $section);

                    if (in_array($name, $existingNames, true)) {
                        continue;
                    }

                    $academicYear->classrooms()->create(['grade' => $grade, 'name' => $name]);
                    $created++;
                }
            }
        });

        return back()->with('success', $created > 0
            ? "{$created} kelas baru dibuat."
            : 'Semua kelas tersebut sudah ada, tidak ada kelas baru.');
    }
}
