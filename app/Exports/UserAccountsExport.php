<?php

namespace App\Exports;

/**
 * The accounts made or updated by a teacher import, with their starting
 * passwords, to print and hand out. Passwords are only known right after the
 * import; afterwards only their hash is stored.
 */
class UserAccountsExport extends RecapSheet
{
    /**
     * @param  list<array{name: string, role: string, classroom: ?string, username: string, password: ?string}>  $accounts
     */
    public function __construct(private array $accounts, private string $schoolName) {}

    public function title(): string
    {
        return 'Daftar akun';
    }

    protected function titleLines(): array
    {
        return [
            'Daftar Akun Guru',
            $this->schoolName,
            'Simpan file ini baik-baik dan bagikan ke masing-masing guru. Password bisa diganti sendiri lewat menu profil setelah masuk.',
        ];
    }

    protected function headings(): array
    {
        return ['No', 'Nama', 'Peran', 'Wali kelas', 'Username', 'Password'];
    }

    protected function rows(): array
    {
        return array_map(fn (array $account, int $index): array => [
            $index + 1,
            $account['name'],
            $account['role'],
            $account['classroom'] ?? '-',
            $account['username'],
            $account['password'] ?? '(tidak diubah)',
        ], $this->accounts, array_keys($this->accounts));
    }
}
