<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RiskSettingRequest extends FormRequest
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
            'window_days' => ['required', 'integer', 'between:7,120'],
            'absent_watch' => ['required', 'integer', 'between:1,60'],
            'absent_high' => ['required', 'integer', 'between:1,60', 'gte:absent_watch'],
            'rate_watch' => ['required', 'integer', 'between:1,100'],
            'rate_high' => ['required', 'integer', 'between:1,100', 'lte:rate_watch'],
            'semester_rate_high' => ['required', 'integer', 'between:1,100'],
            'late_watch' => ['required', 'integer', 'between:1,60'],
            'excused_watch' => ['required', 'integer', 'between:1,60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'window_days' => 'Periode',
            'absent_watch' => 'Alpa (perlu perhatian)',
            'absent_high' => 'Alpa (risiko tinggi)',
            'rate_watch' => 'Kehadiran (perlu perhatian)',
            'rate_high' => 'Kehadiran (risiko tinggi)',
            'semester_rate_high' => 'Kehadiran semester',
            'late_watch' => 'Terlambat',
            'excused_watch' => 'Sakit + izin',
        ];
    }
}
