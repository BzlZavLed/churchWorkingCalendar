<?php

namespace App\Services;

use App\Models\WebAuthnCredential;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnCredentialRepository implements PublicKeyCredentialSourceRepository
{
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $encoded = Base64UrlSafe::encodeUnpadded($publicKeyCredentialId);
        $record = WebAuthnCredential::query()
            ->where('credential_id', $encoded)
            ->first();

        return $record ? $this->decodeSource($record->data) : null;
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userId = (int) $publicKeyCredentialUserEntity->id;
        return WebAuthnCredential::query()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (WebAuthnCredential $record) => $this->decodeSource($record->data))
            ->filter()
            ->values()
            ->all();
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $encodedId = Base64UrlSafe::encodeUnpadded($publicKeyCredentialSource->publicKeyCredentialId);
        $data = json_encode($publicKeyCredentialSource, JSON_THROW_ON_ERROR);

        WebAuthnCredential::query()
            ->updateOrCreate(
                ['credential_id' => $encodedId],
                [
                    'user_id' => (int) $publicKeyCredentialSource->userHandle,
                    'data' => $data,
                    'counter' => $publicKeyCredentialSource->counter,
                    'user_handle' => $publicKeyCredentialSource->userHandle,
                    'transports' => $publicKeyCredentialSource->transports,
                    'last_used_at' => now(),
                ]
            );
    }

    private function decodeSource(string $data): ?PublicKeyCredentialSource
    {
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return null;
        }

        return PublicKeyCredentialSource::createFromArray($decoded);
    }
}
