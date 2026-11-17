<?php

namespace App\Http\Controllers;

use App\Models\ChurchContact;
use Illuminate\Http\Request;

class PublicChurchContactConsentController extends Controller
{
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
