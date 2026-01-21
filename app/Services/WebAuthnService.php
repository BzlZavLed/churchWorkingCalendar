<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TokenBinding\IgnoreTokenBindingHandler;

class WebAuthnService
{
    public function __construct(private readonly WebAuthnCredentialRepository $credentialRepository)
    {
    }

    public function createRegistrationOptions(User $user): array
    {
        $rp = PublicKeyCredentialRpEntity::create(
            Config::get('webauthn.rp_name'),
            Config::get('webauthn.rp_id')
        );
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->email,
            (string) $user->id,
            $user->name
        );

        $challenge = random_bytes(32);
        $pubKeyCredParams = [
            PublicKeyCredentialParameters::create('public-key', -7),
            PublicKeyCredentialParameters::create('public-key', -257),
        ];

        $excludeCredentials = [];
        foreach ($this->credentialRepository->findAllForUserEntity($userEntity) as $source) {
            $excludeCredentials[] = $source->getPublicKeyCredentialDescriptor();
        }

        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            $challenge,
            $pubKeyCredParams,
            $authenticatorSelection,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $excludeCredentials,
            Config::get('webauthn.timeout', 60000)
        );

        return $this->normalizeOptions($options->jsonSerialize());
    }

    public function createAuthenticationOptions(?User $user): array
    {
        $allowCredentials = [];
        if ($user) {
            $userEntity = PublicKeyCredentialUserEntity::create(
                $user->email,
                (string) $user->id,
                $user->name
            );
            foreach ($this->credentialRepository->findAllForUserEntity($userEntity) as $source) {
                $allowCredentials[] = $source->getPublicKeyCredentialDescriptor();
            }
        }

        $challenge = random_bytes(32);
        $options = PublicKeyCredentialRequestOptions::create(
            $challenge,
            Config::get('webauthn.rp_id'),
            $allowCredentials,
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            Config::get('webauthn.timeout', 60000)
        );

        return $options->jsonSerialize();
    }

    public function verifyRegistration(array $credential, array $options): void
    {
        $publicKeyCredential = $this->credentialLoader()->loadArray($credential);
        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new \InvalidArgumentException('Invalid attestation response.');
        }

        $creationOptions = PublicKeyCredentialCreationOptions::createFromArray($options);
        $validator = AuthenticatorAttestationResponseValidator::create(
            $this->attestationSupportManager(),
            null,
            new IgnoreTokenBindingHandler()
        );

        $publicKeyCredentialSource = $validator->check(
            $publicKeyCredential->response,
            $creationOptions,
            Config::get('webauthn.rp_id'),
            $this->securedRelyingPartyIds()
        );

        $this->credentialRepository->saveCredentialSource($publicKeyCredentialSource);
    }

    public function verifyAuthentication(array $credential, array $options): \Webauthn\PublicKeyCredentialSource
    {
        $publicKeyCredential = $this->credentialLoader()->loadArray($credential);
        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            throw new \InvalidArgumentException('Invalid assertion response.');
        }

        $requestOptions = PublicKeyCredentialRequestOptions::createFromArray($options);
        $validator = AuthenticatorAssertionResponseValidator::create(
            $this->credentialRepository,
            new IgnoreTokenBindingHandler()
        );

        return $validator->check(
            $publicKeyCredential->rawId,
            $publicKeyCredential->response,
            $requestOptions,
            Config::get('webauthn.rp_id'),
            $publicKeyCredential->response->userHandle,
            $this->securedRelyingPartyIds()
        );
    }

    private function credentialLoader(): PublicKeyCredentialLoader
    {
        $attestationObjectLoader = AttestationObjectLoader::create($this->attestationSupportManager());
        return PublicKeyCredentialLoader::create($attestationObjectLoader);
    }

    private function attestationSupportManager(): AttestationStatementSupportManager
    {
        return new AttestationStatementSupportManager();
    }

    private function normalizeOptions(array $options): array
    {
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $options[$key] = $this->normalizeOptions($value);
                continue;
            }
            if (is_object($value) && method_exists($value, 'jsonSerialize')) {
                $options[$key] = $this->normalizeOptions((array) $value->jsonSerialize());
            }
        }

        return $options;
    }

    private function securedRelyingPartyIds(): ?array
    {
        $rpId = (string) Config::get('webauthn.rp_id');
        if (in_array($rpId, ['localhost', '127.0.0.1'], true)) {
            return [$rpId];
        }

        return null;
    }
}
