<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicChurchContactConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_consent_link_can_be_viewed(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $contact = ChurchContact::create([
            'church_id' => $church->id,
            'name' => 'John Visitor',
            'phone' => '555-1010',
            'email' => 'john@example.com',
            'sms_consent' => true,
            'email_consent' => true,
        ]);

        $response = $this->getJson("/api/public/consent/{$contact->consent_token}");

        $response->assertOk()
            ->assertJsonPath('contact.name', 'John Visitor')
            ->assertJsonPath('contact.sms_consent', true)
            ->assertJsonPath('contact.email_consent', true)
            ->assertJsonPath('church.name', 'Main Church');
    }

    public function test_public_search_can_find_contact_by_email(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        ChurchContact::create([
            'church_id' => $church->id,
            'name' => 'John Visitor',
            'email' => 'john@example.com',
            'sms_consent' => false,
            'email_consent' => true,
        ]);

        $response = $this->getJson('/api/public/consent/search?email=john@example.com');

        $response->assertOk()
            ->assertJsonPath('contacts.0.name', 'John Visitor')
            ->assertJsonPath('contacts.0.email_consent', true);
    }

    public function test_public_search_requires_phone_or_email(): void
    {
        $response = $this->getJson('/api/public/consent/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lookup']);
    }

    public function test_public_consent_link_can_withdraw_sms_and_email(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $contact = ChurchContact::create([
            'church_id' => $church->id,
            'name' => 'John Visitor',
            'phone' => '555-1010',
            'email' => 'john@example.com',
            'sms_consent' => true,
            'sms_consented_at' => now(),
            'email_consent' => true,
            'email_consented_at' => now(),
        ]);

        $response = $this->postJson("/api/public/consent/{$contact->consent_token}/revoke", [
            'withdraw_sms' => true,
            'withdraw_email' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('contact.sms_consent', false)
            ->assertJsonPath('contact.email_consent', false);

        $this->assertDatabaseHas('church_contacts', [
            'id' => $contact->id,
            'sms_consent' => false,
            'email_consent' => false,
        ]);
    }
}
