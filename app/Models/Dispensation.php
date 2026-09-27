<?php

namespace App\Models;

use App\Enums\DispensationStatus;
use App\Enums\Role;
use Database\Factories\DispensationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'classroom_id', 'starts_on', 'ends_on', 'reason', 'attachment_path', 'requested_by'])]
class Dispensation extends Model
{
    /** @use HasFactory<DispensationFactory> */
    use HasFactory;

    public const APPROVAL_HOMEROOM = 'wali_kelas';

    public const APPROVAL_DUTY = 'guru_piket';

    protected $attributes = [
        'status' => 'menunggu',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => DispensationStatus::class,
            'homeroom_approved_at' => 'datetime',
            'duty_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === DispensationStatus::Pending;
    }

    /**
     * Which approval this user gives: the wali kelas of the student's class
     * approves as homeroom, any guru piket approves as duty teacher. Null
     * when the user cannot approve this request.
     */
    public function approvalSideFor(User $user): ?string
    {
        return match (true) {
            $user->hasRole(Role::WaliKelas) && $this->classroom->homeroom_teacher_id === $user->id => self::APPROVAL_HOMEROOM,
            $user->hasRole(Role::GuruPiket) => self::APPROVAL_DUTY,
            default => null,
        };
    }

    /**
     * Whether the user still has to approve or reject this request.
     */
    public function awaitsDecisionFrom(User $user): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return match ($this->approvalSideFor($user)) {
            self::APPROVAL_HOMEROOM => $this->homeroom_approved_at === null,
            self::APPROVAL_DUTY => $this->duty_approved_at === null,
            default => false,
        };
    }

    public function isFullyApproved(): bool
    {
        return $this->homeroom_approved_at !== null && $this->duty_approved_at !== null;
    }

    /**
     * Pending requests that still need this user's decision.
     */
    #[Scope]
    protected function awaitingDecisionFrom(Builder $query, User $user): void
    {
        $query->where('status', DispensationStatus::Pending);

        if ($user->hasRole(Role::WaliKelas)) {
            $query->whereNull('homeroom_approved_at')->where('classroom_id', $user->currentHomeroom()?->id ?? 0);
        } elseif ($user->hasRole(Role::GuruPiket)) {
            $query->whereNull('duty_approved_at');
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function homeroomApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dutyApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'duty_approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
