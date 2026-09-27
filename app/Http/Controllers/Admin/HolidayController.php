<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * Add a holiday, or a run of holidays such as a semester break. Dates that
     * are already holidays are kept as they are.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'description' => ['required', 'string', 'max:150'],
        ], attributes: ['from' => 'Tanggal', 'to' => 'Sampai tanggal', 'description' => 'Keterangan']);

        $to = $request->date('to') ?? $request->date('from');

        if ($request->date('from')->diffInDays($to) > 60) {
            return back()->withErrors(['to' => 'Rentang libur paling lama 60 hari.']);
        }

        $added = 0;

        foreach (CarbonPeriod::create($request->date('from'), $to) as $date) {
            $holiday = Holiday::query()->firstOrCreate(['date' => $date->toDateString()], ['description' => $validated['description']]);
            $added += $holiday->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', "{$added} hari libur ditambahkan.");
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', "Libur {$holiday->date->translatedFormat('j F Y')} dihapus.");
    }
}
