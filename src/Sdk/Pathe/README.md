# Pathé SDK

Client for the unofficial pathe.fr API (documented at https://tdutrion.github.io/pathe-fr-unofficial-api-doc/).
It is written like a standalone library: it depends on PSR interfaces only, never on the application
or on a framework, so it could move to its own Composer package as is.

## Dependencies

- `psr/http-client` (PSR-18), `psr/http-factory` (PSR-17), `psr/cache` (PSR-6), `psr/log` (PSR-3)
- `psr-discovery/*-implementations`: finds the implementations that are not given to the constructor

## Usage

```php
use App\Sdk\Pathe\PatheClient;
use App\Sdk\Pathe\PatheMapper;

// Everything discovered: first PSR-18 client and PSR-17 factory installed, cache and logger if any.
$pathe = new PatheClient();

// Everything explicit (what the Symfony application does).
$pathe = new PatheClient(
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    cache: $psr6Pool,
    logger: $psr3Logger,
    delayMs: 1000,   // pause before each call, to stay polite with pathe.fr
    cacheTtl: 3600,  // cities, cinemas and films are kept one hour; 0 disables the cache
);

$cinemas = $pathe->getCinemas(); // array|false
$raw = $pathe->getShowtimes('digger-51293', 'cinema-pathe-dijon'); // array|false
$showtimes = false === $raw ? [] : (new PatheMapper())->mapShowtimes($raw, 'Europe/Paris');
```

## Rules

- Only `Psr\*`, `PsrDiscovery\*` and `App\Sdk\Pathe\*` may be imported.
- `false` means a one-off failure (logged); a `\RuntimeException` means Pathé is blocking the caller (HTTP 403/429).
- Pathé times are naive local times; `PatheMapper::mapShowtimes()` converts them to UTC with the time zone given by the caller.
