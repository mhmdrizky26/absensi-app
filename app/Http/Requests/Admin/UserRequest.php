<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower(trim((string) $this->input('username')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
        ];
    }

    /**
     * Admins cannot lock themselves out by changing their own role or account.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');

                if (! $user || ! $user->is($this->user())) {
                    return;
                }

                if ($this->input('role') !== Role::Admin->value) {
                    $validator->errors()->add('role', 'Anda tidak bisa mengubah peran akun Anda sendiri.');
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
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
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, strip, atau garis bawah.',
        ];
    }
}
