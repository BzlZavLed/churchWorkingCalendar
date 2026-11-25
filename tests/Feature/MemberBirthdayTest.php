<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMember;
use App\Models\MemberTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberBirthdayTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        Http::preventStrayRequests();
        $church = Church::create(['name' => 'Birthday church']);
        $field = (string) Str::uuid();
        $template = MemberTemplate::create(['church_id' => $church->id, 'version' => 1, 'fields' => [['id' => $field, 'label' => 'Birthday', 'type' => 'dob', 'required' => false, 'options' => []]]]);
        $user = User::factory()->create(['church_id' => $church->id, 'role' => 'secretary']);
        Sanctum::actingAs($user);

        return [$church, $template, $field, $user];
    }

    private function member($church, $template, $field, $date, $name = 'Birthday Person'): void
    {
        ChurchMember::create(['church_id' => $church->id, 'member_template_id' => $template->id, 'name' => $name, 'email' => Str::uuid().'@example.test', 'phone' => '+15555550100', 'data' => [$field => $date]]);
    }

    private function query(array $overrides = []): array
    {
        return array_replace(['frequency' => 'monthly', 'date' => '2027-02-15', 'timezone' => 'America/New_York'], $overrides);
    }

    public function test_clerk_access_and_birthday_scope_with_leap_day_and_missing_dates(): void
    {
        [$church,$template,$field] = $this->context();
        $this->getJson('/api/members')->assertOk();
        $this->member($church, $template, $field, '2000-02-29', 'Leap Day');
        $this->member($church, $template, $field, null, 'Missing');
        $other = Church::create(['name' => 'Other']);
        $this->member($other, $template, $field, '2000-02-01', 'Private Other');
        $this->getJson('/api/members/birthdays?'.http_build_query($this->query()))->assertOk()->assertJsonCount(1, 'members')->assertJsonPath('members.0.name', 'Leap Day')->assertJsonPath('members.0.birthday', '2027-02-28')->assertJsonPath('missing_dates', 1)->assertJsonPath('from', '2027-02-01')->assertJsonPath('to', '2027-02-28')->assertJsonMissing(['name' => 'Private Other']);
        $this->postJson('/api/members', ['template_id' => $this->getJson('/api/members')->json('template.id'), 'name' => 'Clerk Added', 'email' => 'clerkadded@example.test', 'phone' => '+15555550101', 'data' => ['BornOn' => '1990-02-03']])->assertOk();
    }

    public function test_legacy_birthdays_follow_church_date_format_and_iso_dates_keep_their_meaning(): void
    {
        [$church, $template, $field] = $this->context();
        $this->member($church, $template, $field, '12-11-1998', 'Legacy Date');
        $this->member($church, $template, $field, '1998-12-11', 'ISO Date');
        $december = '/api/members/birthdays?'.http_build_query($this->query(['date' => '2027-12-15']));
        $november = '/api/members/birthdays?'.http_build_query($this->query(['date' => '2027-11-15']));

        $this->getJson($december)->assertOk()->assertJsonCount(2, 'members');
        $this->putJson('/api/secretary/integrations', [
            'member_date_format' => 'dmy', 'whatsapp_enabled' => false, 'ai_enabled' => false,
        ])->assertOk()->assertJsonPath('member_date_format', 'dmy');
        $this->getJson($december)->assertOk()->assertJsonPath('members.0.name', 'ISO Date')->assertJsonCount(1, 'members');
        $this->getJson($november)->assertOk()->assertJsonPath('members.0.birthday', '2027-11-12')->assertJsonPath('members.0.name', 'Legacy Date');
    }

    public function test_bulk_correction_changes_only_matching_imported_birth_dates_once(): void
    {
        [$church, $template] = $this->context();
        $member = ChurchMember::create([
            'church_id' => $church->id, 'member_template_id' => $template->id, 'source_person_id' => '123',
            'name' => 'Imported Member', 'email' => 'imported@example.test', 'phone' => '+15555550100', 'data' => ['BornOn' => '1998-11-12', 'AddressLine' => 'Example Street'],
        ]);
        $url = '/api/members/birthdate-correction';
        $file = fn () => \Illuminate\Http\UploadedFile::fake()->createWithContent('members.csv', "PersonId,BornOn\n123,12/11/98\n456,1998-15-06\n");
        $preview = $this->post($url, ['file' => $file()], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('rows.0.status', 'ready')->assertJsonPath('rows.0.corrected', '1998-12-11')
            ->assertJsonPath('rows.1.status', 'unmatched');
        $this->assertSame('1998-11-12', $member->fresh()->data['BornOn']);
        $payload = fn () => ['file' => $file(), 'preview_token' => $preview->json('preview_token')];
        $this->post($url, $payload(), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame('1998-12-11', $member->fresh()->data['BornOn']);
        $this->assertSame('Example Street', $member->fresh()->data['AddressLine']);
        $this->post($url, $payload(), ['Accept' => 'application/json'])->assertConflict();
        $this->post($url, ['file' => $file()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('rows.0.status', 'unchanged');
    }

    public function test_daily_weekly_biweekly_and_year_boundary(): void
    {
        [$church,$template,$field] = $this->context();
        $this->member($church, $template, $field, '1990-12-30', 'December');
        $this->member($church, $template, $field, '1991-01-02', 'January');
        $this->member($church, $template, $field, '1990-01-10', 'Later');
        foreach (['daily' => 1, 'weekly' => 2, 'biweekly' => 3] as $frequency => $count) {
            $this->getJson('/api/members/birthdays?'.http_build_query($this->query(['frequency' => $frequency, 'date' => '2026-12-30'])))->assertOk()->assertJsonCount($count, 'members');
        }
    }

    public function test_multiple_dob_fields_require_selection_and_cross_church_field_is_rejected(): void
    {
        [$church,$template,$field] = $this->context();
        $second = (string) Str::uuid();
        $template->update(['fields' => array_merge($template->fields, [['id' => $second, 'type' => 'dob', 'label' => 'Other date', 'required' => false, 'options' => []]])]);
        $this->getJson('/api/members/birthdays?'.http_build_query($this->query()))->assertOk()->assertJsonPath('field_id', null)->assertJsonCount(0, 'members');
        $this->getJson('/api/members/birthdays?'.http_build_query($this->query(['field_id' => (string) Str::uuid()])))->assertUnprocessable();
    }

    public function test_ai_receives_no_member_data_and_app_appends_all_names_with_hard_limits(): void
    {
        [$church,$template,$field] = $this->context();
        $this->member($church, $template, $field, '1990-02-10', 'Ana Secret');
        $this->member($church, $template, $field, '1990-02-20', 'Luis Secret');
        config(['assistant.openai_api_key' => 'test', 'assistant.openai_model' => 'test-model', 'assistant.birthday_monthly_limit' => 1]);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '¡A celebrar! 🎉']]]]])]);
        $reply = $this->postJson('/api/members/birthdays/message', $this->query(['locale' => 'es']))->assertOk()->json('message');
        $this->assertStringContainsString('Ana Secret', $reply);
        $this->assertStringContainsString('Luis Secret', $reply);
        Http::assertSent(fn ($r) => $r['store'] === false && ! str_contains($r->body(), 'Secret') && ! str_contains($r->body(), '1990') && $r['max_output_tokens'] === 400);
        $this->postJson('/api/members/birthdays/message', $this->query())->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_department_denied_and_readonly_role_cannot_use_paid_generation(): void
    {
        [$church,$template,$field,$user] = $this->context();
        $user->update(['role' => 'member']);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/members/birthdays?'.http_build_query($this->query()))->assertForbidden();
        $role = \App\Models\ChurchRole::create(['church_id' => $church->id, 'key' => 'reader', 'name' => 'Reader', 'base_role' => 'pastor', 'permissions' => ['members' => 'read']]);
        $user->update(['role' => 'pastor', 'church_role_id' => $role->id]);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/members/birthdays?'.http_build_query($this->query()))->assertOk();
        $this->postJson('/api/members/birthdays/message',$this->query())->assertForbidden();
        Http::assertNothingSent();
    }
}
