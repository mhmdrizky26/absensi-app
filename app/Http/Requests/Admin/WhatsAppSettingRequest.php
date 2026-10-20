<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WhatsAppSettingRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'test_mode' => ['required', 'boolean'],
            'send_at' => ['required', 'date_format:H:i'],
            'daily_limit' => ['required', 'integer', 'between:1,1000'],
            'on_escalation' => ['required', 'boolean'],
            'on_new_streak' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'send_at' => 'Jam pemeriksaan',
            'daily_limit' => 'Batas pesan per hari',
        ];
    }
}
