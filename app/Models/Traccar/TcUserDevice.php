<?php

namespace App\Models\Traccar;

use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Model;

/**
 * tc_user_device — Traccar user↔device assignment (one owner row per device in this app).
 */
class TcUserDevice extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return TraccarSchema::userDevicePivotKeys()['table'];
    }

    public function getKeyName(): string
    {
        return TraccarSchema::userDevicePivotKeys()['device'];
    }
}
