<?php

namespace App\Console\Commands;

use App\Events\HoldExpired;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireEventHolds extends Command
{
    protected $signature = 'events:expire-holds';

    protected $description = 'Expire hold events that have passed their expiration time.';

    public function handle(): int
    {
        Event::query()
            ->where('status', 'hold')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($events) {
                foreach ($events as $event) {
                    DB::transaction(function () use ($event) {
                        $event->update([
                            'status' => 'cancelled',
                            'expires_at' => null,
                            'updated_by' => $event->created_by,
                        ]);

                        event(new HoldExpired($event));
                    });
                }
            });

        return self::SUCCESS;
    }
}
