<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function registerWithInvite(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'invite_code' => ['required', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $invitation = Invitation::where('code', $data['invite_code'])
                ->lockForUpdate()
                ->first();

            if ($invitation === null || !$invitation->isValidForEmail($data['email'])) {
                throw ValidationException::withMessages([
                    'invite_code' => ['Invalid or expired invitation code.'],
                ]);
            }

            if ($invitation->role === User::ROLE_SECRETARY) {
                throw ValidationException::withMessages([
                    'invite_code' => ['Secretary accounts must be created by superadmin.'],
                ]);
            }

            $departmentId = $data['department_id'] ?? null;
            if ($departmentId !== null && $invitation->department_id !== null && $departmentId !== $invitation->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department does not match invitation.'],
                ]);
            }

            if ($departmentId !== null && $invitation->church_id !== null) {
                $departmentChurchId = Department::whereKey($departmentId)->value('church_id');
                if ($departmentChurchId !== $invitation->church_id) {
                    throw ValidationException::withMessages([
                        'department_id' => ['Department must belong to the invited church.'],
                    ]);
                }
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $invitation->role ?? User::ROLE_MEMBER,
                'church_id' => $invitation->church_id,
                'department_id' => $departmentId ?? $invitation->department_id,
            ]);

            $invitation->increment('uses_count');

            $token = $user->createToken('auth')->plainTextToken;

            return [
                'user' => $user->load('department'),
                'token' => $token,
            ];
        });

        return response()->json($result, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user === null || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => $user->load('department'),
            'token' => $token,
        ]);
    }

    public function recoverPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'church_code' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        $invitation = Invitation::where('code', $data['church_code'])->first();
        if ($invitation === null) {
            throw ValidationException::withMessages([
                'church_code' => ['Invalid church code.'],
            ]);
        }

        if ($invitation->revoked_at !== null) {
            throw ValidationException::withMessages([
                'church_code' => ['Invitation is revoked.'],
            ]);
        }

        if ($invitation->expires_at !== null && $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'church_code' => ['Invitation is expired.'],
            ]);
        }

        if ($invitation->email !== null && $invitation->email !== $data['email']) {
            throw ValidationException::withMessages([
                'church_code' => ['Email does not match the invitation.'],
            ]);
        }

        if ($invitation->church_id !== null && $user->church_id !== $invitation->church_id) {
            throw ValidationException::withMessages([
                'church_code' => ['Invitation does not belong to your church.'],
            ]);
        }

        $user->update([
            'password' => $data['password'],
        ]);

        return response()->noContent();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('department'),
        ]);
    }
}
