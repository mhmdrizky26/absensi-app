<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ClassroomLodgingController extends Controller
{
    /**
     * Spread the active students of a class without a room evenly over the
     * other classes of the same grade that have one. Done once a year; running
     * it again redistributes everyone.
     */
    public function store(Classroom $classroom): RedirectResponse
    {
        if ($classroom->has_room) {
            return back()->with('error', "Kelas {$classroom->name} punya ruang sendiri, jadi siswanya tidak perlu dititipkan.");
        }

        $hosts = Classroom::query()
            ->where('academic_year_id', $classroom->academic_year_id)
            ->where('grade', $classroom->grade)
            ->where('has_room', true)
            ->whereKeyNot($classroom->id)
            ->orderBy('name')
            ->get();

        if ($hosts->isEmpty()) {
            return back()->with('error', "Tidak ada kelas {$classroom->name} lain yang punya ruang untuk dititipi.");
        }

        $studentIds = $classroom->students()->where('status', StudentStatus::Active)->orderBy('name')->pluck('students.id');

        // Mulai dari kelas yang paling sedikit siswanya (termasuk titipan dari kelas lain).
        $load = $hosts->mapWithKeys(fn (Classroom $host): array => [
            $host->id => DB::table('classroom_student')
                ->where('academic_year_id', $classroom->academic_year_id)
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->where('classroom_id', $host->id)->whereNull('study_classroom_id'))
                    ->orWhere(fn ($query) => $query->where('study_classroom_id', $host->id)->where('classroom_id', '!=', $classroom->id)))
                ->count(),
        ])->all();

        DB::transaction(function () use ($classroom, $studentIds, &$load): void {
            foreach ($studentIds as $studentId) {
                $hostId = array_keys($load, min($load))[0];
                $load[$hostId]++;

                DB::table('classroom_student')
                    ->where('academic_year_id', $classroom->academic_year_id)
                    ->where('classroom_id', $classroom->id)
                    ->where('student_id', $studentId)
                    ->update(['study_classroom_id' => $hostId]);
            }
        });

        return back()->with('success', "{$studentIds->count()} siswa {$classroom->name} dititipkan ke {$hosts->count()} kelas. Absen pagi mereka di kelas titipan, tetapi tetap tercatat untuk {$classroom->name}.");
    }
}
