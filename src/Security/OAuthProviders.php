<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enabled sign-in providers (OAUTH_PROVIDERS variable) and the scopes requested from each.
 */
class OAuthProviders
{
    private const SCOPES = [
        'google' => ['openid', 'email', 'profile'],
        'github' => ['read:user', 'user:email'],
        'local' => ['openid', 'email', 'profile'],
    ];

    public function __construct(
        #[Autowire('%env(OAUTH_PROVIDERS)%')]
        private string $providers,
    ) {
    }

    /**
     * @return array names of the enabled providers, e.g. ['local', 'github']
     */
    public function enabled(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->providers))));
    }

    public function scopes(string $provider): array
    {
        return self::SCOPES[$provider] ?? ['openid', 'email', 'profile'];
    }
}
