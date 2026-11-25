<?php

namespace App\Models;

use Database\Factories\GreetingScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guru on sapa pagi every week on one weekday: greets students at the gate
 * and may record attendance from when the gate opens until jam masuk.
 */
#[Fillable(['user_id', 'weekday'])]
class GreetingSchedule extends Model
{
    /** @use HasFactory<GreetingScheduleFactory> */
    use HasFactory;

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
