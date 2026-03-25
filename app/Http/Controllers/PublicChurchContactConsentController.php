<?php

namespace App\Http\Controllers;

use App\Models\ChurchContact;
use Illuminate\Http\Request;

class PublicChurchContactConsentController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : '';

        if ($email === '' && $phone === '') {
            return response()->json([
                'message' => 'Email or phone is required.',
                'errors' => [
                    'lookup' => ['Email or phone is required.'],
                ],
            ], 422);
        }

        $contacts = ChurchContact::query()
            ->with('church:id,name')
            ->when($email !== '' && $phone !== '', function ($query) use ($email, $phone) {
                $query->where('email', $email)
                    ->where('phone', $phone);
            })
            ->when($email !== '' && $phone === '', function ($query) use ($email) {
                $query->where('email', $email);
            })
            ->when($phone !== '' && $email === '', function ($query) use ($phone) {
                $query->where('phone', $phone);
            })
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (ChurchContact $contact) {
                return [
                    'token' => $contact->consent_token,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'sms_consent' => $contact->sms_consent,
                    'email_consent' => $contact->email_consent,
                    'church' => $contact->church,
                ];
            })
            ->values();

        return response()->json([
            'status' => 'ok',
            'contacts' => $contacts,
        ]);
    }

    public function show(string $token)
    {
        $contact = ChurchContact::query()
            ->where('consent_token', $token)
            ->with('church:id,name')
            ->first();

        if ($contact === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'contact' => [
                'token' => $contact->consent_token,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'sms_consent' => $contact->sms_consent,
                'email_consent' => $contact->email_consent,
            ],
            'church' => $contact->church,
        ]);
    }

    public function revoke(Request $request, string $token)
    {
        $contact = ChurchContact::query()
            ->where('consent_token', $token)
            ->first();

        if ($contact === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $data = $request->validate([
            'withdraw_sms' => ['required', 'boolean'],
            'withdraw_email' => ['required', 'boolean'],
        ]);

        if ($data['withdraw_sms']) {
            $contact->sms_consent = false;
            $contact->sms_consented_at = null;
        }

        if ($data['withdraw_email']) {
            $contact->email_consent = false;
            $contact->email_consented_at = null;
        }

        $contact->save();

        return response()->json([
            'status' => 'ok',
            'contact' => [
                'token' => $contact->consent_token,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'sms_consent' => $contact->sms_consent,
                'email_consent' => $contact->email_consent,
            ],
        ]);
    }
}
