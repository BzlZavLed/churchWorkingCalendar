<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuperAdminWebController extends Controller
{
    public function showLogin()
    {
        return view('superadmin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['Superadmin access required.'],
            ]);
        }

        return redirect()->route('superadmin.churches.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }

    public function index(Request $request)
    {
        $churches = Church::query()->latest()->paginate(20);

        return view('superadmin.churches.index', [
            'churches' => $churches,
        ]);
    }

    public function create()
    {
        return view('superadmin.churches.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'invite_role' => ['nullable', 'in:admin,manager,member,secretary'],
            'invite_email' => ['nullable', 'email'],
            'invite_expires_at' => ['nullable', 'date'],
            'invite_max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = DB::transaction(function () use ($data, $request) {
            $church = Church::create([
                'name' => $data['name'],
                'slug' => Church::generateUniqueSlug($data['name']),
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

        return redirect()
            ->route('superadmin.churches.index')
            ->with('invite_code', $result['invitation']->code);
    }

    public function edit(Church $church)
    {
        return view('superadmin.churches.edit', [
            'church' => $church,
        ]);
    }

    public function update(Request $request, Church $church)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $payload = ['name' => $data['name']];
        if ($data['name'] !== $church->name) {
            $payload['slug'] = Church::generateUniqueSlug($data['name'], $church->id);
        }
        $church->update($payload);

        return redirect()->route('superadmin.churches.index');
    }

    public function destroy(Church $church)
    {
        DB::transaction(function () use ($church) {
            $church->delete();
        });

        return redirect()->route('superadmin.churches.index');
    }

    public function generateInvite(Request $request, Church $church)
    {
        $data = $request->validate([
            'invite_role' => ['nullable', 'in:admin,manager,member,secretary'],
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

        return redirect()
            ->route('superadmin.churches.index')
            ->with('invite_code', $invitation->code);
    }
}
