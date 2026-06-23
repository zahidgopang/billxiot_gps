<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotificationLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'fcm_token_hash',
        'push_type',
        'title',
        'body',
        'status',
        'http_status',
        'error_message',
        'response',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
