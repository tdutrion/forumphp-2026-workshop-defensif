<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\UserInfo\OpenIdConnectUserInfoProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The UserInfoProvider of each sign-in provider: the class tagged with its name, otherwise OpenID Connect.
 * The callers only ask for enabled providers.
 */
class UserInfoProviders
{
    /**
     * @param ServiceLocator<UserInfoProvider> $providers the implementations, indexed by #[AsTaggedItem]
     */
    public function __construct(
        #[AutowireLocator(UserInfoProvider::class)]
        private ServiceLocator $providers,
        private OpenIdConnectUserInfoProvider $openIdConnect,
    ) {
    }

    public function for(string $provider): UserInfoProvider
    {
        return $this->providers->has($provider) ? $this->providers->get($provider) : $this->openIdConnect;
    }
}
