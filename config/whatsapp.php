<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Notification Dispatcher Configuration
    |--------------------------------------------------------------------------
    |
    | Enables automated end-of-day daily reconciliation summaries (9:00 PM)
    | sent directly to the store owner's personal WhatsApp phone number.
    |
    | Supported Gateways:
    | - 'meta': Official Meta WhatsApp Business Cloud API (graph.facebook.com)
    | - 'termii': Termii WhatsApp API (Popular for Nigeria / West Africa)
    | - 'twilio': Twilio WhatsApp Messaging API
    | - 'generic_webhook': Custom webhook (Baileys, Evolution API, UltraMsg, etc.)
    | - 'log': Safe local logger (does not make external network requests; ideal for CI/dev)
    |
    */

    'enabled' => env('WHATSAPP_ENABLED', false),

    'gateway' => env('WHATSAPP_GATEWAY', 'log'),

    'meta' => [
        'api_url' => env('WHATSAPP_META_API_URL', 'https://graph.facebook.com/v19.0'),
        'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID', ''),
        'token' => env('WHATSAPP_META_TOKEN', ''),
    ],

    'termii' => [
        'api_url' => env('WHATSAPP_TERMII_API_URL', 'https://api.ng.termii.com/api'),
        'api_key' => env('WHATSAPP_TERMII_API_KEY', ''),
        'device_id' => env('WHATSAPP_TERMII_DEVICE_ID', ''),
    ],

    'twilio' => [
        'sid' => env('WHATSAPP_TWILIO_SID', ''),
        'token' => env('WHATSAPP_TWILIO_TOKEN', ''),
        'from' => env('WHATSAPP_TWILIO_FROM', ''), // e.g., 'whatsapp:+14155238886'
    ],

    'generic_webhook' => [
        'url' => env('WHATSAPP_WEBHOOK_URL', ''),
        'token' => env('WHATSAPP_WEBHOOK_TOKEN', ''),
    ],

    'default_report_time' => env('WHATSAPP_DEFAULT_REPORT_TIME', '21:00'),

    'ssl_verify' => env('WHATSAPP_SSL_VERIFY', true),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'victorious_pos_secret_token_2026'),

];

