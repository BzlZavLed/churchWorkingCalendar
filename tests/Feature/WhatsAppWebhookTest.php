<?php

namespace Tests\Feature;

use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    public function test_verification_requires_the_configured_token_and_returns_plain_challenge(): void
    {
        config(['whatsapp.verify_token' => 'test-only-token']);
        $this->get('/api/integrations/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test-only-token&hub_challenge=12345')
            ->assertOk()->assertSeeText('12345')->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->get('/api/integrations/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')->assertForbidden();
        $this->get('/api/integrations/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test-only-token&hub_challenge[]=12345')->assertBadRequest();
        config(['whatsapp.verify_token' => '']);
        $this->get('/api/integrations/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=&hub_challenge=12345')->assertForbidden();
    }
}
