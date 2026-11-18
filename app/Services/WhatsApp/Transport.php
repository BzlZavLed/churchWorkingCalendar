<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;

class Transport
{
    public function send(WhatsAppAccount $account, string $text): bool
    {
        if (! app(Access::class)->allowed($account)) {
            return false;
        }
        $account = $account->fresh();
        if (! $account || ! $account->last_received_at || $account->last_received_at->lt(now()->subHours(23))) {
            return false;
        }
        $version = (string) config('whatsapp.graph_version');
        $phone = (string) config('whatsapp.phone_number_id');
        if (! preg_match('/^v[0-9]+\.[0-9]+$/D', $version) || ! ctype_digit($phone) || ! config('whatsapp.access_token')) {
            return false;
        }
        if (! app(Access::class)->consume($account, 'replies')) {
            return false;
        }
        // No automatic retry: a timeout may mean Meta accepted the message already.
        try {
            return Http::withToken(config('whatsapp.access_token'))->connectTimeout(5)->timeout(15)
                ->post("https://graph.facebook.com/$version/$phone/messages", [
                    'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $account->sender,
                    'type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 4000)],
                ])->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
