<?php

namespace App\Models;

use Database\Factories\GuardianContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parent's or guardian's WhatsApp number. Messages only go to contacts
 * that agreed to receive them (consented_at is set).
 */
#[Fillable(['student_id', 'name', 'relation', 'phone', 'consented_at'])]
class GuardianContact extends Model
{
    /** @use HasFactory<GuardianContactFactory> */
    use HasFactory;

    public const RELATIONS = ['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali'];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }

    /**
     * Indonesian numbers in WhatsApp's international form: "0812-2345 678",
     * "+62 812…" and "812…" (Excel drops the leading zero) all become "62812…".
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        $digits = match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };

        return preg_match('/^628\d{7,11}$/', $digits) ? $digits : null;
    }

    /**
     * "6281234567890" → "0812-3456-7890" and "62895344024316" → "0895-3440-24316",
     * for showing to teachers.
     */
    public static function displayPhone(string $phone): string
    {
        $local = '0'.substr($phone, 2);

        return substr($local, 0, 4).'-'.substr($local, 4, 4).'-'.substr($local, 8);
    }

    #[Scope]
    protected function consented(Builder $query): void
    {
        $query->whereNotNull('consented_at');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
