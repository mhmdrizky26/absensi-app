<?php

namespace App\Models;

use App\Support\RandomCode;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['academic_year_id', 'grade', 'name', 'homeroom_teacher_id'])]
#[Hidden(['access_code'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /**
     * SMP grades and their Roman numerals as written on class names.
     */
    public const GRADES = [7 => 'VII', 8 => 'VIII', 9 => 'IX'];

    /**
     * Length of generated codes. Admins may also type their own code of
     * ACCESS_CODE_MIN to ACCESS_CODE_MAX letters or digits.
     */
    public const ACCESS_CODE_LENGTH = 6;

    public const ACCESS_CODE_MIN = 4;

    public const ACCESS_CODE_MAX = 12;

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            if ($classroom->access_code !== null) {
                return;
            }

            // Kode standar (ESSAR7A); kode acak hanya kalau kode standar sudah terpakai di tahun itu.
            $standard = static::standardAccessCode($classroom->name);
            $taken = static::query()
                ->where('academic_year_id', $classroom->academic_year_id)
                ->where('access_code', $standard)
                ->exists();

            $classroom->access_code = $taken ? static::generateAccessCode() : $standard;
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
     * The easy-to-remember code for a class: the school prefix, the grade
     * number and the section, e.g. "VII-A" → "ESSAR7A".
     */
    public static function standardAccessCode(string $name): string
    {
        [$roman, $section] = array_pad(explode('-', Str::upper($name), 2), 2, '');
        $grade = array_search($roman, self::GRADES, true) ?: $roman;
        $code = preg_replace('/[^A-Z0-9]/', '', Str::upper(config('attendance.class_code_prefix')).$grade.$section);

        return substr($code, 0, self::ACCESS_CODE_MAX);
    }

    /**
     * Generate a random access code that no classroom uses. Look-alike
     * characters are left out so a teacher can type it without guessing.
     */
    public static function generateAccessCode(): string
    {
        do {
            $code = RandomCode::generate(self::ACCESS_CODE_LENGTH);
        } while (static::query()->where('access_code', $code)->exists());

        return $code;
    }

    public function useStandardAccessCode(): void
    {
        $this->forceFill(['access_code' => static::standardAccessCode($this->name)])->save();
    }

    public function regenerateAccessCode(): void
    {
        $this->forceFill(['access_code' => static::generateAccessCode()])->save();
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
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('academic_year_id')->withTimestamps();
    }
}
