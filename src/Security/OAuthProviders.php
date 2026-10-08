<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enabled sign-in providers (OAUTH_PROVIDERS variable).
 */
class OAuthProviders
{
    public function __construct(
        /** @var list<string> */
        #[Autowire('%env(csv:OAUTH_PROVIDERS)%')]
        private array $providers,
    ) {
    }

    /**
     * @return list<string> names of the enabled providers, e.g. ['local', 'github']
     */
    public function enabled(): array
    {
        return $this->providers;
    }
}
