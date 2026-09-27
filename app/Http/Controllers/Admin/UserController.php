<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $academicYearId = AcademicYear::active()?->id;

        $filters = [
            'q' => $request->string('q')->trim()->value(),
            'role' => $request->string('role')->value(),
        ];

        $users = User::query()
            ->with(['homeroomClassrooms' => fn (HasMany $query) => $query->where('academic_year_id', $academicYearId)])
            ->when($filters['q'], fn (Builder $query, string $term) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "{$term}%"),
            ))
            ->when(Role::tryFrom($filters['role']), fn (Builder $query, Role $role) => $query->where('role', $role))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role->value,
                'roleLabel' => $user->role->label(),
                'isActive' => $user->is_active,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'homeroomClassroom' => $user->homeroomClassrooms->first()?->name,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => collect(Role::cases())->map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return back()->with('success', "Akun {$user->name} ({$user->role->label()}) dibuat.");
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $attributes = $request->safe()->only(['name', 'username', 'role', 'is_active']);

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        DB::transaction(function () use ($user, $attributes): void {
            $user->update($attributes);

            // Only wali kelas can lead a class; release classes of anyone else.
            if ($user->role !== Role::WaliKelas) {
                $user->homeroomClassrooms()->update(['homeroom_teacher_id' => null]);
            }
        });

        return back()->with('success', "Akun {$user->name} diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        $user->delete();

        return back()->with('success', "Akun {$user->name} dihapus.");
    }
}
