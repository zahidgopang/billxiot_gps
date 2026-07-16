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
    | Resolution priority (CommandProtocolMapper):
    |   1. attributes.command_profile          (explicit override)
    |   2. attributes.protocol / traccar_protocol (explicit or cached)
    |   3. tc_positions.protocol               (live Traccar protocol)
    |   4. tc_devices.model / attributes.device_model keyword match
    |   5. broader keyword fallback (name, etc.)
    |   6. default_profile
    */
    'default_profile' => 'generic',

    /*
    |--------------------------------------------------------------------------
    | Traccar protocol → command profile
    |--------------------------------------------------------------------------
    | Exact protocol strings as stored on tc_positions.protocol. Add an entry
    | when introducing a new BillX profile for Queclink, Ruptela, etc.
    | Protocols without an entry still fall through to profile "match" needles
    | and to a same-name profile when one exists.
    */
    'protocol_to_profile' => [
        'teltonika' => 'teltonika',
        'gt06' => 'gt06',
        'h02' => 'gt06',
        'huabao' => 'gt06',
    ],

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
