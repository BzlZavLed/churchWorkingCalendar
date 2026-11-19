<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;

class Transport
{
    public function send(WhatsAppAccount $account, string $text, ?int $messageId = null): bool
    {
        if (! app(Access::class)->allowed($account)) {
            $this->recordStatus($account, $messageId, 'access_disabled');
            return false;
        }
        $interaction = app(InteractionHistory::class)->record($account, 'outgoing', $text, 'sending');
        $account = $account->fresh();
        if (! $account || ! $account->last_received_at || $account->last_received_at->lt(now()->subHours(23))) {
            $interaction->update(['status' => 'window_expired']);
            $this->recordStatus($account, $messageId, 'window_expired');
            return false;
        }
        $version = (string) config('whatsapp.graph_version');
        $phone = (string) config('whatsapp.phone_number_id');
        if (! preg_match('/^v[0-9]+\.[0-9]+$/D', $version) || ! ctype_digit($phone) || ! config('whatsapp.access_token')) {
            $interaction->update(['status' => 'not_configured']);
            $this->recordStatus($account, $messageId, 'not_configured');
            return false;
        }
        if (! app(Access::class)->consume($account, 'replies')) {
            $interaction->update(['status' => 'reply_limit']);
            $this->recordStatus($account, $messageId, 'reply_limit');
            return false;
        }
        // No automatic retry: a timeout may mean Meta accepted the message already.
        try {
            $response = Http::withToken(config('whatsapp.access_token'))->connectTimeout(5)->timeout(15)
                ->post("https://graph.facebook.com/$version/$phone/messages", [
                    'messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $account->sender,
                    'type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr($text, 0, 4000)],
                ]);
            $sent = $response->successful();
            $code = $response->json('error.code');
            $failure = is_numeric($code) ? 'meta_error_'.(int)$code : 'send_failed';
            $interaction->update(['status' => $sent ? 'sent' : $failure]);
            $this->recordStatus($account, $messageId, $sent ? 'sent' : $failure);
            return $sent;
        } catch (\Throwable) {
            $interaction->update(['status' => 'send_unknown']);
            $this->recordStatus($account, $messageId, 'send_unknown');
            return false;
        }
    }
    private function recordStatus(WhatsAppAccount $account, ?int $id, string $status): void
    {
        if ($id) \App\Models\WhatsAppMessage::whereKey($id)->where('account_id', $account->id)->update(['reply_status' => $status]);
    }
}
