# Pathé SDK

Client for the unofficial pathe.fr API (documented at https://tdutrion.github.io/pathe-fr-unofficial-api-doc/).
It is written like a standalone library: it depends on PSR interfaces only, never on the application
or on a framework, so it could move to its own Composer package as is.

## Dependencies

- `psr/http-client` (PSR-18), `psr/http-factory` (PSR-17), `psr/cache` (PSR-6), `psr/log` (PSR-3)
Nothing is discovered: the PSR-18 client and the PSR-17 factory are given to the constructor; the PSR-6 cache is
optional (none, no cache) and the PSR-3 logger defaults to a `NullLogger`.

## Usage

```php
use App\Sdk\Pathe\BotBlockedException;
use App\Sdk\Pathe\CinemaSlug;
use App\Sdk\Pathe\PatheClient;
use App\Sdk\Pathe\PatheMapper;
use App\Sdk\Pathe\PatheUnavailableException;
use App\Sdk\Pathe\RateLimitedException;
use App\Sdk\Pathe\ShowSlug;

// The two collaborators it cannot do without; no cache, no logs.
$pathe = new PatheClient(httpClient: $psr18Client, requestFactory: $psr17RequestFactory);

// Everything explicit (what the Symfony application does).
$pathe = new PatheClient(
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    cache: $psr6Pool,
    logger: $psr3Logger,
    delayMs: 1000,   // pause before each call, to stay polite with pathe.fr
    cacheTtl: 3600,  // cities, cinemas and films are kept one hour; 0 disables the cache
);

try {
    $cinemas = $pathe->getCinemas(); // array
    $showtimes = $pathe->getShowtimes(
        showSlug: new ShowSlug('digger-51293'),
        cinemaSlug: new CinemaSlug('cinema-pathe-dijon'),
    ); // PatheShowtimes
} catch (BotBlockedException|RateLimitedException $e) {
    // Pathé refuses the caller: stop, do not insist.
} catch (PatheUnavailableException $e) {
    // A one-off failure: skip this call, go on with the next one.
}

$mapped = (new PatheMapper())->mapShowtimes(
    showtimes: $showtimes,
    timezone: new \DateTimeZone('Europe/Paris'),
);
```

## Rules

- Only `Psr\*` and `App\Sdk\Pathe\*` may be imported.
- Slugs are value objects (`ShowSlug`, `CinemaSlug`): an invalid slug throws an `\InvalidArgumentException`
  when it is built, and a film cannot be passed where a cinema is expected.
- Pathé's shapes are normalized once, here: `PatheShowtimes::fromApiResponse()` turns `[]` or showtimes indexed
  by date into one list, `GpsPosition::fromApi()` turns `x`/`y` into a latitude and a longitude (`null` without position).
- Every failure is a `PatheApiException` (abstract, a `\RuntimeException`), whose subclass says what to do:
  `BotBlockedException` (HTTP 403) and `RateLimitedException` (HTTP 429) mean Pathé refuses the caller, so stop;
  `PatheUnavailableException` is a one-off failure (network, error status, answer that is not JSON or not the
  expected shape), logged, that the caller may skip.
- Pathé times are naive local times; `PatheMapper::mapShowtimes()` converts them to UTC with the time zone given by the caller.
  A malformed date skips that showtime only (logged through the optional PSR-3 logger of `PatheMapper`).
