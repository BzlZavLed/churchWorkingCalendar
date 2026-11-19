<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProviderUsage
{
    public function report(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'UTC');
        $end = min($start->addMonth()->timestamp, now()->timestamp);
        if ($end <= $start->timestamp) {
            return ['meta' => ['status' => 'future'], 'openai' => ['status' => 'future']];
        }

        return [
            'meta' => $this->cached('meta', $month, fn () => $this->meta($start->timestamp, $end)),
            'openai' => $this->cached('openai', $month, fn () => $this->openai($start->timestamp, $end)),
        ];
    }

    private function cached(string $provider, string $month, callable $fetch): array
    {
        // Credentials are never placed in cache keys or returned to the browser.
        $identity = $provider === 'meta'
            ? [config('whatsapp.business_account_id'), config('whatsapp.graph_version'), config('whatsapp.access_token')]
            : [config('assistant.openai_admin_key')];
        $key = 'provider-usage:'.$provider.':'.$month.':'.hash('sha256', json_encode($identity));
        return Cache::remember($key, now()->addMinutes(30), function () use ($fetch) {
            try {
                $result = $fetch();
            } catch (Throwable $e) {
                // Do not expose provider response bodies, tokens, URLs, or member data.
                $result = ['status' => 'unavailable'];
            }
            $result['checked_at'] = now()->toIso8601String();
            if ($result['status'] === 'ok') $result['updated_at'] = $result['checked_at'];
            return $result;
        });
    }

    private function meta(int $start, int $end): array
    {
        $token = config('whatsapp.access_token');
        $account = config('whatsapp.business_account_id');
        $version = config('whatsapp.graph_version');
        if (!$token || !$account || !$version) return ['status' => 'not_configured'];

        $url = "https://graph.facebook.com/$version/$account/pricing_analytics";
        $params = ['start' => $start, 'end' => $end, 'granularity' => 'DAILY', 'metric_types' => json_encode(['VOLUME', 'COST'])];
        $volume = 0; $cost = 0; $hasCost = false;
        for ($page = 0; $page < 20; $page++) {
            $response = Http::withToken($token)->connectTimeout(3)->timeout(8)->get($url, $params);
            if (!$response->successful()) return ['status' => in_array($response->status(), [401, 403]) || in_array($response->json('error.code'), [10, 190, 200]) ? 'permission_required' : 'unavailable'];
            $rows = $response->json('data');
            if (!is_array($rows)) throw new RuntimeException('Invalid analytics format');
            foreach ($rows as $row) {
                $points = $row['data_points'] ?? (isset($row['start']) ? [$row] : null);
                if (!is_array($points)) throw new RuntimeException('Invalid analytics format');
                foreach ($points as $point) {
                    if (!isset($point['volume']) || !is_numeric($point['volume'])) throw new RuntimeException('Missing volume');
                    $volume += $point['volume'];
                    if (isset($point['cost']) && is_numeric($point['cost'])) {
                        $cost += $point['cost']; $hasCost = true;
                    }
                }
            }
            if (!$response->json('paging.next')) {
                return ['status' => 'ok', 'messages' => $volume, 'cost' => $hasCost ? round($cost, 6) : null];
            }
            // Follow cursors on our fixed host, never a provider-supplied URL containing a token.
            $params['after'] = $response->json('paging.cursors.after');
            if (!$params['after']) throw new RuntimeException('Missing cursor');
        }
        throw new RuntimeException('Incomplete analytics');
    }

    private function openai(int $start, int $end): array
    {
        $key = config('assistant.openai_admin_key');
        if (!$key) return ['status' => 'admin_key_required'];
        $totals = ['requests' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'costs' => []];
        foreach (['usage/completions', 'costs'] as $endpoint) {
            $params = ['start_time' => $start, 'end_time' => $end, 'bucket_width' => '1d', 'limit' => 31];
            for ($page = 0; $page < 20; $page++) {
                $response = Http::withToken($key)->connectTimeout(3)->timeout(8)->get("https://api.openai.com/v1/organization/$endpoint", $params);
                if (!$response->successful()) return ['status' => in_array($response->status(), [401, 403]) ? 'permission_required' : 'unavailable'];
                if (!is_array($response->json('data'))) throw new RuntimeException('Invalid usage format');
                foreach ($response->json('data') as $bucket) {
                    if (!is_array($bucket['results'] ?? null)) throw new RuntimeException('Invalid bucket');
                    foreach ($bucket['results'] as $row) {
                        if ($endpoint === 'costs') {
                            $currency = $row['amount']['currency'] ?? null;
                            $amount = $row['amount']['value'] ?? null;
                            if (!$currency || !is_numeric($amount)) throw new RuntimeException('Invalid cost');
                            $totals['costs'][$currency] = ($totals['costs'][$currency] ?? 0) + $amount;
                        } else {
                            foreach (['num_model_requests' => 'requests', 'input_tokens' => 'input_tokens', 'output_tokens' => 'output_tokens'] as $field => $target) {
                                if (!is_numeric($row[$field] ?? null)) throw new RuntimeException('Invalid usage');
                                $totals[$target] += $row[$field];
                            }
                        }
                    }
                }
                if (!$response->json('has_more')) break;
                $params['page'] = $response->json('next_page');
                if (!$params['page'] || $page === 19) throw new RuntimeException('Incomplete usage');
            }
        }
        return ['status' => 'ok'] + $totals;
    }
}
