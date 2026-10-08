<?php

namespace App\Security\UserInfo;

use App\Security\UserInfo;
use App\Security\UserInfoProvider;
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
        $email = $data['email'] ?? null;

        return new UserInfo(
            id: (string) $owner->getId(),
            email: $email,
            emailVerified: null !== $email && true === ($data['email_verified'] ?? null),
            name: $data['name'] ?? null,
        );
    }
}
