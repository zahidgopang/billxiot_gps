<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'can_track_maps',
        'created_by',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'can_track_maps' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->slug)) {
                $client->slug = Str::slug($client->name);
            }
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClientMember::class);
    }

    public function clientDevices(): HasMany
    {
        return $this->hasMany(ClientDevice::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'client_devices', 'client_id', 'device_id')
            ->withTimestamps();
    }

    public function adminScopes(): HasMany
    {
        return $this->hasMany(AdminClientScope::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Client company managers may open live maps for fleet end users when enabled.
     */
    public function allowsMapTracking(): bool
    {
        return $this->isActive() && (bool) $this->can_track_maps;
    }
}
