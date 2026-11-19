<?php

namespace Tests\Feature;

use App\Models\{Church, User};
use App\Services\ProviderUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Http};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 11, 19));
        config(['whatsapp.access_token' => 'test-meta-secret', 'whatsapp.business_account_id' => '123', 'whatsapp.graph_version' => 'v25.0', 'assistant.openai_admin_key' => null]);
    }

    public function test_meta_pages_are_totalled_cached_and_shared_without_using_normal_openai_key(): void
    {
        config(['assistant.openai_api_key' => 'normal-key']);
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [['data_points' => [['start' => 1, 'volume' => 10, 'cost' => 0.5]]]], 'paging' => ['next' => 'https://untrusted.example/?access_token=secret', 'cursors' => ['after' => 'page2']]])
            ->push(['data' => [['data_points' => [['start' => 2, 'volume' => 20, 'cost' => 1.25]]]]])]);
        $service = app(ProviderUsage::class);
        $report = $service->report('2026-11');
        $this->assertSame(30, $report['meta']['messages']);
        $this->assertSame(1.75, $report['meta']['cost']);
        $this->assertSame('admin_key_required', $report['openai']['status']);
        $this->assertArrayHasKey('updated_at', $report['meta']);
        $this->assertSame($report, $service->report('2026-11'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://graph.facebook.com/v25.0/123/pricing_analytics') && ($request['after'] ?? null) === 'page2');
        $this->assertStringNotContainsString('secret', json_encode($report));
    }

    public function test_openai_aggregates_usage_and_cost_currencies(): void
    {
        config(['whatsapp.access_token' => null, 'assistant.openai_admin_key' => 'admin-secret']);
        Http::fake([
            'api.openai.com/v1/organization/usage/completions*' => Http::sequence()
                ->push(['data' => [['results' => [['num_model_requests' => 2, 'input_tokens' => 100, 'output_tokens' => 30]]]], 'has_more' => true, 'next_page' => 'second'])
                ->push(['data' => [['results' => [['num_model_requests' => 1, 'input_tokens' => 50, 'output_tokens' => 10]]]], 'has_more' => false]),
            'api.openai.com/v1/organization/costs*' => Http::response(['data' => [['results' => [['amount' => ['currency' => 'usd', 'value' => 0.1]], ['amount' => ['currency' => 'usd', 'value' => 0.2]]]]], 'has_more' => false]),
        ]);
        $report = app(ProviderUsage::class)->report('2026-11')['openai'];
        $this->assertSame('ok', $report['status']);
        $this->assertSame(3, $report['requests']);
        $this->assertSame(150, $report['input_tokens']);
        $this->assertSame(40, $report['output_tokens']);
        $this->assertEqualsWithDelta(0.3, $report['costs']['usd'], 0.000001);
        Http::assertSentCount(3);
    }

    public function test_failures_are_safe_and_do_not_claim_zero_cost(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'sensitive-provider-detail']], 400)]);
        $report = app(ProviderUsage::class)->report('2026-11');
        $this->assertSame('permission_required', $report['meta']['status']);
        $this->assertArrayNotHasKey('cost', $report['meta']);
        $this->assertArrayNotHasKey('updated_at', $report['meta']);
        $this->assertStringNotContainsString('sensitive', json_encode($report));
    }

    public function test_only_superadmin_can_read_reports_and_future_months_send_no_requests(): void
    {
        $church = Church::create(['name' => 'Test']);
        $url = "/api/superadmin/churches/{$church->id}/integrations/providers";
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id]));
        $this->getJson($url.'?month=2026-11')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'superadmin']));
        $this->getJson($url.'?month=invalid')->assertUnprocessable();
        $this->getJson($url.'?month=2027-01')->assertOk()->assertJsonPath('meta.status', 'future');
        Http::assertNothingSent();
    }

    public function test_incomplete_response_is_not_a_successful_zero_report(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [['unexpected' => true]]])]);
        $this->assertSame('unavailable', app(ProviderUsage::class)->report('2026-11')['meta']['status']);
    }
}
