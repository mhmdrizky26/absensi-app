<?php

namespace App\Models;

use Database\Factories\DutyScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guru on duty (guru piket) every week on one weekday.
 */
#[Fillable(['user_id', 'weekday'])]
class DutySchedule extends Model
{
    /** @use HasFactory<DutyScheduleFactory> */
    use HasFactory;

    /**
     * School weekdays with a duty schedule, ISO numbered (1 = Senin).
     */
    public const WEEKDAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
