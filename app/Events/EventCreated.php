<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Realtime broadcasting is temporarily disabled.
class EventCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Event $event)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('calendar.global')];
    }

    public function broadcastAs(): string
    {
        return 'event.created';
    }
}
