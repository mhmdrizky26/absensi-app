<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which classrooms a user may see in recaps: an admin sees every class of
 * the active year, a wali kelas only the class they lead.
 */
class RecapScope
{
    /**
     * @return Collection<int, Classroom>
     */
    public function classroomsFor(User $user): Collection
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return collect();
        }

        if ($user->hasRole(Role::WaliKelas)) {
            return collect([$user->currentHomeroom()])->filter()->values();
        }

        return $academicYear->classrooms()->orderBy('grade')->orderBy('name')->get();
    }

    /**
     * The requested classroom, or the first allowed one. Aborts with 403 when
     * the user asks for a class they may not see.
     */
    public function classroomFor(User $user, ?int $classroomId): ?Classroom
    {
        $classrooms = $this->classroomsFor($user);

        if ($classroomId === null) {
            return $classrooms->first();
        }

        $classroom = $classrooms->firstWhere('id', $classroomId);
        abort_if($classroom === null, 403, 'Anda tidak punya akses ke kelas ini.');

        return $classroom;
    }

    public function canCorrect(User $user, Classroom $classroom): bool
    {
        return $this->classroomsFor($user)->contains('id', $classroom->id);
    }
}
