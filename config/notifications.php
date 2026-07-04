<?php

return [

  'email_alerts_enabled' => env('ALERT_EMAIL_ENABLED', true),

  'whatsapp_alerts_enabled' => env('ALERT_WHATSAPP_ENABLED', false),

  'whatsapp' => [
      'provider' => env('WHATSAPP_PROVIDER', 'twilio'),
      'twilio_account_sid' => env('TWILIO_ACCOUNT_SID'),
      'twilio_auth_token' => env('TWILIO_AUTH_TOKEN'),
      'twilio_from' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
  ],

];
