<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'odd_semester_starts_on', 'even_semester_starts_on', 'ends_on'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'odd_semester_starts_on' => 'date',
            'even_semester_starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'promoted_at' => 'datetime',
        ];
    }

    /**
     * The academic year the school is currently running, if one is set.
     */
    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Make this the only active academic year.
     */
    public function activate(): void
    {
        DB::transaction(function (): void {
            static::query()->whereKeyNot($this->getKey())->update(['is_active' => false]);
            $this->forceFill(['is_active' => true])->save();
        });
    }

    /**
     * First and last day of a semester ("ganjil" or "genap").
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function semesterRange(string $semester): array
    {
        return $semester === 'genap'
            ? [$this->even_semester_starts_on->toImmutable(), $this->ends_on->toImmutable()]
            : [$this->odd_semester_starts_on->toImmutable(), $this->even_semester_starts_on->toImmutable()->subDay()];
    }

    /**
     * The semester $today falls in, from its first day up to $today.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    public function semesterSoFar(CarbonInterface $today): array
    {
        $semester = $this->semesterOn($today);
        [$from, $to] = $this->semesterRange($semester);

        return [$from, $to->min($today->toImmutable()->startOfDay()), $semester];
    }

    /**
     * The semester a date falls in; dates before the year count as ganjil.
     */
    public function semesterOn(CarbonInterface $date): string
    {
        return $date->greaterThanOrEqualTo($this->even_semester_starts_on) ? 'genap' : 'ganjil';
    }

    /**
     * @return HasMany<Classroom, $this>
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
}
