<?php

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Reads the identity returned by each provider and brings it to a single format.
 */
class OAuthUserInfoExtractor
{
    /**
     * @return array ['id' => identifier at the provider, 'email' => ?string, 'emailVerified' => bool, 'name' => ?string]
     */
    public function extract(string $provider, OAuth2ClientInterface $client, AccessToken $accessToken): array
    {
        $owner = $client->fetchUserFromToken($accessToken);

        // All OpenID Connect providers share the same reading of the standard claims; GitHub is apart.
        // The callers only pass enabled providers: any provider declared in knpu_oauth2_client.yaml
        // (Google, Keycloak, LinkedIn...) is read as OpenID Connect, without new code.
        return match ($provider) {
            // The offline provider accepts any name typed by anyone: its emails are never proof of ownership.
            'local' => ['emailVerified' => false] + $this->fromOpenIdConnect($owner),
            'github' => $this->fromGithub($owner, $client, $accessToken),
            default => $this->fromOpenIdConnect($owner),
        };
    }

    private function fromOpenIdConnect(ResourceOwnerInterface $owner): array
    {
        $data = $owner->toArray();

        return [
            'id' => (string) $owner->getId(),
            'email' => $data['email'] ?? null,
            'emailVerified' => true === ($data['email_verified'] ?? null),
            'name' => $data['name'] ?? null,
        ];
    }

    /**
     * GitHub does not do OIDC: the public email from /user is not necessarily verified.
     * We read /user/emails and keep only the primary AND verified email.
     */
    private function fromGithub(ResourceOwnerInterface $owner, OAuth2ClientInterface $client, AccessToken $accessToken): array
    {
        $data = $owner->toArray();
        $provider = $client->getOAuth2Provider();
        $emails = $provider->getParsedResponse(
            $provider->getAuthenticatedRequest('GET', 'https://api.github.com/user/emails', $accessToken),
        );

        $email = null;
        foreach (is_array($emails) ? $emails : [] as $entry) {
            if (true === ($entry['primary'] ?? false) && true === ($entry['verified'] ?? false)) {
                $email = $entry['email'];
                break;
            }
        }

        return [
            'id' => (string) $owner->getId(),
            'email' => $email,
            'emailVerified' => null !== $email,
            'name' => $data['name'] ?? $data['login'] ?? null,
        ];
    }
}
