<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WhatsAppStatus extends Command
{
    protected $signature = 'whatsapp:status';

    protected $description = 'Check WhatsApp pilot configuration without revealing credentials or making API calls';

    public function handle(): int
    {
        $settings = [
            'WHATSAPP_CHURCH_ID' => 'whatsapp.church_id',
            'WHATSAPP_PHONE_NUMBER_ID' => 'whatsapp.phone_number_id',
            'WHATSAPP_BUSINESS_ACCOUNT_ID' => 'whatsapp.business_account_id',
            'WHATSAPP_ACCESS_TOKEN' => 'whatsapp.access_token',
            'WHATSAPP_APP_SECRET' => 'whatsapp.app_secret',
            'WHATSAPP_VERIFY_TOKEN' => 'whatsapp.verify_token',
            'WHATSAPP_GRAPH_VERSION' => 'whatsapp.graph_version',
            'OPENAI_API_KEY' => 'assistant.openai_api_key',
            'OPENAI_MODEL' => 'assistant.openai_model',
        ];
        $missing = false;
        $rows = [];
        foreach ($settings as $name => $key) {
            $present = filled(config($key));
            $missing = $missing || ! $present;
            $rows[] = [$name, $present ? 'Configured' : 'Missing'];
        }
        $this->table(['Setting', 'Status'], $rows);
        $this->line('Monthly AI / reply limits: '.config('whatsapp.monthly_ai_limit').' / '.config('whatsapp.monthly_replies_limit'));
        $this->line('Daily per-user limit (each operation type): '.config('whatsapp.daily_user_limit'));
        $this->line('Enable flag: '.(config('whatsapp.enabled') ? 'ON' : 'OFF'));
        $this->warn('Configuration check only: credentials are not validated. No provider requests were made. Verify the database queue worker and perform a test-number smoke test before live use.');

        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
