<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;

class ClassroomAccessCodeController extends Controller
{
    /**
     * Replace the classroom's access code. The old code stops working at once.
     */
    public function update(Classroom $classroom): RedirectResponse
    {
        $classroom->regenerateAccessCode();

        return back()->with('success', "Kode kelas {$classroom->name} sudah diganti. Kode lama tidak berlaku lagi.");
    }
}
