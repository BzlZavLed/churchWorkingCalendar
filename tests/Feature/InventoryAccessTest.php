<?php

namespace Tests\Feature;

use App\Models\{Church, Department, InventoryItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_clerk_and_superadmin_clerk_context_can_only_read_their_church_inventory(): void
    {
        $church = Church::create(['name' => 'Selected']);
        $other = Church::create(['name' => 'Other']);
        $music = Department::create(['name' => 'Music', 'church_id' => $church->id]);
        $youth = Department::create(['name' => 'Youth', 'church_id' => $church->id]);
        $outside = Department::create(['name' => 'Outside', 'church_id' => $other->id]);
        $item = InventoryItem::create(['department_id' => $music->id, 'description' => 'Piano', 'quantity' => 1]);
        InventoryItem::create(['department_id' => $youth->id, 'description' => 'Tent', 'quantity' => 2]);
        InventoryItem::create(['department_id' => $outside->id, 'description' => 'Private', 'quantity' => 1]);

        foreach (['secretary', 'superadmin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'church_id' => $church->id, 'department_id' => null]));
            $headers = $role === 'superadmin' ? ['X-Church-Context' => (string) $church->id] : [];
            $this->getJson('/api/inventory', $headers)->assertOk()->assertJsonCount(2)->assertJsonMissing(['description' => 'Private']);
            $this->postJson('/api/inventory', ['department_id' => $music->id, 'description' => 'Blocked'], $headers)->assertUnprocessable();
            $this->putJson('/api/inventory/'.$item->id, ['description' => 'Changed'], $headers)->assertUnprocessable();
            $this->deleteJson('/api/inventory/'.$item->id, [], $headers)->assertUnprocessable();
        }
        $this->assertDatabaseCount('inventory_items', 3);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'description' => 'Piano']);
    }
}
