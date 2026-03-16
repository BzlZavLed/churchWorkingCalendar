<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Realtime broadcasting is temporarily disabled.
class DomainUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $update)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('app.updates')];
    }

    public function broadcastAs(): string
    {
        return 'domain.updated';
    }
}
