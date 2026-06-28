<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// List all Traccar tables that relate to commands / tasks / queue.
$all = DB::select('SHOW TABLES');
$key = array_key_first((array) $all[0]);
$tables = array_map(fn ($r) => $r->$key, $all);

echo "== command/queue/task related tc_ tables ==\n";
foreach ($tables as $t) {
    if (preg_match('/command|queue|task/i', $t)) {
        echo $t . ': [' . implode(',', Schema::getColumnListing($t)) . "]\n";
    }
}

echo "\n== explicit checks ==\n";
foreach (['tc_commands', 'tc_commands_queue', 'tc_queued_commands', 'tc_command_queue'] as $t) {
    echo $t . ': ' . (Schema::hasTable($t) ? 'YES [' . implode(',', Schema::getColumnListing($t)) . ']' : 'no') . "\n";
}
