<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['academic_year_id', 'grade', 'name', 'has_room', 'homeroom_teacher_id'])]
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

    /**
     * Build a class name such as "VII-A" from a grade and a section letter.
     */
    public static function nameFor(int $grade, string $section): string
    {
        return self::GRADES[$grade].'-'.Str::upper($section);
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
