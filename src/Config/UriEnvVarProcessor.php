<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Uri\Rfc3986\Uri;

/**
 * %env(uri:NAME)% is the value of NAME, which must be an absolute http(s) URL with a host:
 * a typo in a URL of the environment fails with the name of the variable, not on the first sign-in.
 */
final class UriEnvVarProcessor implements EnvVarProcessorInterface
{
    #[\Override]
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $value = $getEnv($name);
        if (!\is_string($value)) {
            throw new RuntimeException(\sprintf('The environment variable "%s" is not a string.', $name));
        }

        $uri = Uri::parse($value);
        if (null === $uri || !\in_array($uri->getScheme(), ['http', 'https'], true) || \in_array($uri->getHost(), [null, ''], true)) {
            throw new RuntimeException(\sprintf('The environment variable "%s" is not an absolute http(s) URL.', $name));
        }

        return $uri->toString();
    }

    #[\Override]
    public static function getProvidedTypes(): array
    {
        return ['uri' => 'string'];
    }
}
