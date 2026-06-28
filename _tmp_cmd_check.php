<?php

use Illuminate\Support\Facades\Schema;

foreach (['tc_commands', 'tc_queued_commands'] as $t) {
    $has = Schema::hasTable($t);
    echo $t . ': ' . ($has ? 'YES' : 'no');
    if ($has) {
        echo ' cols=[' . implode(',', Schema::getColumnListing($t)) . ']';
    }
    echo PHP_EOL;
}
