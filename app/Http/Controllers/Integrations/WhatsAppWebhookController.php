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
}
