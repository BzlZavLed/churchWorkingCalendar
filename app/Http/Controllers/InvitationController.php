<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invitation::class);

        $invitations = Invitation::query()
            ->when(!$request->user()->isSuperAdmin(), function ($query) use ($request) {
                $query->where('church_id', $request->user()->church_id);
            })
            ->latest()
            ->paginate(50);

        return response()->json($invitations);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Invitation::class);

        $data = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role' => ['nullable', 'in:admin,member,secretary'],
            'email' => ['nullable', 'email'],
            'expires_at' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        $invitation = DB::transaction(function () use ($data, $request) {
            $user = $request->user();
            $churchId = $user->isSuperAdmin() ? ($data['church_id'] ?? null) : $user->church_id;

            if ($churchId === null) {
                throw ValidationException::withMessages([
                    'church_id' => ['Church is required for invitations.'],
                ]);
            }

            if (!empty($data['department_id'])) {
                $department = Department::find($data['department_id']);
                if ($department === null || $department->church_id !== $churchId) {
                    throw ValidationException::withMessages([
                        'department_id' => ['Department must belong to the selected church.'],
                    ]);
                }
            }

            return Invitation::create([
                'code' => Invitation::generateCode(),
                'church_id' => $churchId,
                'department_id' => $data['department_id'] ?? null,
                'role' => $data['role'] ?? User::ROLE_MEMBER,
                'email' => $data['email'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'max_uses' => $data['max_uses'] ?? 1,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($invitation, 201);
    }

    public function revoke(Request $request, Invitation $invitation)
    {
        $this->authorize('revoke', $invitation);

        DB::transaction(function () use ($invitation) {
            $invitation->update([
                'revoked_at' => now(),
            ]);
        });

        return response()->json($invitation);
    }
}
