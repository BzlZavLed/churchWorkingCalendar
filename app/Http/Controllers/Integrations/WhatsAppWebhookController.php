<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $expected = (string) config('whatsapp.verify_token');
        // PHP normalizes dots in query parameter names to underscores.
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        abort_unless($expected !== '' && is_string($token) && hash_equals($expected, $token), 403);
        abort_unless($mode === 'subscribe' && is_string($challenge) && preg_match('/^[0-9]{1,128}$/D', $challenge), 400);

        return response($challenge, 200, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }

    public function receive(Request $request)
    {
        $secret = (string) config('whatsapp.app_secret');
        abort_if(strlen($request->getContent()) > 262144, 413);
        abort_unless($secret !== '' && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Hub-Signature-256')), 403);
        if (! config('whatsapp.enabled')) {
            return response()->json(['received' => true]);
        }
        if ($request->input('object') !== 'whatsapp_business_account') {
            return response()->json(['received' => true]);
        }
        foreach ($request->input('entry', []) as $entry) {
            if ((string) ($entry['id'] ?? '') !== (string) config('whatsapp.business_account_id')) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (($change['field'] ?? '') !== 'messages' || (string) ($value['metadata']['phone_number_id'] ?? '') !== (string) config('whatsapp.phone_number_id')) {
                    continue;
                }
                foreach ($value['messages'] ?? [] as $message) {
                    // Pilot supports phone-addressed text only. Ignore media and status callbacks.
                    $sender = $message['from'] ?? '';
                    $text = $message['text']['body'] ?? '';
                    $id = $message['id'] ?? '';
                    if (($message['type'] ?? '') !== 'text' || ! is_string($sender) || ! preg_match('/^[0-9]{7,15}$/D', $sender) || ! is_string($text) || strlen($text) > 4000 || ! is_string($id) || strlen($id) > 255 || $id === '') {
                        continue;
                    }
                    $sent = filter_var($message['timestamp'] ?? null, FILTER_VALIDATE_INT);
                    if (! $sent || $sent < now()->subHours(23)->timestamp || $sent > now()->addMinutes(5)->timestamp) {
                        continue;
                    }
                    app(\App\Services\WhatsApp\Inbox::class)->accept($id, $sender, $text, $sent);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
