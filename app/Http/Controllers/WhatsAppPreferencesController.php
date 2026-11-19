<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;

class WhatsAppPreferencesController extends Controller
{
    public function show(Request $request)
    {
        $account = WhatsAppAccount::where('user_id', $request->user()->id)->first();

        return response()->json(['enabled' => (bool) $request->user()->whatsapp_assistant_enabled,
            'system_enabled' => (bool) config('whatsapp.enabled'), 'linked' => (bool) $account?->sender_hash,
            'ready' => $account ? app(\App\Services\WhatsApp\Access::class)->allowed($account) : false,
            'link_block_reason' => $this->linkBlockReason($request),
            'daily_limit' => (int) config('whatsapp.daily_user_limit')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $request->user()->forceFill(['whatsapp_assistant_enabled' => $data['enabled']])->save();
        if (! $data['enabled']) {
            $this->discardPending($request);
        }
        if (! $data['enabled']) {
            WhatsAppAccount::where('user_id', $request->user()->id)->update(['state' => null, 'link_hash' => null, 'link_expires_at' => null]);
        }

        return $this->show($request);
    }

    public function link(Request $request)
    {
        $user = $request->user();
        if ($reason = $this->linkBlockReason($request)) {
            return response()->json(['message' => 'WhatsApp linking is unavailable.', 'reason' => $reason], 403);
        }
        $account = WhatsAppAccount::firstOrCreate(['user_id' => $user->id]);
        abort_if($account->sender_hash, 422, 'Unlink your current number first.');
        $code = bin2hex(random_bytes(16));
        $account->update(['link_hash' => hash('sha256', $code), 'link_expires_at' => now()->addMinutes(10)]);

        return response()->json(['command' => 'VINCULAR '.$code, 'expires_in' => 600])->header('Cache-Control', 'no-store');
    }

    private function linkBlockReason(Request $request): ?string
    {
        $user = $request->user();
        if (! config('whatsapp.enabled')) {
            return 'system_disabled';
        }
        if (! $user->church_id) {
            return 'account_without_church';
        }
        if (! config('whatsapp.church_id')) {
            return 'pilot_not_configured';
        }
        if ((string) $user->church_id !== (string) config('whatsapp.church_id')) {
            return 'church_not_in_pilot';
        }
        if (! $user->whatsapp_assistant_enabled) {
            return 'opt_in_required';
        }

        return null;
    }

    private function discardPending(Request $request): void
    {
        $id = WhatsAppAccount::where('user_id', $request->user()->id)->value('id');
        if ($id) {
            WhatsAppAccount::whereKey($id)->increment('generation');
        }
        if ($id) {
            \App\Models\WhatsAppMessage::where('account_id', $id)->where('status', 'pending')->update(['status' => 'ignored', 'body' => null]);
        }
    }

    public function unlink(Request $request)
    {
        $this->discardPending($request);
        // Keep the account ID and dedupe records; revoke queued work and old codes.
        WhatsAppAccount::where('user_id', $request->user()->id)->update(['sender_hash' => null, 'sender' => null, 'state' => null, 'link_hash' => null, 'link_expires_at' => null]);

        return $this->show($request);
    }
}
