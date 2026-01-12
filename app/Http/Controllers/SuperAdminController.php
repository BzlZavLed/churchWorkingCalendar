<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    public function index(Request $request)
    {
        $churches = Church::query()
            ->with('latestInvitation')
            ->latest()
            ->paginate(50);

        return response()->json($churches);
    }

    public function storeChurch(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'conference_name' => ['nullable', 'string', 'max:255'],
            'pastor_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
            'invite_role' => ['nullable', 'in:admin,member,secretary'],
            'invite_email' => ['nullable', 'email'],
            'invite_expires_at' => ['nullable', 'date'],
            'invite_max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($data, $request) {
            $church = Church::create([
                'name' => $data['name'],
                'slug' => Church::generateUniqueSlug($data['name']),
                'conference_name' => $data['conference_name'] ?? null,
                'pastor_name' => $data['pastor_name'] ?? null,
                'address' => $data['address'] ?? null,
                'ethnicity' => $data['ethnicity'] ?? null,
            ]);

            $invitation = Invitation::create([
                'code' => Invitation::generateCode(),
                'church_id' => $church->id,
                'department_id' => null,
                'role' => $data['invite_role'] ?? User::ROLE_ADMIN,
                'email' => $data['invite_email'] ?? null,
                'expires_at' => $data['invite_expires_at'] ?? null,
                'max_uses' => $data['invite_max_uses'] ?? 1,
                'created_by' => $request->user()->id,
            ]);

            return [
                'church' => $church,
                'invitation' => $invitation,
            ];
        });

        return response()->json($result, 201);
    }

    public function update(Request $request, Church $church)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'conference_name' => ['nullable', 'string', 'max:255'],
            'pastor_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'name' => $data['name'],
            'conference_name' => $data['conference_name'] ?? $church->conference_name,
            'pastor_name' => $data['pastor_name'] ?? $church->pastor_name,
            'address' => $data['address'] ?? $church->address,
            'ethnicity' => $data['ethnicity'] ?? $church->ethnicity,
        ];

        if ($data['name'] !== $church->name) {
            $payload['slug'] = Church::generateUniqueSlug($data['name'], $church->id);
        }

        $church->update($payload);

        return response()->json($church);
    }

    public function destroy(Church $church)
    {
        DB::transaction(function () use ($church) {
            $church->delete();
        });

        return response()->noContent();
    }

    public function generateInvite(Request $request, Church $church)
    {
        $data = $request->validate([
            'invite_role' => ['nullable', 'in:admin,member,secretary'],
            'invite_email' => ['nullable', 'email'],
            'invite_expires_at' => ['nullable', 'date'],
            'invite_max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        $invitation = Invitation::create([
            'code' => Invitation::generateCode(),
            'church_id' => $church->id,
            'department_id' => null,
            'role' => $data['invite_role'] ?? User::ROLE_ADMIN,
            'email' => $data['invite_email'] ?? null,
            'expires_at' => $data['invite_expires_at'] ?? null,
            'max_uses' => $data['invite_max_uses'] ?? 1,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($invitation, 201);
    }

    public function listDepartments(Church $church)
    {
        $departments = Department::query()
            ->where('church_id', $church->id)
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    public function storeDepartment(Request $request, Church $church)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'is_club' => ['nullable', 'boolean'],
        ]);

        $department = Department::create([
            'church_id' => $church->id,
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'is_club' => $data['is_club'] ?? false,
        ]);

        return response()->json($department, 201);
    }

    public function updateDepartment(Request $request, Church $church, Department $department)
    {
        if ($department->church_id !== $church->id) {
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

    public function destroyDepartment(Church $church, Department $department)
    {
        if ($department->church_id !== $church->id) {
            abort(404);
        }

        $department->delete();

        return response()->noContent();
    }

    public function listUsers(Church $church)
    {
        $users = User::query()
            ->where('church_id', $church->id)
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function storeUser(Request $request, Church $church)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,member,secretary'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        if (!empty($data['department_id'])) {
            $department = Department::find($data['department_id']);
            if ($department === null || $department->church_id !== $church->id) {
                return response()->json([
                    'message' => 'Department must belong to the selected church.',
                ], 422);
            }
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'church_id' => $church->id,
            'department_id' => $data['department_id'] ?? null,
        ]);

        return response()->json($user, 201);
    }

    public function updateUser(Request $request, Church $church, User $user)
    {
        if ($user->church_id !== $church->id) {
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
            if ($department === null || $department->church_id !== $church->id) {
                return response()->json([
                    'message' => 'Department must belong to the selected church.',
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

    public function destroyUser(Church $church, User $user)
    {
        if ($user->church_id !== $church->id) {
            abort(404);
        }

        $user->delete();

        return response()->noContent();
    }
}
