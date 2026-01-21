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

        return response()->json($users);
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

    public function storeUser(Request $request)
    {
        $churchId = $request->user()?->church_id;
        if (!$churchId) {
            abort(403, 'Church required.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,member,secretary'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

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

    public function updateUser(Request $request, User $user)
    {
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
            'role' => ['sometimes', 'in:admin,member,secretary'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

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

        $user->update($data);

        return response()->json($user);
    }

    public function updateDepartment(Request $request, Department $department)
    {
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
            'is_club' => ['nullable', 'boolean'],
        ]);

        $department->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'is_club' => $data['is_club'] ?? $department->is_club,
        ]);

        return response()->json($department);
    }

    public function destroyUser(Request $request, User $user)
    {
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
