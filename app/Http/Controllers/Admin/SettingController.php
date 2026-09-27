<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Models\Holiday;
use App\Models\Setting;
use App\Support\AttendanceWindow;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(AttendanceWindow $window): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'opens_at' => $window->opensAtTime(),
                'closes_at' => $window->closesAtTime(),
                'school_days' => $window->schoolDays(),
            ],
            'isEnforced' => $window->isEnforced(),
            'holidays' => Holiday::query()
                ->whereDate('date', '>=', today()->subMonths(2))
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->toDateString(),
                    'description' => $holiday->description,
                ]),
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $schoolDays = collect($request->validated('school_days'))->map(fn ($day): int => (int) $day)->unique()->sort()->implode(',');

        Setting::put([
            AttendanceWindow::OPENS_AT => $request->validated('opens_at'),
            AttendanceWindow::CLOSES_AT => $request->validated('closes_at'),
            AttendanceWindow::SCHOOL_DAYS => $schoolDays,
        ]);

        return back()->with('success', 'Pengaturan jam absensi disimpan.');
    }
}
