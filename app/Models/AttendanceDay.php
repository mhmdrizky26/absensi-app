<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school day that was finished with "Selesaikan absen". Before that,
 * students without a mark are "belum absen"; afterwards they are Alpa.
 */
#[Fillable(['date', 'finalized_at', 'finalized_by', 'absent_count'])]
class AttendanceDay extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'finalized_at' => 'datetime',
            'absent_count' => 'integer',
        ];
    }

    public static function forDate(DateTimeInterface $date): ?self
    {
        return static::query()->whereDate('date', $date->format('Y-m-d'))->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
