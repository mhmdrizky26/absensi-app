<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DutySubstitutionRequest extends FormRequest
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
            'date' => ['required', 'date', 'after_or_equal:today'],
            'absent_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::Guru->value)],
            'substitute_user_id' => ['required', 'integer', 'different:absent_user_id', Rule::exists('users', 'id')->where('role', Role::Guru->value)->where('is_active', true)],
        ];
    }

    /**
     * The absent teacher must be on duty that day, and the substitute not yet.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $date = $this->date('date');

                if (! array_key_exists($date->dayOfWeekIso, DutySchedule::WEEKDAYS)) {
                    $validator->errors()->add('date', 'Jadwal piket hanya Senin sampai Jumat.');

                    return;
                }

                $absent = User::find($this->integer('absent_user_id'));
                $substitute = User::find($this->integer('substitute_user_id'));

                if (! $absent->isOnDuty($date)) {
                    $validator->errors()->add('absent_user_id', "{$absent->name} tidak sedang piket pada tanggal itu.");
                }

                if ($substitute->isOnDuty($date)) {
                    $validator->errors()->add('substitute_user_id', "{$substitute->name} sudah piket pada tanggal itu.");
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
            'date' => 'Tanggal',
            'absent_user_id' => 'Guru yang diganti',
            'substitute_user_id' => 'Guru pengganti',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Pengganti hanya bisa dicatat untuk hari ini atau tanggal mendatang.',
            'substitute_user_id.different' => 'Guru pengganti harus orang lain.',
            'substitute_user_id.exists' => 'Pilih akun guru yang masih aktif.',
        ];
    }
}
