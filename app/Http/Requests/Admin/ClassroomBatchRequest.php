<?php

namespace App\Http\Requests\Admin;

use App\Models\Classroom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomBatchRequest extends FormRequest
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
            'grades' => ['required', 'array', 'min:1'],
            'grades.*' => ['integer', Rule::in(array_keys(Classroom::GRADES))],
            'count' => ['required', 'integer', 'min:1', 'max:26'],
        ];
    }
}
