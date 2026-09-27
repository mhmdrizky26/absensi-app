<?php

namespace App\Actions;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\DispensationStatus;
use App\Models\Attendance;
use App\Models\Dispensation;
use App\Models\User;
use App\Support\AttendanceWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Approvals and rejections of dispensations. A dispensation needs the wali
 * kelas and a guru piket; once both approved, every school day in its range
 * becomes Dispensasi, except days the student was already at school.
 */
class DecideDispensation
{
    public function __construct(
        private SetAttendanceStatus $setAttendanceStatus,
        private AttendanceWindow $window,
    ) {}

    /**
     * Record the user's approval. Returns how many days became Dispensasi
     * (0 while the other approval is still missing).
     *
     * @throws InvalidArgumentException when the user cannot approve it now
     */
    public function approve(Dispensation $dispensation, User $user): int
    {
        return DB::transaction(function () use ($dispensation, $user): int {
            $dispensation = Dispensation::query()->lockForUpdate()->findOrFail($dispensation->id);

            if (! $dispensation->awaitsDecisionFrom($user)) {
                throw new InvalidArgumentException('Dispensasi ini tidak sedang menunggu persetujuan Anda.');
            }

            $this->recordApproval($dispensation, $user);

            if (! $dispensation->isFullyApproved()) {
                return 0;
            }

            $dispensation->forceFill(['status' => DispensationStatus::Approved])->save();

            return $this->applyMarks($dispensation, $user);
        });
    }

    /**
     * @throws InvalidArgumentException when the user cannot decide on it now
     */
    public function reject(Dispensation $dispensation, User $user, string $reason): void
    {
        if (! $dispensation->awaitsDecisionFrom($user)) {
            throw new InvalidArgumentException('Dispensasi ini tidak sedang menunggu persetujuan Anda.');
        }

        $dispensation->forceFill([
            'status' => DispensationStatus::Rejected,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
    }

    /**
     * Mark the approval side this user holds; used both when deciding and
     * when the requester is one of the approvers themselves.
     */
    public function recordApproval(Dispensation $dispensation, User $user): void
    {
        $side = $dispensation->approvalSideFor($user);

        if ($side === Dispensation::APPROVAL_HOMEROOM && $dispensation->homeroom_approved_at === null) {
            $dispensation->forceFill(['homeroom_approved_by' => $user->id, 'homeroom_approved_at' => now()])->save();
        }

        if ($side === Dispensation::APPROVAL_DUTY && $dispensation->duty_approved_at === null) {
            $dispensation->forceFill(['duty_approved_by' => $user->id, 'duty_approved_at' => now()])->save();
        }
    }

    private function applyMarks(Dispensation $dispensation, User $user): int
    {
        $marked = 0;
        $days = $this->window->schoolDaysBetween(
            CarbonImmutable::parse($dispensation->starts_on),
            CarbonImmutable::parse($dispensation->ends_on),
        );

        foreach ($days as $day) {
            $existing = Attendance::query()->where('student_id', $dispensation->student_id)->whereDate('date', $day)->first();

            if ($existing?->status->isAtSchool()) {
                continue;
            }

            $this->setAttendanceStatus->handle($dispensation->student, $day, AttendanceStatus::Dispensation, AttendanceSource::Dispensation, $user, [
                'note' => "Dispensasi: {$dispensation->reason}",
                'dispensation_id' => $dispensation->id,
            ]);
            $marked++;
        }

        return $marked;
    }
}
