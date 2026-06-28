<?php

use Illuminate\Support\Facades\Schema;

foreach (['tc_notifications', 'tc_user_notification'] as $t) {
    echo $t . ': ';
    echo Schema::hasTable($t)
        ? implode(',', Schema::getColumnListing($t))
        : 'MISSING';
    echo "\n";
}
