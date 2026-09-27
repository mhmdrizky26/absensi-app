<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PromoteStudents;
use App\Exports\GraduatesArchiveExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Support\AttendanceReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PromotionController extends Controller
{
    /**
     * The confirmation screen shown when a new academic year is activated.
     */
    public function show(AcademicYear $academicYear, PromoteStudents $promoteStudents): Response|RedirectResponse
    {
        $source = $promoteStudents->sourceFor($academicYear);

        if (! $source) {
            return redirect()->route('academic-years.index')->with('error', "Tahun ajaran {$academicYear->name} tidak memerlukan kenaikan kelas.");
        }

        return Inertia::render('Admin/AcademicYears/Promotion', [
            'source' => $source->only('id', 'name'),
            'target' => $academicYear->only('id', 'name'),
            'classrooms' => $promoteStudents->preview($source),
        ]);
    }

    public function store(Request $request, AcademicYear $academicYear, PromoteStudents $promoteStudents): RedirectResponse
    {
        $validated = $request->validate([
            'repeaters' => ['array'],
            'repeaters.*' => ['integer'],
            'leavers' => ['array'],
            'leavers.*' => ['integer'],
            'confirm_delete' => ['boolean'],
        ]);

        $source = $promoteStudents->sourceFor($academicYear);

        if (! $source) {
            return redirect()->route('academic-years.index')->with('error', "Kenaikan kelas ke {$academicYear->name} sudah pernah dijalankan atau tidak diperlukan.");
        }

        $graduatesToDelete = collect($promoteStudents->preview($source))
            ->where('target', null)
            ->flatMap(fn (array $classroom): array => array_column($classroom['students'], 'id'))
            ->diff([...$validated['repeaters'] ?? [], ...$validated['leavers'] ?? []]);

        if ($graduatesToDelete->isNotEmpty() && ! $request->boolean('confirm_delete')) {
            throw ValidationException::withMessages([
                'confirm_delete' => 'Centang pernyataan bahwa data siswa kelas IX akan dihapus permanen.',
            ]);
        }

        $result = $promoteStudents->handle($source, $academicYear, $validated['repeaters'] ?? [], $validated['leavers'] ?? []);

        return redirect()->route('classrooms.index')->with('success', sprintf(
            'Tahun ajaran %s aktif. %d kelas dibuat, %d siswa naik kelas, %d tinggal kelas, %d pindah, %d siswa kelas IX dihapus. Tentukan wali kelas, lalu import siswa kelas VII baru.',
            $academicYear->name,
            $result['classrooms'],
            $result['promoted'],
            $result['repeated'],
            $result['left'],
            $result['graduated'],
        ));
    }

    /**
     * Excel archive of the grade IX students' attendance for the whole
     * source year, to download before they are deleted.
     */
    public function archive(AcademicYear $academicYear, PromoteStudents $promoteStudents, AttendanceReport $report): BinaryFileResponse
    {
        $source = $promoteStudents->sourceFor($academicYear);
        abort_if($source === null, 404);

        $graduates = [];

        foreach ($source->classrooms()->where('grade', 9)->orderBy('name')->get() as $classroom) {
            foreach ($report->studentTotals($classroom, $source->odd_semester_starts_on, $source->ends_on) as $row) {
                if ($row['isActive']) {
                    $graduates[] = ['classroom' => $classroom->name, 'row' => $row];
                }
            }
        }

        return Excel::download(
            new GraduatesArchiveExport($source, $graduates, config('app.school_name')),
            'arsip-kelas-ix-'.str($source->name)->replace('/', '-').'.xlsx',
        );
    }
}
