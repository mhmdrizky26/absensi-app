<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['academic_year_id', 'grade', 'name', 'view_code', 'has_room', 'homeroom_teacher_id'])]
#[Hidden(['view_code'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /**
     * SMP grades and their Roman numerals as written on class names.
     */
    public const GRADES = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'has_room' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            if (filled($classroom->view_code)) {
                return;
            }

            $code = static::standardViewCode($classroom->grade, $classroom->name);
            $candidate = $code;

            for ($suffix = 2; static::query()->where('academic_year_id', $classroom->academic_year_id)->where('view_code', $candidate)->exists(); $suffix++) {
                $candidate = $code.$suffix;
            }

            $classroom->view_code = $candidate;
        });
    }

    /**
     * Build a class name such as "VII-A" from a grade and a section letter.
     */
    public static function nameFor(int $grade, string $section): string
    {
        return self::GRADES[$grade].'-'.Str::upper($section);
    }

    /**
     * The short code subject teachers type to see a class: grade number and
     * section in capitals, e.g. "VII-A" → "7A".
     */
    public static function standardViewCode(?int $grade, string $name): string
    {
        return self::normalizeViewCode(($grade ?? '').Str::after($name, '-'));
    }

    /**
     * Codes are kept in capitals and typed on a phone, so any case and
     * spaces are accepted.
     */
    public static function normalizeViewCode(?string $code): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    /**
     * Students placed in this class. For a class without its own room, the
     * pivot's study_classroom_id says which class each student studies in;
     * their marks still belong to this class.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('academic_year_id', 'study_classroom_id')->withTimestamps();
    }
}
