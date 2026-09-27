<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the staff dashboard. Its content depends on the user's role; the
     * duty teacher's home is today's monitor.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasRole(Role::GuruPiket)) {
            return redirect()->route('duty.monitor');
        }

        return Inertia::render('Dashboard', [
            'stats' => $request->user()->hasRole(Role::Admin) ? $this->adminStats() : null,
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
