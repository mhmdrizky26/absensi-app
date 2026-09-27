<?php

namespace App\Http\Requests\Recap;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Support\RecapScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
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
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)->except([AttendanceStatus::Dispensation])],
            'reason' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * Only a class the user may see in the recap can be corrected.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('student_id')) {
                    return;
                }

                $academicYear = AcademicYear::active();
                $classroom = $academicYear ? Student::find($this->integer('student_id'))->classroomIn($academicYear) : null;

                if (! $classroom || ! app(RecapScope::class)->canCorrect($this->user(), $classroom)) {
                    $validator->errors()->add('student_id', 'Anda tidak bisa mengubah absensi siswa ini.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Tanggal di masa depan tidak bisa dikoreksi. Untuk izin yang sudah diketahui, pakai menu Izin & sakit.',
            'reason.required' => 'Tulis alasan koreksi, misalnya "ada surat sakit" atau "lupa di-scan".',
        ];
    }
}
