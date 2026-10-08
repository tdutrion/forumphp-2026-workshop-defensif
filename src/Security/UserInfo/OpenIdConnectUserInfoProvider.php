<?php

declare(strict_types=1);

namespace App\Security\UserInfo;

use App\Security\UnverifiedEmail;
use App\Security\UserInfo;
use App\Security\UserInfoProvider;
use App\Security\VerifiedEmail;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Every OpenID Connect provider shares the same reading of the standard claims: this is the reading of
 * any provider that has no class of its own (Google, Keycloak, LinkedIn...), declared by configuration alone.
 */
class OpenIdConnectUserInfoProvider implements UserInfoProvider
{
    #[\Override]
    public function scopes(): array
    {
        return ['openid', 'email', 'profile'];
    }

    #[\Override]
    public function userInfo(OAuth2ClientInterface $client, AccessToken $accessToken): UserInfo
    {
        $owner = $client->fetchUserFromToken($accessToken);
        $data = $owner->toArray();

        return new UserInfo(
            id: (string) $owner->getId(),
            email: $this->email($data),
            name: $data['name'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function email(array $claims): VerifiedEmail|UnverifiedEmail|null
    {
        $email = $claims['email'] ?? null;
        if (!\is_string($email)) {
            return null;
        }

        try {
            return true === ($claims['email_verified'] ?? null) ? new VerifiedEmail($email) : new UnverifiedEmail($email);
        } catch (\InvalidArgumentException) {
            // Not an address: the provider gives no usable email.
            return null;
        }
    }
}
