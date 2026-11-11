<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * School-wide settings that used to live in .env.
 */
class GeneralSettingRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:40'],
            'school_name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', Rule::in(array_keys(Setting::TIMEZONES))],
            'demo_mode' => ['required', 'boolean'],
            'finalize_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'app_name' => 'Nama aplikasi',
            'school_name' => 'Nama sekolah',
            'timezone' => 'Zona waktu',
            'demo_mode' => 'Mode demo',
            'finalize_at' => 'Jam selesai otomatis',
        ];
    }
}
