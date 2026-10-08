<?php

namespace App\Security\UserInfo;

use App\Security\UserInfo;
use App\Security\UserInfoProvider;
use App\Security\VerifiedEmail;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * GitHub does not do OIDC: the public email from /user is not necessarily verified.
 * We read /user/emails and keep only the primary AND verified email.
 */
#[AsTaggedItem('github')]
class GithubUserInfoProvider implements UserInfoProvider
{
    #[\Override]
    public function scopes(): array
    {
        return ['read:user', 'user:email'];
    }

    #[\Override]
    public function userInfo(OAuth2ClientInterface $client, AccessToken $accessToken): UserInfo
    {
        $owner = $client->fetchUserFromToken($accessToken);
        $data = $owner->toArray();
        $provider = $client->getOAuth2Provider();
        $emails = $provider->getParsedResponse(
            $provider->getAuthenticatedRequest('GET', 'https://api.github.com/user/emails', $accessToken),
        );

        $primary = array_find(
            \is_array($emails) ? $emails : [],
            static fn (mixed $entry): bool => \is_array($entry) && true === ($entry['primary'] ?? false) && true === ($entry['verified'] ?? false),
        );
        $email = $primary['email'] ?? null;

        try {
            $verified = \is_string($email) ? new VerifiedEmail($email) : null;
        } catch (\InvalidArgumentException) {
            $verified = null;
        }

        return new UserInfo(
            id: (string) $owner->getId(),
            email: $verified,
            name: $data['name'] ?? $data['login'] ?? null,
        );
    }
}
