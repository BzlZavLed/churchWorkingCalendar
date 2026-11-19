<?php

return [
    'monthly_inbox_limit' => (int) env('WHATSAPP_MONTHLY_INBOX_LIMIT', 2000),
    'monthly_ai_limit' => (int) env('WHATSAPP_MONTHLY_AI_LIMIT', 500),
    'monthly_replies_limit' => (int) env('WHATSAPP_MONTHLY_REPLIES_LIMIT', 1000),
    'daily_user_limit' => (int) env('WHATSAPP_DAILY_USER_LIMIT', 30),
    'enabled' => env('WHATSAPP_ENABLED', false),
    'church_id' => env('WHATSAPP_CHURCH_ID'),
    'phone_number' => env('WHATSAPP_PHONE_NUMBER'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    // Set to the supported Graph API version displayed in Meta's API setup.
    'graph_version' => env('WHATSAPP_GRAPH_VERSION'),
];
