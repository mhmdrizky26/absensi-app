<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DutySubstitutionRequest;
use App\Models\DutySubstitution;
use Illuminate\Http\RedirectResponse;

class DutySubstitutionController extends Controller
{
    /**
     * On one date, let another guru do the duty of a scheduled teacher.
     */
    public function store(DutySubstitutionRequest $request): RedirectResponse
    {
        $substitution = DutySubstitution::query()->create($request->validated());
        $substitution->load(['absentUser:id,name', 'substituteUser:id,name']);

        return back()->with('success', "{$substitution->substituteUser->name} menggantikan piket {$substitution->absentUser->name} pada {$substitution->date->translatedFormat('l, j F Y')}.");
    }

    public function destroy(DutySubstitution $dutySubstitution): RedirectResponse
    {
        $dutySubstitution->delete();

        return back()->with('success', 'Pengganti piket dihapus; jadwal tetap berlaku lagi untuk tanggal itu.');
    }
}
