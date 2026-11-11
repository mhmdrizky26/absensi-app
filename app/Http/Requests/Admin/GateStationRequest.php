<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GateStation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GateStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => GateStation::normalizeCode($this->input('code')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('gate_stations', 'name')->ignore($this->route('gateStation'))],
            'code' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('gate_stations', 'code')->ignore($this->route('gateStation')),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $classroom = Classroom::query()
                        ->where('academic_year_id', AcademicYear::active()?->id)
                        ->where('view_code', Classroom::normalizeViewCode((string) $value))
                        ->first();

                    if ($classroom) {
                        $fail("Kode ini sudah dipakai sebagai kode kelas {$classroom->name}. Pilih kode lain.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh huruf, angka, tanda - dan _, tanpa spasi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama tab',
            'code' => 'Kode',
        ];
    }
}
