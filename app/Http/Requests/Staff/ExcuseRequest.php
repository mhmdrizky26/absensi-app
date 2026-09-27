<?php

namespace App\Http\Requests\Staff;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExcuseRequest extends FormRequest
{
    /**
     * Longest span one letter may cover.
     */
    public const MAX_DAYS = 14;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'status' => ['required', Rule::in([AttendanceStatus::Sick->value, AttendanceStatus::Excused->value])],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'note' => ['required', 'string', 'max:200'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['student_id', 'from', 'to'])) {
                    return;
                }

                if ($this->date('from')->diffInDays($this->date('to')) >= self::MAX_DAYS) {
                    $validator->errors()->add('to', 'Satu surat paling lama untuk '.self::MAX_DAYS.' hari.');
                }

                $academicYear = AcademicYear::active();
                $classroom = $academicYear ? Student::find($this->integer('student_id'))->classroomIn($academicYear) : null;

                if (! $classroom) {
                    $validator->errors()->add('student_id', 'Siswa ini belum punya kelas di tahun ajaran aktif.');

                    return;
                }

                $user = $this->user();

                if ($user->hasRole(Role::WaliKelas) && $user->currentHomeroom()?->id !== $classroom->id) {
                    $validator->errors()->add('student_id', 'Wali kelas hanya bisa mengisi izin untuk siswa kelasnya sendiri.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'Siswa',
            'from' => 'Tanggal mulai',
            'to' => 'Tanggal selesai',
            'note' => 'Keterangan',
            'attachment' => 'Foto surat',
        ];
    }
}
