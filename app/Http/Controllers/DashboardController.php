<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceTrend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the staff dashboard: the role's menu and, below it, attendance
     * trends. The duty teacher's home is today's monitor.
     */
    public function __invoke(Request $request, AttendanceTrend $trend): Response|RedirectResponse
    {
        if ($request->user()->hasRole(Role::GuruPiket)) {
            return redirect()->route('duty.monitor');
        }

        return Inertia::render('Dashboard', [
            'stats' => $request->user()->hasRole(Role::Admin) ? $this->adminStats() : null,
            'trends' => $trend->overview($request->user(), $request->integer('classroom') ?: null, $request->string('periode')->value() === 'tahun'),
        ]);
    }

    /**
     * @return array{academicYear: ?string, classrooms: int, students: int, users: int}
     */
    private function adminStats(): array
    {
        $academicYear = AcademicYear::active();

        return [
            'academicYear' => $academicYear?->name,
            'classrooms' => $academicYear?->classrooms()->count() ?? 0,
            'students' => Student::query()->where('status', StudentStatus::Active)->count(),
            'users' => User::query()->where('is_active', true)->count(),
        ];
    }
}
