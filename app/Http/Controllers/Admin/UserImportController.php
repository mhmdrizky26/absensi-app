<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ImportUsers;
use App\Exports\UserAccountsExport;
use App\Exports\UserTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import teacher accounts from a spreadsheet. The accounts and starting
 * passwords of the last import stay downloadable (encrypted in the admin's
 * session) until the next import or logout.
 */
class UserImportController extends Controller
{
    public const SESSION_KEY = 'imported_accounts';

    public function create(): Response
    {
        $academicYear = AcademicYear::active();

        return Inertia::render('Admin/Users/Import', [
            'academicYear' => $academicYear?->only('id', 'name'),
            'classroomNames' => $academicYear
                ? $academicYear->classrooms()->orderBy('grade')->orderBy('name')->pluck('name')
                : [],
        ]);
    }

    public function store(Request $request, ImportUsers $importUsers): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $reader = new StudentsImport;
        Excel::import($reader, $request->file('file'));

        $result = $importUsers->handle($reader->rows, AcademicYear::active());

        if ($result['errors'] !== []) {
            return back()
                ->with('error', 'Import dibatalkan, tidak ada data yang disimpan. Perbaiki baris berikut lalu unggah ulang.')
                ->with('importErrors', $result['errors']);
        }

        $request->session()->put(self::SESSION_KEY, Crypt::encrypt($result['accounts']));

        return redirect()->route('users.index')->with(
            'success',
            "Import selesai: {$result['created']} akun baru, {$result['updated']} akun diperbarui. Unduh daftar akun untuk dibagikan ke guru.",
        );
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new UserTemplateExport, 'template-import-guru.xlsx');
    }

    /**
     * The usernames and starting passwords of the last import.
     */
    public function accounts(Request $request): BinaryFileResponse|RedirectResponse
    {
        $accounts = self::importedAccounts($request);

        if ($accounts === []) {
            return redirect()->route('users.index')->with('error', 'Belum ada import guru di sesi ini.');
        }

        return Excel::download(
            new UserAccountsExport($accounts, (string) config('app.school_name')),
            'daftar-akun-guru.xlsx',
        );
    }

    /**
     * Forget the passwords of the last import once they were handed out.
     */
    public function forgetAccounts(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return back()->with('success', 'Daftar akun dihapus dari sesi ini.');
    }

    /**
     * @return list<array{name: string, role: string, classroom: ?string, username: string, password: ?string}>
     */
    public static function importedAccounts(Request $request): array
    {
        $stored = $request->session()->get(self::SESSION_KEY);

        return is_string($stored) ? Crypt::decrypt($stored) : [];
    }
}
