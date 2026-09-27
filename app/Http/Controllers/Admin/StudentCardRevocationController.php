<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;

class StudentCardRevocationController extends Controller
{
    /**
     * Block the student's current card.
     */
    public function store(Student $student): RedirectResponse
    {
        $student->revokeCard();

        return back()->with('success', "Kartu {$student->name} dicabut dan tidak bisa dipakai absen.");
    }

    /**
     * Unblock the student's current card.
     */
    public function destroy(Student $student): RedirectResponse
    {
        $student->restoreCard();

        return back()->with('success', "Kartu {$student->name} aktif kembali.");
    }
}
