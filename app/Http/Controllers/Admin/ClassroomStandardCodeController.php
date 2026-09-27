<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ClassroomStandardCodeController extends Controller
{
    /**
     * Give every class of the active year its standard code (ESSAR7A, …).
     */
    public function store(): RedirectResponse
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return back()->with('error', 'Aktifkan tahun ajaran terlebih dahulu.');
        }

        $classrooms = $academicYear->classrooms()->get();

        DB::transaction(function () use ($classrooms): void {
            // Kosongkan dulu dengan kode sementara supaya tidak bentrok dengan kode kelas lain di tengah proses.
            foreach ($classrooms as $classroom) {
                $classroom->forceFill(['access_code' => 'TMP'.$classroom->id])->save();
            }

            foreach ($classrooms as $classroom) {
                $classroom->useStandardAccessCode();
            }
        });

        return back()->with('success', "{$classrooms->count()} kelas sekarang memakai kode standar, misalnya {$classrooms->first()?->access_code}.");
    }
}
