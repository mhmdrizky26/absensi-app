<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentStudyRoomController extends Controller
{
    /**
     * Where a student of a class without its own room studies this year
     * ("belajar di"). Their marks, recap and report stay with their own class;
     * at promotion they keep studying in the host class one grade higher.
     */
    public function update(Request $request, Student $student): RedirectResponse
    {
        $academicYear = AcademicYear::active();
        $classroom = $academicYear ? $student->classroomIn($academicYear) : null;

        if (! $classroom) {
            return back()->with('error', "{$student->name} belum punya kelas di tahun ajaran aktif.");
        }

        if ($classroom->has_room) {
            return back()->with('error', "Kelas {$classroom->name} punya ruang sendiri, jadi {$student->name} tidak perlu dititipkan.");
        }

        $validated = $request->validate([
            'study_classroom_id' => [
                'nullable',
                'integer',
                Rule::exists('classrooms', 'id')->where('academic_year_id', $academicYear->id)->where('has_room', true),
            ],
        ], ['study_classroom_id.exists' => 'Pilih ruang belajar dari kelas yang punya ruang.']);

        $student->classrooms()->updateExistingPivot($classroom->id, ['study_classroom_id' => $validated['study_classroom_id'] ?? null]);

        $studyRoom = $student->classroomIn($academicYear)?->pivot->study_classroom_id;

        return back()->with('success', $studyRoom
            ? "{$student->name} ({$classroom->name}) sekarang belajar di ".$academicYear->classrooms()->whereKey($studyRoom)->value('name').'.'
            : "{$student->name} ({$classroom->name}) belum dititipkan ke kelas mana pun.");
    }
}
