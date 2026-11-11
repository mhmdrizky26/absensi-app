<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('admin:buat {--name= : Nama lengkap} {--username= : Username untuk masuk} {--password= : Password (minimal 8 karakter)}')]
#[Description('Buat akun admin, misalnya admin pertama setelah aplikasi dipasang di server')]
class CreateAdmin extends Command
{
    /**
     * Asks for whatever was not given as an option, so it works both by hand
     * and in a deploy script.
     */
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? $this->ask('Nama lengkap', 'Administrator'),
            'username' => strtolower(trim((string) ($this->option('username') ?? $this->ask('Username', 'admin')))),
            'password' => $this->option('password') ?? $this->secret('Password (minimal 8 karakter)'),
        ];

        if ($this->option('password') === null && $this->secret('Ulangi password') !== $data['password']) {
            $this->error('Password tidak sama. Akun tidak dibuat.');

            return self::FAILURE;
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ], [
            'username.unique' => 'Username :input sudah dipakai.',
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, - dan _.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::query()->create([...$validator->validated(), 'role' => Role::Admin, 'is_active' => true]);

        $this->info("Akun admin {$data['username']} dibuat. Silakan masuk di halaman login.");

        return self::SUCCESS;
    }
}
