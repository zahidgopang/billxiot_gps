<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo 'device_commands: ' . (Schema::hasTable('device_commands') ? 'EXISTS' : 'gone') . "\n";
$rows = DB::table('migrations')->where('migration', 'like', '%device_commands%')->pluck('migration');
echo 'migrations: ' . ($rows->isEmpty() ? 'none' : $rows->implode(', ')) . "\n";
