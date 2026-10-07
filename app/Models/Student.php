<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\DispensationStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Support\RandomCode;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nis', 'nisn', 'name', 'gender', 'status'])]
#[Hidden(['qr_token'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * Printed in front of the token in every card's QR code, so the scanner
     * can tell a student card from any other QR code.
     */
    public const QR_PREFIX = 'ABS-';

    public const QR_TOKEN_LENGTH = 12;

    protected $attributes = [
        'status' => 'aktif',
        'qr_version' => 1,
    ];

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            $student->qr_token ??= static::generateQrToken();
            $student->qr_issued_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'qr_version' => 'integer',
            'qr_issued_at' => 'datetime',
            'qr_revoked_at' => 'datetime',
        ];
    }

    public static function generateQrToken(): string
    {
        do {
            $token = RandomCode::generate(self::QR_TOKEN_LENGTH);
        } while (static::query()->where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Find the student whose current, non-revoked card carries this QR text.
     */
    public static function findByQrPayload(string $payload): ?self
    {
        $payload = strtoupper(trim($payload));

        if (! str_starts_with($payload, self::QR_PREFIX)) {
            return null;
        }

        return static::query()
            ->where('qr_token', substr($payload, strlen(self::QR_PREFIX)))
            ->whereNull('qr_revoked_at')
            ->first();
    }

    /**
     * The text encoded in the student's QR code.
     */
    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->qr_token;
    }

    /**
     * What the card screens need to show and print this student's card.
     *
     * @return array{id: int, name: string, nis: string, nisn: ?string, payload: string, version: int, issuedAt: ?string, isRevoked: bool}
     */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'payload' => $this->qrPayload(),
            'version' => $this->qr_version,
            'issuedAt' => $this->qr_issued_at?->toIso8601String(),
            'isRevoked' => ! $this->hasActiveCard(),
        ];
    }

    /**
     * A one-way fingerprint of the QR text. Class scanners receive this
     * instead of the token, so a leaked class code cannot be used to make
     * copies of classmates' cards.
     */
    public function qrHash(): string
    {
        return substr(hash('sha256', $this->qrPayload()), 0, 16);
    }

    public function hasActiveCard(): bool
    {
        return $this->qr_revoked_at === null;
    }

    /**
     * Replace the card: the old QR code stops working at once.
     */
    public function issueNewCard(): void
    {
        $this->forceFill([
            'qr_token' => static::generateQrToken(),
            'qr_version' => $this->qr_version + 1,
            'qr_issued_at' => now(),
            'qr_revoked_at' => null,
        ])->save();
    }

    /**
     * Block the current card, for example while a lost card is being replaced.
     */
    public function revokeCard(): void
    {
        $this->forceFill(['qr_revoked_at' => now()])->save();
    }

    public function restoreCard(): void
    {
        $this->forceFill(['qr_revoked_at' => null])->save();
    }

    /**
     * @return BelongsToMany<Classroom, $this>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class)->withPivot('academic_year_id')->withTimestamps();
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<Dispensation, $this>
     */
    public function dispensations(): HasMany
    {
        return $this->hasMany(Dispensation::class);
    }

    /**
     * The classroom this student sits in during the given academic year.
     */
    public function classroomIn(AcademicYear $academicYear): ?Classroom
    {
        return $this->classrooms()->wherePivot('academic_year_id', $academicYear->id)->first();
    }

    /**
     * Place the student in a classroom for that classroom's academic year,
     * replacing any other placement in the same year. Null removes the placement.
     */
    public function placeIn(?Classroom $classroom, AcademicYear $academicYear): void
    {
        $this->classrooms()->wherePivot('academic_year_id', $academicYear->id)->detach();

        if ($classroom) {
            $this->classrooms()->attach($classroom->id, ['academic_year_id' => $academicYear->id]);
        }
    }

    /**
     * Students not yet excused on the date: no Sakit/Izin/Dispensasi mark and
     * no dispensation request (waiting or approved) covering it.
     */
    #[Scope]
    protected function notExcusedOn(Builder $query, CarbonInterface $date): void
    {
        $query
            ->whereDoesntHave('attendances', fn (Builder $query) => $query
                ->whereDate('date', $date)
                ->whereIn('status', [AttendanceStatus::Sick, AttendanceStatus::Excused, AttendanceStatus::Dispensation]))
            ->whereDoesntHave('dispensations', fn (Builder $query) => $query
                ->where('status', '!=', DispensationStatus::Rejected)
                ->whereDate('starts_on', '<=', $date)
                ->whereDate('ends_on', '>=', $date));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('nis', 'like', "{$term}%")
                ->orWhere('nisn', 'like', "{$term}%");
        }));
    }
}
