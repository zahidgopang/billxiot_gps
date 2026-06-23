<?php

return [

    /** Minimum seconds a human should spend on the form before submit. */
    'min_form_seconds' => (int) env('BOT_MIN_FORM_SECONDS', 3),

    /** Reject submissions older than this (replay protection). */
    'max_form_seconds' => (int) env('BOT_MAX_FORM_SECONDS', 7200),

    /** Hidden field names — all must remain empty. */
    'honeypot_fields' => ['honeypot', 'website', 'url'],

    /** Obvious automation / scraper user-agent patterns. */
    'blocked_user_agent_patterns' => [
        '/^curl\//i',
        '/python-requests/i',
        '/scrapy/i',
        '/headlesschrome/i',
        '/phantomjs/i',
        '/selenium/i',
        '/puppeteer/i',
        '/^wget\//i',
        '/libwww-perl/i',
        '/go-http-client/i',
    ],

    'contact' => [
        'ip_max_attempts' => (int) env('BOT_CONTACT_IP_MAX', 3),
        'ip_decay_seconds' => (int) env('BOT_CONTACT_IP_DECAY', 3600),
        'email_max_attempts' => (int) env('BOT_CONTACT_EMAIL_MAX', 2),
        'email_decay_seconds' => (int) env('BOT_CONTACT_EMAIL_DECAY', 3600),
    ],

    'register' => [
        'ip_max_attempts' => (int) env('BOT_REGISTER_IP_MAX', 5),
        'ip_decay_seconds' => (int) env('BOT_REGISTER_IP_DECAY', 3600),
    ],

];
