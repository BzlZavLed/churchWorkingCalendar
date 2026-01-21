<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WebAuthnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class WebAuthnController extends Controller
{
    public function registerOptions(Request $request, WebAuthnService $service)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $options = $service->createRegistrationOptions($user);
        Cache::put($this->registerCacheKey($user->id), $options, now()->addMinutes(10));

        return response()->json(['publicKey' => $options]);
    }

    public function registerVerify(Request $request, WebAuthnService $service)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'credential' => ['required', 'array'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        $cacheKey = $this->registerCacheKey($user->id);
        $options = Cache::pull($cacheKey);
        if (!$options) {
            throw ValidationException::withMessages([
                'credential' => ['Registration has expired. Please try again.'],
            ]);
        }

        $service->verifyRegistration($data['credential'], $options);

        if (!empty($data['device_name']) && isset($data['credential']['id'])) {
            \App\Models\WebAuthnCredential::query()
                ->where('credential_id', $data['credential']['id'])
                ->update(['name' => $data['device_name']]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => $user->load('department'),
            'token' => $token,
        ]);
    }

    public function authenticateOptions(Request $request, WebAuthnService $service)
    {
        $data = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $user = null;
        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                throw ValidationException::withMessages([
                    'email' => ['User not found.'],
                ]);
            }
        }

        $options = $service->createAuthenticationOptions($user);
        if ($user && empty($options['allowCredentials'] ?? [])) {
            return response()->json([
                'message' => 'No Face ID registration found.',
                'status' => 'no_credentials',
            ], 404);
        }

        $optionsId = $this->generateOptionsId();
        Cache::put($this->authCacheKey($optionsId), $options, now()->addMinutes(10));

        return response()->json([
            'publicKey' => $options,
            'options_id' => $optionsId,
        ]);
    }

    public function authenticateVerify(Request $request, WebAuthnService $service)
    {
        $data = $request->validate([
            'options_id' => ['required', 'string'],
            'credential' => ['required', 'array'],
        ]);

        $cacheKey = $this->authCacheKey($data['options_id']);
        $options = Cache::pull($cacheKey);
        if (!$options) {
            throw ValidationException::withMessages([
                'credential' => ['Authentication has expired. Please try again.'],
            ]);
        }

        $source = $service->verifyAuthentication($data['credential'], $options);
        $userId = (int) ($source->userHandle ?? 0);
        $user = $userId ? User::find($userId) : null;
        if (!$user && isset($data['credential']['id'])) {
            $credentialRecord = \App\Models\WebAuthnCredential::query()
                ->where('credential_id', $data['credential']['id'])
                ->first();
            if ($credentialRecord) {
                $user = User::find($credentialRecord->user_id);
            }
        }
        if (!$user) {
            throw ValidationException::withMessages([
                'credential' => ['User not found.'],
            ]);
        }
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => $user->load('department'),
            'token' => $token,
        ]);
    }

    private function registerCacheKey(int $userId): string
    {
        return "webauthn.register.{$userId}";
    }

    private function authCacheKey(string $optionsId): string
    {
        return "webauthn.auth.{$optionsId}";
    }

    private function generateOptionsId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
