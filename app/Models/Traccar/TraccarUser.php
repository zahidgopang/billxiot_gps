<?php

namespace App\Models\Traccar;

use Illuminate\Database\Eloquent\Model;

class TraccarUser extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.users', 'tc_users');
    }
}
