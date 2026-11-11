<?php

namespace App\Support;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every active student placed in a class of the active year, with the class
 * the mark belongs to and, for a class without its own room, the class they
 * study in. Gate tablets cache this list so they can scan offline.
 */
class GateRoster
{
    /**
     * @param  iterable<int>|null  $studentIds  only these students
     * @return Collection<int, object{id: int, name: string, nis: string, qr_token: string, qr_revoked_at: ?string, classroom_id: int, classroom: string, study_classroom: ?string}>
     */
    public function students(?iterable $studentIds = null): Collection
    {
        $academicYear = AcademicYear::active();

        if (! $academicYear) {
            return collect();
        }

        return DB::table('classroom_student')
            ->join('students', 'students.id', '=', 'classroom_student.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->leftJoin('classrooms as study', 'study.id', '=', 'classroom_student.study_classroom_id')
            ->where('classroom_student.academic_year_id', $academicYear->id)
            ->where('students.status', StudentStatus::Active->value)
            ->when($studentIds !== null, fn ($query) => $query->whereIn('students.id', collect($studentIds)->all()))
            ->orderBy('students.name')
            ->get([
                'students.id',
                'students.name',
                'students.nis',
                'students.qr_token',
                'students.qr_revoked_at',
                'classroom_student.classroom_id',
                'classrooms.name as classroom',
                'study.name as study_classroom',
            ])
            ->keyBy('id');
    }
}
