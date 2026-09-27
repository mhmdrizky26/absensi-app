<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attendance_id', 'from_status', 'to_status', 'source', 'changed_by', 'reason'])]
class AttendanceLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => AttendanceStatus::class,
            'to_status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
        ];
    }

    /**
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
