<?php

namespace App\Models;

use Database\Factories\OnlineDayFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A school day held online (sekolah daring). It still counts as a school day
 * in the recaps, but nobody scans: every student without a mark is Hadir.
 */
#[Fillable(['date', 'description'])]
class OnlineDay extends Model
{
    /** @use HasFactory<OnlineDayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public static function forDate(DateTimeInterface $date): ?self
    {
        return static::query()->whereDate('date', $date->format('Y-m-d'))->first();
    }
}
