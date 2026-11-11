<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingRequest;
use App\Http\Requests\Admin\RiskSettingRequest;
use App\Http\Requests\Admin\SettingRequest;
use App\Models\Holiday;
use App\Models\Setting;
use App\Support\AttendanceWindow;
use App\Support\RiskSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(AttendanceWindow $window, RiskSettings $riskSettings): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => ['days' => $window->weekForm()],
            'general' => [
                'app_name' => (string) config('app.name'),
                'school_name' => (string) config('app.school_name'),
                'timezone' => array_key_exists((string) config('app.timezone'), Setting::TIMEZONES) ? (string) config('app.timezone') : 'Asia/Jakarta',
                'demo_mode' => ! $window->isEnforced(),
                'finalize_at' => (string) config('attendance.finalize_at'),
            ],
            'timezones' => collect(Setting::TIMEZONES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'riskSettings' => $riskSettings->toArray(),
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

    /**
     * The weekly schedule: school days and the gate hours of each.
     */
    public function update(SettingRequest $request, AttendanceWindow $window): RedirectResponse
    {
        $window->saveSchedule($request->schedule());

        return back()->with('success', 'Jadwal absensi disimpan.');
    }

    /**
     * School name, demo mode and the time a forgotten day is finished.
     */
    public function updateGeneral(GeneralSettingRequest $request): RedirectResponse
    {
        Setting::put([
            Setting::APP_NAME => $request->validated('app_name'),
            Setting::SCHOOL_NAME => $request->validated('school_name'),
            Setting::TIMEZONE => $request->validated('timezone'),
            Setting::DEMO_MODE => $request->boolean('demo_mode') ? '1' : '0',
            Setting::FINALIZE_AT => $request->validated('finalize_at'),
        ]);

        return back()->with('success', $request->boolean('demo_mode')
            ? 'Pengaturan disimpan. Mode demo aktif: jadwal gerbang tidak dipakai.'
            : 'Pengaturan disimpan.');
    }

    /**
     * Thresholds of the early-warning rules (siswa berisiko).
     */
    public function updateRisk(RiskSettingRequest $request): RedirectResponse
    {
        Setting::put(collect($request->validated())->mapWithKeys(fn (int|string $value, string $key): array => ["risk_{$key}" => (string) (int) $value])->all());

        return back()->with('success', 'Aturan siswa berisiko disimpan.');
    }
}
