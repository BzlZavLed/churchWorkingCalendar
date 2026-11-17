<?php

namespace App\Http\Controllers;

use App\Models\{BudgetEntry, BudgetPublication, Church, Department, DepartmentBudget, DepartmentBudgetPlan, InventoryItem, User};
use App\Support\BudgetMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    private const PERIOD = 'continuous';
    private const AMOUNT = ['required', 'numeric', 'min:0', 'max:999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];

    private function churchId(Request $request): int
    {
        abort_unless($request->user()->church_id, 403, 'Select a church work context first.');
        return $request->user()->church_id;
    }

    private function publisher(Request $request): bool
    {
        $user = $request->user();
        return $user->hasCapability('budget.publish');
    }

    private function ownDepartment(Request $request): Department
    {
        abort_unless($request->user()->hasCapability('budget.manage'), 403);
        return Department::where('church_id', $this->churchId($request))->findOrFail($request->user()->department_id);
    }

    private function hasTreasurer(int $churchId): bool
    {
        return User::where('church_id', $churchId)->get()->contains(fn ($user) => $user->hasCapability('budget.publish'));
    }

    private function selfManagement(Church $church): bool
    {
        return (bool) $church->budget_self_management || !$this->hasTreasurer($church->id);
    }

    private function authorizeFunding(Request $request, Church $church): void
    {
        abort_unless($this->publisher($request) || $this->selfManagement($church), 403,
            'Treasury manages expected budgets, starting balances and income. / Tesorería administra los presupuestos esperados, saldos iniciales e ingresos.');
    }

    public function settings(Request $request)
    {
        abort_unless($this->publisher($request), 403);
        $data = $request->validate(['self_management' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data) {
            $church = Church::whereKey($this->churchId($request))->lockForUpdate()->firstOrFail();
            $church->budget_self_management = $data['self_management'];
            $church->save();
        });
        return response()->json(['self_management' => $data['self_management']]);
    }

    public function plan(Request $request)
    {
        $canPublish = $this->publisher($request);
        $ownDepartment = $canPublish ? null : $this->ownDepartment($request);
        $data = $request->validate(['department_id' => ['required', 'integer'], 'year' => ['required', 'integer', 'between:2000,2100'], 'amount' => self::AMOUNT]);
        $churchId = $this->churchId($request);
        Department::where('church_id', $churchId)->findOrFail($data['department_id']);
        abort_if(!$canPublish && $ownDepartment->id !== (int) $data['department_id'], 403);
        return DB::transaction(function () use ($request, $data, $churchId) {
            $church = Church::whereKey($churchId)->lockForUpdate()->firstOrFail();
            $this->authorizeFunding($request, $church);
            return DepartmentBudgetPlan::updateOrCreate(
                ['department_id' => $data['department_id'], 'year' => $data['year']],
                ['expected_cents' => BudgetMoney::cents($data['amount']), 'updated_by' => $request->user()->id]
            );
        });
    }

    public function index(Request $request)
    {
        $request->validate(['year' => ['sometimes', 'integer', 'between:2000,2100']]);
        $year = (int) $request->input('year', now('America/New_York')->year);
        $churchId = $this->churchId($request);
        $church = Church::findOrFail($churchId);
        $user = $request->user();
        $canPublish = $this->publisher($request);
        $all = $user->isSecretary() || $user->isSuperAdmin() || $canPublish || (!$user->department_id && app(\App\Services\ModuleAccess::class)->read($user,'budget'));
        abort_unless($all || $user->department_id, 403);
        $departments = Department::where('church_id', $churchId)->when(!$all, fn ($query) => $query->whereKey($user->department_id))->orderBy('name')->get();
        $budgets = DepartmentBudget::whereIn('department_id', $departments->pluck('id'))->where('period', self::PERIOD)
            ->with(['entries' => fn ($query) => $query->with(['author:id,name', 'inventoryItem'])->orderByDesc('occurred_on')->orderByDesc('id')])->get()->keyBy('department_id');
        $plans = DepartmentBudgetPlan::whereIn('department_id', $departments->pluck('id'))->where('year', $year)->get()->keyBy('department_id');
        return response()->json([
            'year' => $year,
            'self_management' => (bool) $church->budget_self_management,
            'has_treasurer' => $this->hasTreasurer($churchId),
            'can_self_fund' => $canPublish || $this->selfManagement($church),
            'period' => self::PERIOD, 'periods' => [['value' => self::PERIOD, 'label' => 'Continuous']],
            'can_view_all' => $all, 'can_publish' => $canPublish && app(\App\Services\ModuleAccess::class)->write($user,'budget'), 'writable_department_id' => $user->hasCapability('budget.manage') && app(\App\Services\ModuleAccess::class)->write($user,'budget') ? $user->department_id : null,
            'departments' => $departments->map(function ($department) use ($budgets, $plans) {
                $budget = $budgets->get($department->id);
                if ($budget) {
                    $budget->income_cents = $budget->entries->where('kind', 'income')->sum('amount_cents');
                    $budget->expense_cents = $budget->entries->where('kind', 'expense')->sum('amount_cents');
                    $budget->balance_cents = $budget->opening_cents + $budget->income_cents - $budget->expense_cents;
                }
                return [...$department->toArray(), 'budget' => $budget, 'expected_cents' => $plans->get($department->id)?->expected_cents];
            }),
        ]);
    }

    public function start(Request $request)
    {
        $department = $this->ownDepartment($request);
        $data = $request->validate(['amount' => self::AMOUNT]);
        return DB::transaction(function () use ($request, $department, $data) {
            $church = Church::whereKey($department->church_id)->lockForUpdate()->firstOrFail();
            $this->authorizeFunding($request, $church);
            abort_if(DepartmentBudget::where('department_id', $department->id)->where('period', self::PERIOD)->exists(), 409, 'A starting balance already exists. Refresh the budget.');
            $budget = DepartmentBudget::create(['department_id' => $department->id, 'period' => self::PERIOD, 'opening_cents' => BudgetMoney::cents($data['amount']), 'opening_source' => 'department', 'created_by' => $request->user()->id]);
            $budget->entries()->create(['kind' => 'opening', 'amount_cents' => $budget->opening_cents, 'description' => 'Starting balance / Saldo inicial', 'occurred_on' => now()->toDateString(), 'created_by' => $request->user()->id]);
            return response()->json($budget, 201);
        });
    }

    public function entry(Request $request)
    {
        $department = $this->ownDepartment($request);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'kind' => ['required', 'in:income,expense'], 'amount' => [...self::AMOUNT, 'gt:0'], 'description' => ['required', 'string', 'max:255'], 'occurred_on' => ['required', 'date_format:Y-m-d'],
            'inventory' => ['nullable', 'array:description,quantity,value,brand,model,serial_number,location'],
            'inventory.description' => ['required_with:inventory', 'string', 'max:255'],
            'inventory.quantity' => ['required_with:inventory', 'integer', 'min:1', 'max:100000'],
            'inventory.value' => ['required_with:inventory', 'numeric', 'min:0.01', 'max:999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'inventory.brand' => ['nullable', 'string', 'max:255'],
            'inventory.model' => ['nullable', 'string', 'max:255'],
            'inventory.serial_number' => ['nullable', 'string', 'max:255'],
            'inventory.location' => ['nullable', 'string', 'max:2000'],
        ]);
        $asset = $data['inventory'] ?? null;
        if ($asset && ($data['kind'] !== 'expense' || BudgetMoney::cents($asset['value']) * $asset['quantity'] > BudgetMoney::cents($data['amount']))) {
            throw ValidationException::withMessages(['inventory' => ['Inventory is only available for expenses; its total value cannot exceed the expense. / El inventario solo aplica a gastos y su valor no puede superar el gasto.']]);
        }
        return DB::transaction(function () use ($request, $department, $data, $asset) {
            $church = Church::whereKey($department->church_id)->lockForUpdate()->firstOrFail();
            if ($data['kind'] === 'income') $this->authorizeFunding($request, $church);
            $budget = DepartmentBudget::where('department_id', $department->id)->where('period', self::PERIOD)->first();
            abort_unless($budget, 409, 'Set a starting balance first.');
            $payload = ['department_budget_id' => $budget->id, 'kind' => $data['kind'], 'amount_cents' => BudgetMoney::cents($data['amount']), 'description' => $data['description'], 'occurred_on' => $data['occurred_on'], 'created_by' => $request->user()->id];
            $hash = hash('sha256', json_encode([$payload, $asset]));
            if ($existing = BudgetEntry::where('request_id', $data['request_id'])->first()) {
                abort_if(($existing->request_hash && $existing->request_hash !== $hash) || (!$existing->request_hash && $asset), 409, 'This request was already used for another transaction.');
                foreach ($payload as $key => $value) abort_unless($existing->$key == $value, 409, 'This request was already used for another transaction.');
                return response()->json($existing);
            }
            $item = $asset ? InventoryItem::create($asset + [
                'department_id' => $department->id,
                'total_value' => number_format(BudgetMoney::cents($asset['value']) * $asset['quantity'] / 100, 2, '.', ''),
            ]) : null;
            return response()->json(BudgetEntry::create($payload + ['request_id' => $data['request_id'], 'request_hash' => $hash, 'inventory_item_id' => $item?->id]), 201);
        });
    }

    private function allocationRules(string $kind): array
    {
        return ['allocations' => ['required', 'array', 'min:1', 'max:500'], 'allocations.*.department_id' => ['required', 'integer', 'distinct'], 'allocations.*.expected_amount' => ['sometimes', ...self::AMOUNT], 'allocations.*.amount' => $kind === 'income' ? [...self::AMOUNT, 'gt:0'] : self::AMOUNT];
    }

    private function checkDepartments(array $allocations, int $churchId): void
    {
        $ids = array_column($allocations, 'department_id');
        if (Department::where('church_id', $churchId)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['allocations' => ['Every department must belong to your church, with one row per department.']]);
        }
    }

    public function publish(Request $request)
    {
        abort_unless($this->publisher($request), 403);
        $churchId = $this->churchId($request);
        $data = $request->validate(['request_id' => ['required', 'uuid'], 'kind' => ['required', 'in:opening,income'], 'bulk' => ['sometimes', 'boolean'], 'year' => ['required_if:bulk,true', 'integer', 'between:2000,2100'], 'description' => ['required', 'string', 'max:255'], 'occurred_on' => ['required', 'date_format:Y-m-d'], ...$this->allocationRules($request->input('kind', ''))]);
        $bulkOpening = ($data['bulk'] ?? false) && $data['kind'] === 'opening';
        validator($data, ['allocations.*.expected_amount' => $bulkOpening ? self::AMOUNT : ['prohibited']])->validate();
        $this->checkDepartments($data['allocations'], $churchId);
        $fingerprintData = [$request->user()->id, $data['kind'], $data['description'], $data['occurred_on'], $data['allocations']];
        if ($bulkOpening) $fingerprintData[] = $data['year'];
        $fingerprint = hash('sha256', json_encode($fingerprintData));
        return DB::transaction(function () use ($request, $churchId, $data, $fingerprint, $bulkOpening) {
            Church::whereKey($churchId)->lockForUpdate()->firstOrFail();
            if ($existing = BudgetPublication::where('request_id', $data['request_id'])->first()) {
                abort_unless($existing->church_id === $churchId && $existing->payload_hash === $fingerprint, 409, 'This request was already used for another allocation.');
                return response()->json($existing);
            }
            $publication = BudgetPublication::create(['church_id' => $churchId, 'period' => $bulkOpening ? (string) $data['year'] : self::PERIOD, 'request_id' => $data['request_id'], 'payload_hash' => $fingerprint, 'kind' => $data['kind'], 'description' => $data['description'], 'occurred_on' => $data['occurred_on'], 'created_by' => $request->user()->id]);
            foreach ($data['allocations'] as $allocation) {
                if ($bulkOpening) {
                    DepartmentBudgetPlan::updateOrCreate(
                        ['department_id' => $allocation['department_id'], 'year' => $data['year']],
                        ['expected_cents' => BudgetMoney::cents($allocation['expected_amount']), 'updated_by' => $request->user()->id]
                    );
                }
                $budget = DepartmentBudget::where('department_id', $allocation['department_id'])->where('period', self::PERIOD)->first();
                // An additional allocation must never silently replace the department's initial funding.
                if (!$budget && $data['kind'] === 'income') throw ValidationException::withMessages(['allocations' => ['Set a starting balance before publishing additional income for department '.$allocation['department_id'].'.']]);
                $amount = BudgetMoney::cents($allocation['amount']);
                $kind = $data['kind'];
                $recordedAmount = $amount;
                if (!$budget) {
                    $budget = DepartmentBudget::create(['department_id' => $allocation['department_id'], 'period' => self::PERIOD, 'opening_cents' => $amount, 'opening_source' => 'treasury', 'created_by' => $request->user()->id]);
                } elseif ($kind === 'opening') {
                    $kind = 'opening_update';
                    $recordedAmount = $amount - $budget->opening_cents;
                    $budget->update(['opening_cents' => $amount, 'opening_source' => 'treasury']);
                }
                $budget->entries()->create(['budget_publication_id' => $publication->id, 'kind' => $kind, 'amount_cents' => $recordedAmount, 'description' => $data['description'], 'occurred_on' => $data['occurred_on'], 'created_by' => $request->user()->id]);
            }
            return response()->json($publication, 201);
        });
    }

    public function preview(Request $request)
    {
        abort_unless($this->publisher($request), 403);
        $request->validate(['file' => ['required', 'file', 'max:1024'], 'kind' => ['required', 'in:opening,income']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (!$headers) throw ValidationException::withMessages(['file' => ['The CSV is empty.']]);
            $headers = array_map(fn ($value) => trim(ltrim($value, "\xEF\xBB\xBF")), $headers);
            $idIndex = array_search('department_id', $headers, true);
            $amountIndex = array_search('amount', $headers, true);
            $expectedIndex = array_search('expected_amount', $headers, true);
            if ($request->input('kind') === 'opening' && $expectedIndex === false) throw ValidationException::withMessages(['file' => ['Starting-balance CSV requires expected_amount and amount columns.']]);
            if ($request->input('kind') === 'income' && $expectedIndex !== false) throw ValidationException::withMessages(['file' => ['Use the new-income template without expected_amount.']]);
            if ($idIndex === false || $amountIndex === false) throw ValidationException::withMessages(['file' => ['Use the CSV template: department_id,department,amount.']]);
            $allocations = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($row) === 1 && $row[0] === null) continue;
                $amount = trim($row[$amountIndex] ?? '');
                $expected = $expectedIndex === false ? '' : trim($row[$expectedIndex] ?? '');
                if ($amount === '' && $expected === '') continue;
                $allocation = ['department_id' => trim($row[$idIndex] ?? ''), 'amount' => $amount];
                if ($request->input('kind') === 'opening') $allocation['expected_amount'] = $expected;
                $allocations[] = $allocation;
                if (count($allocations) > 500) throw ValidationException::withMessages(['file' => ['Maximum 500 allocations.']]);
            }
        } finally { fclose($handle); }
        validator(['allocations' => $allocations], $this->allocationRules($request->input('kind')))->validate();
        $this->checkDepartments($allocations, $this->churchId($request));
        return response()->json(['allocations' => $allocations]);
    }
}
