# Pathé SDK

Client for the unofficial pathe.fr API (documented at https://tdutrion.github.io/pathe-fr-unofficial-api-doc/).
It is written like a standalone library: it depends on PSR interfaces only, never on the application
or on a framework, so it could move to its own Composer package as is.

## Dependencies

- `psr/http-client` (PSR-18), `psr/http-factory` (PSR-17), `psr/cache` (PSR-6), `psr/log` (PSR-3)
- `psr-discovery/*-implementations`: finds the implementations that are not given to the constructor

## Usage

```php
use App\Sdk\Pathe\CinemaSlug;
use App\Sdk\Pathe\PatheClient;
use App\Sdk\Pathe\PatheMapper;
use App\Sdk\Pathe\ShowSlug;

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
$showtimes = $pathe->getShowtimes(
    showSlug: new ShowSlug('digger-51293'),
    cinemaSlug: new CinemaSlug('cinema-pathe-dijon'),
); // PatheShowtimes|false
$mapped = false === $showtimes ? [] : (new PatheMapper())->mapShowtimes(
    showtimes: $showtimes,
    timezone: new \DateTimeZone('Europe/Paris'),
);
```

## Rules

- Only `Psr\*`, `PsrDiscovery\*` and `App\Sdk\Pathe\*` may be imported.
- Slugs are value objects (`ShowSlug`, `CinemaSlug`): an invalid slug throws an `\InvalidArgumentException`
  when it is built, and a film cannot be passed where a cinema is expected.
- Pathé's shapes are normalized once, here: `PatheShowtimes::fromApiResponse()` turns `[]` or showtimes indexed
  by date into one list, `GpsPosition::fromApi()` turns `x`/`y` into a latitude and a longitude (`null` without position).
- `false` means a one-off failure (logged), an unexpected shape of response included; a `\RuntimeException` means
  Pathé is blocking the caller (HTTP 403/429).
- Pathé times are naive local times; `PatheMapper::mapShowtimes()` converts them to UTC with the time zone given by the caller.
  A malformed date skips that showtime only (logged through the optional PSR-3 logger of `PatheMapper`).
