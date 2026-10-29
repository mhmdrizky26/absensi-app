<?php

namespace App\Models;

use Database\Factories\RiskEpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stretch of time a student stays on the early-warning list. Parents are
 * told when it starts, and again when it gets worse. Admin or guru BK can mark
 * it handled. Once the student drops off the list (or the semester changes)
 * the episode is deleted, so only students at risk right now are stored.
 */
#[Fillable(['student_id', 'academic_year_id', 'semester', 'started_on', 'ended_on', 'level', 'reasons', 'streak_ended_on', 'notified_at', 'handled_at', 'handled_by', 'handled_note'])]
class RiskEpisode extends Model
{
    /** @use HasFactory<RiskEpisodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'streak_ended_on' => 'date',
            'notified_at' => 'datetime',
            'handled_at' => 'datetime',
            'reasons' => 'array',
        ];
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('ended_on');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<RiskMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(RiskMessage::class);
    }
}
