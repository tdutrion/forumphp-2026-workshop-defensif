<?php

namespace App\Security\UserInfo;

use App\Security\UserInfo;
use App\Security\UserInfoProvider;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The offline provider: OpenID Connect, but it accepts any name typed by anyone, so its emails are never proof of ownership.
 */
#[AsTaggedItem('local')]
class LocalUserInfoProvider implements UserInfoProvider
{
    public function __construct(private OpenIdConnectUserInfoProvider $openIdConnect)
    {
    }

    #[\Override]
    public function scopes(): array
    {
        return $this->openIdConnect->scopes();
    }

    #[\Override]
    public function userInfo(OAuth2ClientInterface $client, AccessToken $accessToken): UserInfo
    {
        return $this->openIdConnect->userInfo($client, $accessToken)->withUnverifiedEmail();
    }
}
