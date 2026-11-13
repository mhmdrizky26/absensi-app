<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'role', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine whether the user holds any of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Whether this guru leads a class in the active academic year.
     */
    public function isHomeroomTeacher(): bool
    {
        return $this->hasRole(Role::Guru) && $this->currentHomeroom() !== null;
    }

    /**
     * Whether this guru is guru piket on the given day (today by default):
     * scheduled for its weekday and not replaced, or substituting for
     * someone that day.
     */
    public function isOnDuty(?CarbonInterface $date = null): bool
    {
        if (! $this->hasRole(Role::Guru)) {
            return false;
        }

        $date ??= today();

        $substitutions = DutySubstitution::query()
            ->whereDate('date', $date)
            ->where(fn (Builder $query) => $query->where('absent_user_id', $this->id)->orWhere('substitute_user_id', $this->id))
            ->get(['absent_user_id', 'substitute_user_id']);

        if ($substitutions->contains('substitute_user_id', $this->id)) {
            return true;
        }

        if ($substitutions->contains('absent_user_id', $this->id)) {
            return false;
        }

        return $this->dutySchedules()->where('weekday', $date->dayOfWeekIso)->exists();
    }

    /**
     * Whether this guru is guru piket on at least one weekday.
     */
    public function hasDutySchedule(): bool
    {
        return $this->hasRole(Role::Guru) && $this->dutySchedules()->exists();
    }

    /**
     * Admins, and guru piket on their duty day, do the duty teacher's work.
     */
    public function canDoDuty(): bool
    {
        return $this->hasRole(Role::Admin) || $this->isOnDuty();
    }

    /**
     * @return HasMany<DutySchedule, $this>
     */
    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class);
    }

    /**
     * The classroom this guru leads as wali kelas in the active academic year.
     */
    public function currentHomeroom(): ?Classroom
    {
        $academicYear = AcademicYear::active();

        return $academicYear ? $this->homeroomClassrooms()->where('academic_year_id', $academicYear->id)->first() : null;
    }

    /**
     * Classrooms this user leads as wali kelas, one per academic year.
     *
     * @return HasMany<Classroom, $this>
     */
    public function homeroomClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'homeroom_teacher_id');
    }
}
