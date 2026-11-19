<?php

namespace App\Http\Controllers;

use App\Services\MemberBirthdays;
use App\Services\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MemberBirthdayController extends Controller
{
    private function roster(Request $request): array
    {
        $user = $request->user();
        abort_unless($user?->church_id && app(ModuleAccess::class)->read($user, 'members'), 403);
        $input = $request->validate(['frequency' => 'required|in:daily,weekly,biweekly,monthly', 'date' => 'required|date_format:Y-m-d', 'timezone' => 'required|timezone', 'field_id' => 'nullable|uuid', 'locale' => 'sometimes|in:es,en']);

        return app(MemberBirthdays::class)->roster($user->church_id, $input);
    }

    public function index(Request $request)
    {
        return $this->roster($request);
    }

    public function generate(Request $request)
    {
        abort_unless($request->user()->hasCapability('members.manage'), 403);
        $roster = $this->roster($request);
        abort_unless(count($roster['members']), 422, 'No birthdays in this period.');
        abort_unless($roster['ai_available'], 422, 'Configure OPENAI_API_KEY and OPENAI_MODEL before generating with AI.');
        // Hard caps, reserved before the call. Failures also count; no network retry.
        $permitted = DB::transaction(function () use ($request) {
            $limits = [
                'birthdays:month:'.$request->user()->church_id.':'.now()->format('Y-m') => (int) config('assistant.birthday_monthly_limit', 100),
                'birthdays:day:'.$request->user()->id.':'.now()->format('Y-m-d') => (int) config('assistant.birthday_daily_limit', 10),
            ];
            foreach ($limits as $bucket => $limit) {
                DB::table('whatsapp_usage')->insertOrIgnore(['bucket' => $bucket, 'used' => 0]);
                if (DB::table('whatsapp_usage')->where('bucket', $bucket)->lockForUpdate()->value('used') >= $limit) {
                    return false;
                }
            }
            foreach ($limits as $bucket => $limit) {
                DB::table('whatsapp_usage')->where('bucket', $bucket)->increment('used');
            }

            return true;
        });
        abort_unless($permitted, 429, 'Birthday message generation limit reached. You can still edit or copy a message.');
        $locale = $request->input('locale', 'es');
        try {
            $response = Http::withToken(config('assistant.openai_api_key'))->connectTimeout(5)->timeout(25)->post('https://api.openai.com/v1/responses', [
                'model' => config('assistant.openai_model'), 'store' => false, 'max_output_tokens' => 400,
                'instructions' => 'Write a warm, joyful, playful birthday greeting for a church WhatsApp group. Vary your wording. Use 2-3 tasteful emojis, no age jokes, invented personal facts, names, dates or Bible quotations. Output only the greeting, at most 90 words. The application appends the actual birthday names separately.',
                'input' => json_encode(['language' => $locale === 'es' ? 'Spanish' : 'English', 'people_count' => count($roster['members'])]),
            ]);
            abort_unless($response->successful() && $response->json('status') === 'completed', 502, 'AI generation is unavailable. Try again later or write your own greeting.');
            $greeting = '';
            foreach ($response->json('output', []) as $item) {
                foreach ($item['content'] ?? [] as $content) {
                    if (($content['type'] ?? '') === 'output_text') {
                        $greeting .= $content['text'];
                    }
                }
            }
            abort_unless(trim($greeting) !== '', 502, 'No greeting returned. Write your own greeting or try again later.');
        } catch (\Illuminate\Http\Client\ConnectionException) {
            abort(502, 'AI generation timed out. You can write your own greeting.');
        }
        $names = implode("\n", array_map(fn ($m) => '🎂 '.$m['name'], $roster['members']));

        return ['message' => trim($greeting)."\n\n".$names];
    }
}
