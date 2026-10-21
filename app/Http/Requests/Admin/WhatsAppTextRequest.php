<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WhatsAppTextRequest extends FormRequest
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
            'high' => ['required', 'string', 'max:1000'],
            'watch' => ['required', 'string', 'max:1000'],
            'test' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'high' => 'Pesan risiko tinggi',
            'watch' => 'Pesan perlu perhatian',
            'test' => 'Pesan uji',
        ];
    }
}
