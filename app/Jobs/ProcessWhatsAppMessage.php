<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\Access;
use App\Services\WhatsApp\Conversation;
use App\Services\WhatsApp\Transport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

    public $timeout = 65;

    public function __construct(public int $messageId, public int $accountId)
    {
        $this->onConnection('database')->onQueue('whatsapp');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('whatsapp:'.$this->accountId))->shared()->releaseAfter(5)->expireAfter(80)];
    }

    public function handle(): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        $account = WhatsAppAccount::find($this->accountId);
        if (! $message || ! $account || $message->account_id !== $account->id) {
            return;
        }
        if (! WhatsAppMessage::whereKey($message->id)->where('status', 'pending')->update(['status' => 'processing'])) {
            return;
        }
        if ($message->generation !== $account->generation || $message->created_at->lt(now()->subMinutes(10)) || ! app(Access::class)->allowed($account)) {
            $message->update(['status' => 'ignored', 'body' => null]);

            return;
        }
        try {
            $reply = app(Conversation::class)->reply($account, $message->body);
            $message->update(['status' => 'processed', 'body' => null]);
            if ($reply !== null && ! app(Transport::class)->send($account, $reply)) {
                $message->update(['status' => 'reply_failed']);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message->update(['status' => 'rejected', 'body' => null]);
            app(Transport::class)->send($account, implode("\n", array_merge(...array_values($e->errors()))));
        } catch (\Throwable $e) {
            // Do not log provider response bodies, secrets, or visitor text.
            $message->update(['status' => 'failed', 'body' => null]);
            app(Transport::class)->send($account, 'No pude completar la solicitud. Revisa tus permisos o intenta más tarde. Para comprobar un envío, escribe ESTADO.');
        }
    }
}
