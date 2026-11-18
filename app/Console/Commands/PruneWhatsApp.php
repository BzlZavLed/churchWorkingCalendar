<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Console\Command;

class PruneWhatsApp extends Command
{
    protected $signature = 'whatsapp:prune';

    protected $description = 'Remove stale WhatsApp message contents, drafts and expired linking codes';

    public function handle(): int
    {
        WhatsAppMessage::where('created_at', '<', now()->subDay())->update(['body' => null]);
        WhatsAppMessage::where('created_at', '<', now()->subDays(30))->delete();
        WhatsAppAccount::where('last_received_at', '<', now()->subDay())->update(['state' => null]);
        WhatsAppAccount::where('link_expires_at', '<', now())->update(['link_hash' => null, 'link_expires_at' => null]);
        $this->info('Stale WhatsApp content removed.');

        return self::SUCCESS;
    }
}
