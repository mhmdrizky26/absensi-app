<?php

namespace App\Models;

use Database\Factories\DutySubstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On one date the substitute is guru piket in place of a scheduled teacher
 * who cannot come; the next week the schedule applies again.
 */
#[Fillable(['date', 'absent_user_id', 'substitute_user_id'])]
class DutySubstitution extends Model
{
    /** @use HasFactory<DutySubstitutionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function absentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'absent_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function substituteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }
}
