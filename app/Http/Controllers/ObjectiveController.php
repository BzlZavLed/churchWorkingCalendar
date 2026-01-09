<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ObjectiveController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = Objective::query()->with('department');

        if (!$request->user()->isSuperAdmin()) {
            $query->whereHas('department', function ($departmentQuery) use ($request) {
                $departmentQuery->where('church_id', $request->user()->church_id);
            });
        }

        if (
            !$request->user()->isAdmin()
            && !$request->user()->isSuperAdmin()
            && !$request->user()->isSecretary()
        ) {
            $query->where('department_id', $request->user()->department_id);
        }

        $objectives = $query->orderBy('name')->get();

        return response()->json($objectives);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Objective::class);

        $data = $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'evaluation_metrics' => ['required', 'string'],
        ]);

        $departmentId = $this->resolveDepartmentId($request, $data['department_id'] ?? null);

        $objective = DB::transaction(function () use ($data, $departmentId) {
            return Objective::create([
                'department_id' => $departmentId,
                'name' => $data['name'],
                'description' => $data['description'],
                'evaluation_metrics' => $data['evaluation_metrics'],
            ]);
        });

        return response()->json($objective, 201);
    }

    public function update(Request $request, Objective $objective)
    {
        $this->authorize('update', $objective);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'evaluation_metrics' => ['required', 'string'],
        ]);

        $objective->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'evaluation_metrics' => $data['evaluation_metrics'],
        ]);

        return response()->json($objective);
    }

    public function destroy(Objective $objective)
    {
        $this->authorize('delete', $objective);

        $objective->delete();

        return response()->noContent();
    }

    private function resolveDepartmentId(Request $request, ?int $departmentId): int
    {
        /** @var User $user */
        $user = $request->user();

        $resolved = $departmentId ?? $user->department_id;

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'department_id' => ['Department is required.'],
            ]);
        }

        if (!$user->isSuperAdmin() && !$user->isAdmin() && $resolved !== $user->department_id) {
            throw ValidationException::withMessages([
                'department_id' => ['You cannot create objectives for other departments.'],
            ]);
        }

        if (!$user->isSuperAdmin()) {
            $department = Department::find($resolved);
            if ($department === null || $department->church_id !== $user->church_id) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department must belong to your church.'],
                ]);
            }
        }

        return $resolved;
    }
}
