<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    public const SOURCE_AUTHENTICATED = 'authenticated';

    public const SOURCE_PUBLIC = 'public';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'source',
        'name',
        'email',
        'phone',
        'username',
        'reason',
        'status',
        'summary',
        'ip_address',
        'user_agent',
        'metadata',
        'reviewed_by',
        'reviewed_by_name',
        'review_note',
        'reviewed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'source' => self::SOURCE_AUTHENTICATED,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function ticketNumber(): string
    {
        return 'ADR-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
