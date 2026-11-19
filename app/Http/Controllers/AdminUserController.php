<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function listDepartments(Request $request)
    {
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        $departments = Department::query()
            ->where('church_id', $churchId)
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function listUsers(Request $request)
    {
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        $users = User::query()
            ->where('church_id', $churchId)
            ->orderBy('name')
            ->get();

        return response()->json($users->load('whatsappAccount')->map(fn($u)=>array_merge($u->makeHidden('whatsappAccount')->toArray(),app(\App\Services\WhatsApp\Provisioning::class)->fields($u))));
    }

    public function listDepartmentsWithUsers(Request $request)
    {
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        $departments = Department::query()
            ->where('church_id', $churchId)
            ->with(['users:id,name,department_id,email,role'])
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function storeUser(Request $request) {
        return \Illuminate\Support\Facades\DB::transaction(function() use ($request) {
            $response=$this->performstoreUser($request);
            if($response->getStatusCode()>=400) return $response;
            $saved=User::findOrFail($response->getData()->id);
            app(\App\Services\WhatsApp\Provisioning::class)->apply($request,$saved);
            return response()->json(array_merge($saved->toArray(),app(\App\Services\WhatsApp\Provisioning::class)->fields($saved)),$response->getStatusCode());
        });
    }

    private function performstoreUser(Request $request)
    {
        abort_unless($request->user()->hasCapability('users.manage') || $request->user()->isSuperAdmin(),403,'Only church administration can manage users.');
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,member,secretary,pastor'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        if (($data['role'] ?? null) === 'pastor' || (isset($user) && $user->isPastor())) {
            abort_unless($request->user()->hasCapability('users.manage') || $request->user()->isSuperAdmin(), 403);
            if (($data['role'] ?? ($user->role ?? null)) === 'pastor') $data['department_id'] = null;
        }

        if (!empty($data['department_id'])) {
            $department = Department::find($data['department_id']);
            if ($department === null || $department->church_id !== $churchId) {
                return response()->json([
                    'message' => 'Department must belong to your church.',
                ], 422);
            }
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'church_id' => $churchId,
            'department_id' => $data['department_id'] ?? null,
        ]);

        return response()->json($user, 201);
    }

    public function updateUser(Request $request, User $user) {
        return \Illuminate\Support\Facades\DB::transaction(function() use ($request, $user) {
            $response=$this->performupdateUser($request, $user);
            if($response->getStatusCode()>=400) return $response;
            $saved=User::findOrFail($response->getData()->id);
            app(\App\Services\WhatsApp\Provisioning::class)->apply($request,$saved);
            return response()->json(array_merge($saved->toArray(),app(\App\Services\WhatsApp\Provisioning::class)->fields($saved)),$response->getStatusCode());
        });
    }

    private function performupdateUser(Request $request, User $user)
    {
        abort_unless($request->user()->hasCapability('users.manage') || $request->user()->isSuperAdmin(),403,'Only church administration can manage users.');
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        if ($user->church_id !== $churchId) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'in:admin,member,secretary,pastor'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        if (($data['role'] ?? null) === 'pastor' || (isset($user) && $user->isPastor())) {
            abort_unless($request->user()->hasCapability('users.manage') || $request->user()->isSuperAdmin(), 403);
            if (($data['role'] ?? ($user->role ?? null)) === 'pastor') $data['department_id'] = null;
        }

        if (!empty($data['department_id'])) {
            $department = Department::find($data['department_id']);
            if ($department === null || $department->church_id !== $churchId) {
                return response()->json([
                    'message' => 'Department must belong to your church.',
                ], 422);
            }
        }

        if (array_key_exists('password', $data) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (isset($data['role']) && $data['role'] !== $user->role) {
            abort_if($user->church_role_id && !$request->user()->isSuperAdmin(), 403, 'Custom role changes require the superadmin role manager.');
            $data['church_role_id'] = null;
        }
        $user->update($data);

        return response()->json($user);
    }

    public function storeDepartment(Request $request)
    {
        abort_unless($request->user()->hasCapability('departments.manage'),403);
        $churchId = $request->user()?->church_id;
        abort_unless($churchId, 403, 'Church required.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'is_treasury' => ['nullable', 'boolean'],
            'is_club' => ['nullable', 'boolean'],
            'is_greeting' => ['nullable', 'boolean'],
        ]);

        $department = Department::create([
            'church_id' => $churchId,
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'is_treasury' => $data['is_treasury'] ?? false,
            'is_club' => $data['is_club'] ?? false,
            'is_greeting' => $data['is_greeting'] ?? false,
        ]);

        return response()->json($department, 201);
    }

    public function updateDepartment(Request $request, Department $department)
    {
        abort_unless($request->user()->hasCapability('departments.manage'),403);
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        if ($department->church_id !== $churchId) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'is_treasury' => ['nullable', 'boolean'],
            'is_club' => ['nullable', 'boolean'],
            'is_greeting' => ['nullable', 'boolean'],
        ]);

        $department->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'is_treasury' => $data['is_treasury'] ?? $department->is_treasury,
            'is_club' => $data['is_club'] ?? $department->is_club,
            'is_greeting' => $data['is_greeting'] ?? $department->is_greeting,
        ]);

        return response()->json($department);
    }

    public function destroyUser(Request $request, User $user)
    {
        abort_unless($request->user()->hasCapability('users.manage') || $request->user()->isSuperAdmin(),403,'Only church administration can manage users.');
        if ($user->isPastor()) abort_unless($request->user()->hasCapability('users.manage') || $request->user()->isSuperAdmin(), 403);
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        if ($user->church_id !== $churchId) {
            abort(404);
        }

        $user->delete();

        return response()->noContent();
    }
}
