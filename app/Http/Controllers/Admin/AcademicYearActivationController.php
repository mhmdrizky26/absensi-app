<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PromoteStudents;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcademicYearActivationController extends Controller
{
    /**
     * Make the academic year the one the whole application works in. A new
     * year that follows the active one first goes through the promotion
     * screen, unless the admin explicitly skips it.
     */
    public function store(Request $request, AcademicYear $academicYear, PromoteStudents $promoteStudents): RedirectResponse
    {
        if (! $request->boolean('without_promotion') && $promoteStudents->sourceFor($academicYear)) {
            return redirect()->route('academic-years.promotion', $academicYear);
        }

        $academicYear->activate();

        return redirect()->route('academic-years.index')->with('success', "Tahun ajaran {$academicYear->name} sekarang aktif.");
    }
}
