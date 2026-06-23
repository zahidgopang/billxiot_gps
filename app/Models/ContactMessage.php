<?php
// app/Models/ContactMessage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'status',
        'priority',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'new',
        'priority' => 'medium'
    ];

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M d, Y \a\t h:i A');
    }

    public function getShortMessageAttribute()
    {
        return strlen($this->message) > 100
            ? substr($this->message, 0, 100) . '...'
            : $this->message;
    }

    // Methods
    public function markAsRead()
    {
        $this->update(['status' => 'read']);
    }

    public function markAsReplied()
    {
        $this->update(['status' => 'replied']);
    }
}
