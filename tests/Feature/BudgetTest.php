<?php

namespace Tests\Feature;

use App\Models\{Church, Department, DepartmentBudget, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private function setupChurch(): array
    {
        $church = Church::create(['name' => 'Church']);
        $church->budget_self_management = true;
        $church->save();
        $department = Department::create(['church_id' => $church->id, 'name' => 'Music']);
        $treasury = Department::create(['church_id' => $church->id, 'name' => 'Treasury', 'is_treasury' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'church_id' => $church->id, 'department_id' => $department->id]);
        $treasurer = User::factory()->create(['role' => 'admin', 'church_id' => $church->id, 'department_id' => $treasury->id]);
        return [$church, $department, $admin, $treasurer];
    }

    private function allocation(int $departmentId, string $amount, string $kind = 'opening'): array
    {
        return ['request_id' => (string) Str::uuid(), 'kind' => $kind, 'description' => 'Treasury allocation', 'occurred_on' => '2026-11-16', 'allocations' => [['department_id' => $departmentId, 'amount' => $amount]]];
    }

    public function test_balances_carry_forward_and_treasury_only_adjusts_the_opening_difference(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget')->assertOk()->assertJsonPath('departments.0.budget', null);
        $this->postJson('/api/budget/start', ['amount' => '100.10'])->assertCreated();
        $this->postJson('/api/budget/start', ['amount' => '500'])->assertStatus(409);
        $expense = ['request_id' => (string) Str::uuid(), 'kind' => 'expense', 'amount' => '30.05', 'description' => 'Supplies', 'occurred_on' => '2026-11-16'];
        $this->postJson('/api/budget/entries', $expense)->assertCreated();
        $this->postJson('/api/budget/entries', $expense)->assertOk(); // retry must not duplicate
        $this->postJson('/api/budget/entries', array_replace($expense, ['amount' => '1']))->assertStatus(409);
        $this->postJson('/api/budget/entries', array_replace($expense, ['request_id' => (string) Str::uuid(), 'kind' => 'income', 'amount' => '10.00', 'occurred_on' => '2027-01-05']))->assertCreated();
        Sanctum::actingAs($treasurer);
        $allocation = $this->allocation($department->id, '150.10');
        $this->postJson('/api/budget/allocations', $allocation)->assertCreated();
        $this->postJson('/api/budget/allocations', $allocation)->assertOk();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget')->assertJsonPath('departments.0.budget.balance_cents', 13005)->assertJsonPath('departments.0.budget.opening_source', 'treasury');
        Sanctum::actingAs($treasurer);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '80.10'))->assertCreated();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '20.00', 'income'))->assertCreated();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget')->assertJsonPath('departments.0.budget.balance_cents', 8005)->assertJsonPath('departments.0.budget.expense_cents', 3005)->assertJsonPath('departments.0.budget.income_cents', 3000);
        $this->assertDatabaseCount('department_budgets', 1);
    }

    public function test_permissions_and_church_boundaries(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        $otherChurch = Church::create(['name' => 'Other']);
        $outside = Department::create(['church_id' => $otherChurch->id, 'name' => 'Outside']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'))->assertForbidden();
        $this->getJson('/api/budget')->assertJsonCount(1, 'departments')->assertJsonPath('can_publish', false);
        Sanctum::actingAs(User::factory()->create(['church_id' => $church->id, 'department_id' => null, 'role' => 'secretary']));
        $this->getJson('/api/budget')->assertJsonCount(2, 'departments');
        $this->postJson('/api/budget/start', ['amount' => '100'])->assertForbidden();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'))->assertForbidden();
        Sanctum::actingAs($treasurer);
        $this->postJson('/api/budget/allocations', $this->allocation($outside->id, '100'))->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create(['church_id' => $church->id, 'department_id' => $department->id, 'role' => 'member']));
        $this->getJson('/api/budget')->assertOk()->assertJsonPath('writable_department_id', null);
        $this->postJson('/api/budget/start', ['amount' => '100'])->assertForbidden();
    }

    public function test_csv_preview_is_read_only_and_publication_is_atomic(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($treasurer);
        $file = UploadedFile::fake()->createWithContent('budget.csv', "\xEF\xBB\xBFdepartment_id,department,expected_amount,amount\r\n{$department->id},Music,800,250.25\r\n");
        $this->postJson('/api/budget/allocations/preview', ['file' => $file, 'kind' => 'opening'])->assertOk()->assertJsonPath('allocations.0.amount', '250.25');
        $this->assertDatabaseCount('department_budgets', 0);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '250.25'))->assertCreated();
        $payload = $this->allocation($department->id, '20', 'income');
        $payload['allocations'][] = ['department_id' => $treasurer->department_id, 'amount' => '30'];
        $this->postJson('/api/budget/allocations', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('budget_publications', 1);
        $this->assertDatabaseCount('budget_entries', 1);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '1.001'))->assertUnprocessable();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '-10'))->assertUnprocessable();
    }

    public function test_superadmin_context_uses_treasury_permissions(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs(User::factory()->create(['role' => 'superadmin', 'church_id' => null, 'department_id' => null]));
        $headers = ['X-Church-Context' => (string) $church->id];
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'), $headers)->assertForbidden();
        $headers['X-Department-Context'] = (string) $treasurer->department_id;
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'), $headers)->assertCreated();
        $headers['X-Department-Context'] = (string) $department->id;
        $this->getJson('/api/budget', $headers)->assertOk()->assertJsonPath('departments.0.budget.balance_cents', 10000)->assertJsonPath('can_publish', false);
    }
    public function test_treasury_controls_self_funding_but_departments_can_still_record_expenses(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($treasurer);
        $this->putJson('/api/budget/settings', ['self_management' => false])->assertOk();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget')->assertJsonPath('can_self_fund', false);
        $this->postJson('/api/budget/start', ['amount' => '100'])->assertForbidden();
        $this->putJson('/api/budget/settings', ['self_management' => true])->assertForbidden();
        Sanctum::actingAs($treasurer);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'))->assertCreated();
        Sanctum::actingAs($admin);
        $entry = ['request_id' => (string) Str::uuid(), 'kind' => 'income', 'amount' => '10', 'description' => 'Funds', 'occurred_on' => '2026-11-16'];
        $this->postJson('/api/budget/entries', $entry)->assertForbidden();
        $this->postJson('/api/budget/entries', array_replace($entry, ['kind' => 'expense']))->assertCreated();
        Sanctum::actingAs($treasurer);
        $this->putJson('/api/budget/settings', ['self_management' => true])->assertOk();
        Sanctum::actingAs($admin);
        $this->postJson('/api/budget/entries', array_replace($entry, ['request_id' => (string) Str::uuid()]))->assertCreated();
        $this->getJson('/api/budget')->assertJsonPath('departments.0.budget.balance_cents', 10000);
    }

    public function test_no_assigned_treasurer_keeps_department_self_management_available(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        $church->budget_self_management = false;
        $church->save();
        $treasurer->update(['role' => 'member']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget')->assertJsonPath('has_treasurer', false)->assertJsonPath('can_self_fund', true);
        $this->postJson('/api/budget/start', ['amount' => '0'])->assertCreated();
        $this->postJson('/api/budget/entries', ['request_id' => (string) Str::uuid(), 'kind' => 'income', 'amount' => '100', 'description' => 'Funds', 'occurred_on' => '2026-11-16'])->assertCreated();
    }

    public function test_yearly_expected_budget_does_not_change_real_funds_or_other_years(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($treasurer);
        $plan = ['department_id' => $department->id, 'year' => 2026, 'amount' => '800'];
        $this->putJson('/api/budget/plan', $plan)->assertSuccessful();
        $this->assertDatabaseCount('department_budgets', 0);
        $this->putJson('/api/budget/plan', array_replace($plan, ['year' => 2027, 'amount' => '900']))->assertSuccessful();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'))->assertCreated();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '150', 'income'))->assertCreated();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget?year=2026')->assertJsonPath('departments.0.expected_cents', 80000)->assertJsonPath('departments.0.budget.balance_cents', 25000);
        $this->getJson('/api/budget?year=2027')->assertJsonPath('departments.0.expected_cents', 90000)->assertJsonPath('departments.0.budget.balance_cents', 25000);
        $this->putJson('/api/budget/plan', $plan)->assertSuccessful();
        Sanctum::actingAs($treasurer);
        $other = Church::create(['name' => 'Other']);
        $outside = Department::create(['church_id' => $other->id, 'name' => 'Outside']);
        $this->putJson('/api/budget/plan', array_replace($plan, ['department_id' => $outside->id]))->assertNotFound();
    }

    public function test_bulk_starting_allocation_sets_plan_and_cash_and_income_preserves_both(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($treasurer);
        $payload = $this->allocation($department->id, '100') + ['year' => 2026, 'bulk' => true];
        $payload['allocations'][0]['expected_amount'] = '800';
        $this->postJson('/api/budget/allocations', $payload)->assertCreated();
        $this->postJson('/api/budget/allocations', $payload)->assertOk();
        $this->assertDatabaseHas('department_budget_plans', ['department_id' => $department->id, 'year' => 2026, 'expected_cents' => 80000]);
        $this->assertDatabaseHas('department_budgets', ['department_id' => $department->id, 'opening_cents' => 10000]);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '150', 'income') + ['bulk' => true, 'year' => 2026])->assertCreated();
        $bad = $this->allocation($department->id, '150', 'income') + ['bulk' => true, 'year' => 2026];
        $bad['allocations'][0]['expected_amount'] = '999';
        $this->postJson('/api/budget/allocations', $bad)->assertUnprocessable();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100') + ['bulk' => true, 'year' => 2026])->assertUnprocessable();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '800', 'expected') + ['year' => 2026])->assertUnprocessable();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget?year=2026')->assertJsonPath('departments.0.expected_cents', 80000)->assertJsonPath('departments.0.budget.opening_cents', 10000)->assertJsonPath('departments.0.budget.balance_cents', 25000);
        Sanctum::actingAs($treasurer);
        $file = UploadedFile::fake()->createWithContent('start.csv', "department_id,department,expected_amount,amount\n{$department->id},Music,800,0\n");
        $this->postJson('/api/budget/allocations/preview', ['file' => $file, 'kind' => 'opening'])->assertOk()->assertJsonPath('allocations.0.expected_amount', '800')->assertJsonPath('allocations.0.amount', '0');
        $file = UploadedFile::fake()->createWithContent('income.csv', "department_id,department,amount\n{$department->id},Music,150\n");
        $this->postJson('/api/budget/allocations/preview', ['file' => $file, 'kind' => 'income'])->assertOk();
    }

    public function test_expense_inventory_creation_is_atomic_and_retry_safe(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($admin);
        $this->postJson('/api/budget/start', ['amount' => '500'])->assertCreated();
        $expense = ['request_id' => (string) Str::uuid(), 'kind' => 'expense', 'amount' => '100', 'description' => 'Musician', 'occurred_on' => '2026-11-16'];
        $this->postJson('/api/budget/entries', $expense)->assertCreated();
        $this->assertDatabaseCount('inventory_items', 0);
        $expense['request_id'] = (string) Str::uuid();
        $expense['description'] = 'Clarinets';
        $expense['inventory'] = ['description' => 'Clarinet', 'quantity' => 2, 'value' => '50', 'brand' => 'Test', 'location' => 'Music room'];
        $result = $this->postJson('/api/budget/entries', $expense)->assertCreated();
        $itemId = $result->json('inventory_item_id');
        $this->assertNotNull($itemId);
        $this->postJson('/api/budget/entries', $expense)->assertOk();
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseHas('inventory_items', ['id' => $itemId, 'department_id' => $department->id, 'quantity' => 2, 'total_value' => 100]);
        $this->getJson('/api/budget')->assertJsonPath('departments.0.budget.balance_cents', 30000);
        $expense['inventory']['quantity'] = 1;
        $this->postJson('/api/budget/entries', $expense)->assertStatus(409);
        $expense['request_id'] = (string) Str::uuid();
        $expense['inventory']['value'] = '101';
        $this->postJson('/api/budget/entries', $expense)->assertUnprocessable();
        $expense['inventory']['value'] = '50';
        $expense['kind'] = 'income';
        $this->postJson('/api/budget/entries', $expense)->assertUnprocessable();
        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseCount('budget_entries', 3);
        Sanctum::actingAs($treasurer);
        $overview = $this->getJson('/api/budget')->assertOk();
        $music = collect($overview->json('departments'))->firstWhere('id', $department->id);
        $this->assertCount(3, $music['budget']['entries']);
        $inventoryEntry = collect($music['budget']['entries'])->firstWhere('id', $result->json('id'));
        $this->assertSame($itemId, $inventoryEntry['inventory_item']['id']);
        $this->assertNotSame($department->id, $overview->json('writable_department_id'));
        // Unknown API URLs no longer match the SPA GET fallback.
        $this->putJson('/api/budget/entries/'.$result->json('id'), ['amount' => '1'])->assertNotFound();
        $this->assertDatabaseHas('budget_entries', ['id' => $result->json('id'), 'amount_cents' => 10000]);
    }

    public function test_department_expected_budget_requires_self_management_and_own_department(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        $plan = ['department_id' => $department->id, 'year' => 2026, 'amount' => '800'];
        Sanctum::actingAs($admin);
        $this->putJson('/api/budget/plan', $plan)->assertSuccessful();
        $this->putJson('/api/budget/plan', array_replace($plan, ['department_id' => $treasurer->department_id]))->assertForbidden();
        $this->assertDatabaseCount('department_budgets', 0);
        $this->assertDatabaseCount('budget_entries', 0);
        Sanctum::actingAs($treasurer);
        $this->putJson('/api/budget/settings', ['self_management' => false])->assertOk();
        Sanctum::actingAs($admin);
        $this->putJson('/api/budget/plan', array_replace($plan, ['amount' => '900']))->assertForbidden();
        $this->assertDatabaseHas('department_budget_plans', ['department_id' => $department->id, 'expected_cents' => 80000]);
        $treasurer->update(['role' => 'member']);
        $this->putJson('/api/budget/plan', array_replace($plan, ['year' => 2027]))->assertSuccessful();
        Sanctum::actingAs(User::factory()->create(['role' => 'member', 'church_id' => $church->id, 'department_id' => $department->id]));
        $this->putJson('/api/budget/plan', $plan)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id]));
        $this->putJson('/api/budget/plan', $plan)->assertForbidden();
    }

    public function test_clerk_can_read_church_wide_budgets_and_movements_but_cannot_write(): void
    {
        [$church, $department, $admin, $treasurer] = $this->setupChurch();
        Sanctum::actingAs($treasurer);
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'))->assertCreated();
        $this->postJson('/api/budget/allocations', $this->allocation($treasurer->department_id, '200'))->assertCreated();
        $other = Church::create(['name' => 'Other']);
        $outside = Department::create(['church_id' => $other->id, 'name' => 'Private']);
        DepartmentBudget::create(['department_id' => $outside->id, 'period' => 'continuous', 'opening_cents' => 90000, 'opening_source' => 'department']);
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id, 'department_id' => null]));
        $result = $this->getJson('/api/budget')->assertOk()->assertJsonPath('can_view_all', true)->assertJsonPath('can_publish', false)->assertJsonPath('writable_department_id', null)->assertJsonCount(2, 'departments')->assertJsonMissing(['name' => 'Private']);
        $this->assertCount(2, collect($result->json('departments'))->flatMap(fn ($row) => $row['budget']['entries']));
        $this->postJson('/api/budget/start', ['amount' => '100'])->assertForbidden();
        $this->postJson('/api/budget/entries', [])->assertForbidden();
        $this->putJson('/api/budget/plan', [])->assertForbidden();
        $this->putJson('/api/budget/settings', ['self_management' => true])->assertForbidden();
        $this->postJson('/api/budget/allocations', $this->allocation($department->id, '100'))->assertForbidden();
        Sanctum::actingAs($admin);
        $this->getJson('/api/budget')->assertJsonPath('can_view_all', false)->assertJsonCount(1, 'departments');
    }

}
