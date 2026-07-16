<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Command timeout
    |--------------------------------------------------------------------------
    | Mark in-flight commands as timed out when Traccar never reports delivery /
    | acknowledgement within this many minutes.
    */
    'timeout_minutes' => (int) env('COMMAND_TIMEOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Queue fallback when Traccar API is down
    |--------------------------------------------------------------------------
    | GPRS devices only receive commands via Traccar POST /api/commands/send.
    | Inserting into tc_commands_queue without a live API does NOT push to an
    | already-online tracker. Keep this false unless you have a custom check-in
    | path that drains the queue.
    */
    'allow_queue_fallback' => (bool) env('COMMAND_ALLOW_QUEUE_FALLBACK', false),

    /*
    |--------------------------------------------------------------------------
    | Protocol profiles
    |--------------------------------------------------------------------------
    | Map BillX logical types (engineStop / engineResume) to the wire payload
    | Traccar should send for a given GPS family.
    |
    | Profiles are selected from tc_devices.model / attributes.command_profile /
    | attributes.protocol (case-insensitive contains match).
    */
    'default_profile' => 'generic',

    'profiles' => [

        /*
         * Default: use Traccar built-in types (GT06 / Concox / many hardwired).
         * Traccar encodes engineStop/engineResume per connected protocol.
         */
        'generic' => [
            'engineStop' => ['type' => 'engineStop', 'attributes' => []],
            'engineResume' => ['type' => 'engineResume', 'attributes' => []],
        ],

        /*
         * Teltonika FMB/FMC/FMT — digital output relay via setdigout.
         * setdigout 1 = outbound ON (immobilize / cut), setdigout 0 = OFF (resume).
         * Sent as Traccar "custom" text commands when this profile is selected.
         */
        'teltonika' => [
            'match' => ['teltonika', 'fmb', 'fmc', 'fmt', 'fm11', 'fm36', 'fmblos'],
            'engineStop' => [
                'type' => 'custom',
                'attributes' => ['data' => 'setdigout 1'],
            ],
            'engineResume' => [
                'type' => 'custom',
                'attributes' => ['data' => 'setdigout 0'],
            ],
        ],

        /*
         * Explicit GT06/Concox family — still uses native engineStop encoding.
         */
        'gt06' => [
            'match' => ['gt06', 'concox', 'h02', 'sinotrack'],
            'engineStop' => ['type' => 'engineStop', 'attributes' => []],
            'engineResume' => ['type' => 'engineResume', 'attributes' => []],
        ],
    ],

];
