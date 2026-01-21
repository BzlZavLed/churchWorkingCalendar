<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return InventoryItem::query()
                ->with(['department.church'])
                ->orderBy('description')
                ->get();
        }

        if ($user->isSecretary()) {
            return InventoryItem::query()
                ->whereHas('department', function ($query) use ($user) {
                    $query->where('church_id', $user->church_id);
                })
                ->with('department.church')
                ->orderBy('description')
                ->get();
        }

        if ($user->isAdmin()) {
            if (!$user->department_id) {
                return response()->json([], 200);
            }
            return InventoryItem::query()
                ->where('department_id', $user->department_id)
                ->with('department.church')
                ->orderBy('description')
                ->get();
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $departmentId = $this->resolveDepartmentId($user, $request->input('department_id'));

        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::create([
            'department_id' => $departmentId,
            'quantity' => $data['quantity'] ?? 1,
            'value' => $data['value'] ?? null,
            'total_value' => $this->calculateTotalValue($data['quantity'] ?? 1, $data['value'] ?? null),
            'description' => $data['description'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'location' => $data['location'] ?? null,
        ]);

        return response()->json($item->load('department'), 201);
    }

    public function update(Request $request, InventoryItem $inventory)
    {
        $user = $request->user();
        $this->authorizeInventoryMutation($user, $inventory);

        $data = $request->validate([
            'department_id' => ['sometimes', 'exists:departments,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['sometimes', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string'],
        ]);

        if (array_key_exists('department_id', $data) && !$user->isSuperAdmin()) {
            unset($data['department_id']);
        }

        $quantity = array_key_exists('quantity', $data) ? $data['quantity'] : $inventory->quantity;
        $value = array_key_exists('value', $data) ? $data['value'] : $inventory->value;
        $data['total_value'] = $this->calculateTotalValue($quantity, $value);

        $inventory->update($data);

        return response()->json($inventory->fresh('department'));
    }

    public function destroy(Request $request, InventoryItem $inventory)
    {
        $user = $request->user();
        $this->authorizeInventoryMutation($user, $inventory);

        $inventory->delete();

        return response()->noContent();
    }

    private function resolveDepartmentId(User $user, ?int $requestedDepartmentId): int
    {
        if ($user->isSuperAdmin()) {
            if (!$requestedDepartmentId) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department is required.'],
                ]);
            }
            return $requestedDepartmentId;
        }

        if ($user->isAdmin()) {
            if (!$user->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department is required.'],
                ]);
            }
            return $user->department_id;
        }

        throw ValidationException::withMessages([
            'department_id' => ['Unauthorized.'],
        ]);
    }

    private function authorizeInventoryMutation(User $user, InventoryItem $inventory): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isAdmin() && $user->department_id === $inventory->department_id) {
            return;
        }

        throw ValidationException::withMessages([
            'inventory' => ['Unauthorized.'],
        ]);
    }

    private function calculateTotalValue(?int $quantity, $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $qty = $quantity ?? 0;
        return number_format(((float) $value) * $qty, 2, '.', '');
    }
}
