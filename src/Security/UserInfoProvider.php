<?php

declare(strict_types=1);

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What the application needs to know to sign users in with a provider: the scopes to ask for and how
 * to read the identity it answers with. A provider that is not read as OpenID Connect is a class tagged
 * with #[AsTaggedItem('its-name')] and the configuration of its client in knpu_oauth2_client.yaml.
 */
#[AutoconfigureTag]
interface UserInfoProvider
{
    /**
     * @return list<string>
     */
    public function scopes(): array;

    /**
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \UnexpectedValueException
     */
    public function userInfo(OAuth2ClientInterface $client, AccessToken $accessToken): UserInfo;
}
