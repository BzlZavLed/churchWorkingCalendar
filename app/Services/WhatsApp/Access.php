<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;

class Access
{
    public function allowed(WhatsAppAccount $account): bool
    {
        $user = $account->user()->first();
        $current = $account->fresh();

        return app(\App\Services\ChurchIntegrations::class)->whatsapp($user?->church_id) && config('whatsapp.enabled') && $user && !$user->pending_activation && $user->whatsapp_assistant_enabled
         && $user->church_id && (string) $user->church_id === (string) config('whatsapp.church_id')
         && $current?->sender_hash === $account->sender_hash && $current?->generation === $account->generation && $account->sender_hash;
    }

    public function consume(WhatsAppAccount $account, string $kind): bool
    {
        if (! $this->allowed($account) || ($kind === 'ai' && !app(\App\Services\ChurchIntegrations::class)->ai($account->user->church_id))) {
            return false;
        }

        // Durable reservations before network calls. Failed/ambiguous calls still count.
        return DB::transaction(function () use ($account, $kind) {
            $church=$account->user->church_id;
            $settings=app(\App\Services\ChurchIntegrations::class);
            $buckets = ["church:$church:$kind:".now()->format('Y-m') => $settings->limit($church,"whatsapp_{$kind}_limit",(int)config("whatsapp.monthly_{$kind}_limit")),"global:$kind:".now()->format('Y-m') => (int) config("whatsapp.monthly_{$kind}_limit"),
                "user:{$account->user_id}:$kind:".now()->format('Y-m-d') => $settings->limit($church,'whatsapp_daily_limit',(int)config('whatsapp.daily_user_limit'))];
            foreach ($buckets as $bucket => $limit) {
                DB::table('whatsapp_usage')->insertOrIgnore(['bucket' => $bucket, 'used' => 0]);
                $row = DB::table('whatsapp_usage')->where('bucket', $bucket)->lockForUpdate()->first();
                if ($row->used >= $limit) {
                    return false;
                }
            }
            foreach ($buckets as $bucket => $limit) {
                DB::table('whatsapp_usage')->where('bucket', $bucket)->increment('used');
            }

            return true;
        });
    }
}
