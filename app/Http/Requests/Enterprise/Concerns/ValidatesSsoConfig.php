<?php

declare(strict_types=1);

namespace App\Http\Requests\Enterprise\Concerns;

/**
 * Shape of an SSO connection's provider `config` (stored encrypted). The IdP
 * endpoints are called server-side, so production accepts only https URLs —
 * plain-http internal targets (e.g. cloud metadata) are out of reach. Missing
 * fields are allowed here; an incomplete registration fails closed at login.
 */
trait ValidatesSsoConfig
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function ssoConfigRules(): array
    {
        $url = app()->isProduction() ? 'url:https' : 'url:http,https';

        return [
            'config.issuer' => ['sometimes', 'string', 'max:2048', $url],
            'config.authorization_endpoint' => ['sometimes', 'string', 'max:2048', $url],
            'config.token_endpoint' => ['sometimes', 'string', 'max:2048', $url],
            'config.jwks_uri' => ['sometimes', 'string', 'max:2048', $url],
            'config.userinfo_endpoint' => ['sometimes', 'string', 'max:2048', $url],
            'config.client_id' => ['sometimes', 'string', 'max:255'],
            'config.client_secret' => ['sometimes', 'string', 'max:2048'],
            'config.scope' => ['sometimes', 'string', 'max:255'],
            'config.token_endpoint_auth_method' => ['sometimes', 'string', 'in:client_secret_basic,client_secret_post'],
            // SAML: the IdP's identity, its SSO endpoint and its signing certificate.
            'config.idp_entity_id' => ['sometimes', 'string', 'max:2048'],
            'config.idp_sso_url' => ['sometimes', 'string', 'max:2048', $url],
            'config.idp_x509_cert' => ['sometimes', 'string', 'max:20000'],
            'config.idp_slo_url' => ['sometimes', 'string', 'max:2048', $url],
            'config.sign_requests' => ['sometimes', 'boolean'],
            'config.encrypt_assertions' => ['sometimes', 'boolean'],
            'config.email_attribute' => ['sometimes', 'string', 'max:255'],
            'config.name_attribute' => ['sometimes', 'string', 'max:255'],
            'config.subject_attribute' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
