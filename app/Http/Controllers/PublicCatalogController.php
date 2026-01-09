<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Department;
use App\Models\Invitation;

class PublicCatalogController extends Controller
{
    public function churches()
    {
        $churches = Church::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json($churches);
    }

    public function departments(Church $church)
    {
        $departments = Department::query()
            ->where('church_id', $church->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'user_name']);

        return response()->json($departments);
    }

    public function invitation(string $code)
    {
        $invitation = Invitation::query()
            ->where('code', $code)
            ->first();

        if (!$invitation) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $isActive = $invitation->revoked_at === null
            && ($invitation->expires_at === null || $invitation->expires_at->isFuture())
            && $invitation->uses_count < $invitation->max_uses;

        return response()->json([
            'status' => $isActive ? 'active' : 'inactive',
            'church' => $invitation->church()->select('id', 'name', 'slug')->first(),
            'department_id' => $invitation->department_id,
        ]);
    }
}
