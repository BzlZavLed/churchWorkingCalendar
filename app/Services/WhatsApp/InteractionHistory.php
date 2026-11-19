<?php
namespace App\Services\WhatsApp;

use App\Models\{WhatsAppAccount, WhatsAppInteraction};

class InteractionHistory
{
    public function record(WhatsAppAccount $account, string $direction, string $text, string $status, ?int $messageId = null): WhatsAppInteraction
    {
        $user = $account->user()->firstOrFail();
        // Never archive a reusable linking command.
        $text = preg_replace('/VINCULAR\s+[a-f0-9]{32}/i', 'VINCULAR [código oculto]', $text);
        return WhatsAppInteraction::create([
            'user_id' => $user->id, 'church_id' => $user->church_id,
            'direction' => $direction, 'content' => mb_substr($text, 0, 4000),
            'status' => $status, 'message_id' => $messageId,
        ]);
    }
}
