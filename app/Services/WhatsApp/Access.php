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

        return config('whatsapp.enabled') && $user && $user->whatsapp_assistant_enabled
         && $user->church_id && (string) $user->church_id === (string) config('whatsapp.church_id')
         && $current?->sender_hash === $account->sender_hash && $current?->generation === $account->generation && $account->sender_hash;
    }

    public function consume(WhatsAppAccount $account, string $kind): bool
    {
        if (! $this->allowed($account)) {
            return false;
        }

        // Durable reservations before network calls. Failed/ambiguous calls still count.
        return DB::transaction(function () use ($account, $kind) {
            $buckets = ["global:$kind:".now()->format('Y-m') => (int) config("whatsapp.monthly_{$kind}_limit"),
                "user:{$account->user_id}:$kind:".now()->format('Y-m-d') => (int) config('whatsapp.daily_user_limit')];
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
