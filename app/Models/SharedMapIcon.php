<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedMapIcon extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'category',
        'relative_path',
        'mime',
        'rotation_offset',
        'tags',
        'uploaded_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'rotation_offset' => 'integer',
    ];

    /**
     * Degrees added to GPS heading so the icon nose points forward.
     * Canonical UI values: 0 (north), -90 (east), 180 (south), 90 (west).
     */
    public function signedRotationOffset(): int
    {
        $raw = ((int) ($this->rotation_offset ?? 0)) % 360;
        if ($raw > 180) {
            $raw -= 360;
        }
        if ($raw < -180) {
            $raw += 360;
        }

        return $raw;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Registry / vehicle_type id used in the icon picker. */
    public function registryId(): string
    {
        return 'shared_'.$this->slug;
    }
}
