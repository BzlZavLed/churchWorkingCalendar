<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\Transport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WhatsAppHoldWarning implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public function __construct(public int $accountId, public int $eventId, public string $expires)
    {
        $this->onConnection('database')->onQueue('whatsapp');
    }

    public function handle(): void
    {
        $account = WhatsAppAccount::find($this->accountId);
        $event = Event::find($this->eventId);
        if (! $account || ! $event || ($account->state['hold_id'] ?? null) !== $event->id || $event->status !== 'hold' || ! $event->expires_at?->isFuture() || $event->expires_at->toIso8601String() !== $this->expires) {
            return;
        }
        if (! app(\App\Services\ModuleAccess::class)->write($account->user, 'calendar')) {
            return;
        }
        app(Transport::class)->send($account, 'Tu reserva vence en menos de un minuto. Escribe EXTENDER para otros cinco minutos o CONFIRMAR para enviar. Si no respondes, el horario se libera.');
    }
}
