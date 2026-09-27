<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;

class StudentCardController extends Controller
{
    /**
     * Issue a replacement card. The previous card stops working immediately.
     */
    public function store(Student $student): RedirectResponse
    {
        $student->issueNewCard();

        return back()->with('success', "Kartu baru (v{$student->qr_version}) untuk {$student->name} sudah dibuat. Kartu lama tidak berlaku lagi, cetak kartu barunya.");
    }
}
