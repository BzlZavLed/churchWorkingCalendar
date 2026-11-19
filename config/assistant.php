<?php

return [
    'birthday_monthly_limit' => (int) env('BIRTHDAY_AI_MONTHLY_LIMIT', 100),
    'birthday_daily_limit' => (int) env('BIRTHDAY_AI_DAILY_LIMIT', 10),
    'openai_api_key' => env('OPENAI_API_KEY'),
    // Select and evaluate a cost-efficient tool-capable model before enabling.
    'openai_model' => env('OPENAI_MODEL'),
];
