<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessWhatsAppMessage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;

class Inbox
{
    public function accept(string $id, string $sender, string $text, int $sent): void
    {
        DB::transaction(function () use ($id, $sender, $text, $sent) {
            if (WhatsAppMessage::where('provider_id', $id)->exists()) {
                return;
            }
            $originalText = $text;
            $hash = WhatsAppAccount::senderHash($sender);
            $account = WhatsAppAccount::where('sender_hash', $hash)->lockForUpdate()->first();
            if (!$account && app(\App\Services\ChurchIntegrations::class)->whatsapp((int)config('whatsapp.church_id'))) {
                $pending=WhatsAppAccount::where('identity_hash',$hash)->whereNotNull('registered_sender')->lockForUpdate()->first();
                if($pending && $pending->user?->whatsapp_assistant_enabled && (string)$pending->user->church_id===(string)config('whatsapp.church_id')) {
                    $account=$pending;
                    $account->update(['sender_hash'=>$hash,'sender'=>$sender,'link_hash'=>null,'link_expires_at'=>null,'state'=>[]]);
                    if(!in_array(mb_strtoupper(trim($text)),['STOP','BAJA','DESACTIVAR'],true)) $text='AYUDA';
                }
            }
            if (! $account && preg_match('/^VINCULAR ([a-f0-9]{32})$/iD', trim($text), $match)) {
                $account = WhatsAppAccount::where('link_hash', hash('sha256', strtolower($match[1])))->where('link_expires_at', '>', now())->lockForUpdate()->first();
                if (! $account || !app(\App\Services\ChurchIntegrations::class)->whatsapp($account->user?->church_id) || ! $account->user->whatsapp_assistant_enabled || (string) $account->user->church_id !== (string) config('whatsapp.church_id')) {
                    return;
                }
                if(WhatsAppAccount::where('identity_hash',$hash)->where('id','!=',$account->id)->exists()) return;
                $account->update(['identity_hash'=>$hash,'registered_sender'=>null,'sender_hash' => $hash, 'sender' => $sender, 'link_hash' => null, 'link_expires_at' => null, 'state' => []]);
                $text = 'AYUDA';
            }
            if (! $account || ! app(Access::class)->allowed($account)) {
                return;
            }
            if (preg_match('/^VINCULAR /i', trim($text))) {
                $text = 'AYUDA';
            }
            $account->update(['last_received_at' => max($account->last_received_at?->timestamp ?? 0, $sent)]);
            if (in_array(mb_strtoupper(trim($text)), ['STOP', 'BAJA', 'DESACTIVAR'], true)) {
                app(InteractionHistory::class)->record($account, 'incoming', $text, 'disabled');
                $account->increment('generation');
                WhatsAppMessage::where('account_id', $account->id)->where('status', 'pending')->update(['status' => 'ignored', 'body' => null]);
                $account->user->forceFill(['whatsapp_assistant_enabled' => false])->save();
                $account->update(['state' => [], 'link_hash' => null, 'link_expires_at' => null]);

                return;
            }
            if (! app(Access::class)->consume($account, 'inbox')) {
                return;
            }
            $message = WhatsAppMessage::firstOrCreate(['provider_id' => $id], ['account_id' => $account->id, 'generation' => $account->generation, 'body' => $text]);
            if ($message->wasRecentlyCreated) {
                app(InteractionHistory::class)->record($account, 'incoming', $originalText, 'received', $message->id);
                ProcessWhatsAppMessage::dispatch($message->id, $account->id)->afterCommit();
            }
        });
    }
}
