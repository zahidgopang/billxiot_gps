<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::dropIfExists('device_commands');
echo "dropped device_commands: " . (Schema::hasTable('device_commands') ? 'STILL EXISTS' : 'ok') . "\n";

$deleted = DB::table('migrations')
    ->where('migration', '2026_06_28_000000_create_device_commands_table')
    ->delete();
echo "migration rows removed: {$deleted}\n";
