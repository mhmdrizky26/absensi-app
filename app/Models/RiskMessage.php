<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One WhatsApp message (or a skipped one) about a student's attendance risk.
 */
#[Fillable(['risk_episode_id', 'student_id', 'guardian_contact_id', 'phone', 'trigger', 'level', 'reason', 'status', 'error', 'provider_message_id', 'sent_at'])]
class RiskMessage extends Model
{
    public const STATUS_QUEUED = 'antre';

    public const STATUS_SENT = 'terkirim';

    public const STATUS_FAILED = 'gagal';

    /** Recorded only: the sender is in test mode. */
    public const STATUS_TEST = 'uji';

    /** The student has no parent number that agreed to messages. */
    public const STATUS_NO_CONTACT = 'tanpa_kontak';

    /** Not sent because today's limit was reached. */
    public const STATUS_LIMITED = 'dibatasi';

    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Dalam antrean',
        self::STATUS_SENT => 'Terkirim',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_TEST => 'Mode uji (tidak dikirim)',
        self::STATUS_NO_CONTACT => 'Tidak ada nomor orang tua',
        self::STATUS_LIMITED => 'Melebihi batas harian',
    ];

    public const TRIGGER_NEW = 'masuk';

    public const TRIGGER_ESCALATED = 'naik';

    public const TRIGGER_NEW_STREAK = 'rentetan';

    public const TRIGGER_LABELS = [
        self::TRIGGER_NEW => 'Masuk daftar berisiko',
        self::TRIGGER_ESCALATED => 'Naik ke risiko tinggi',
        self::TRIGGER_NEW_STREAK => 'Alpa beruntun lagi',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RiskEpisode, $this>
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(RiskEpisode::class, 'risk_episode_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<GuardianContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(GuardianContact::class, 'guardian_contact_id');
    }
}
