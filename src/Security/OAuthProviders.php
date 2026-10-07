<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enabled sign-in providers (OAUTH_PROVIDERS variable).
 */
class OAuthProviders
{
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
}
