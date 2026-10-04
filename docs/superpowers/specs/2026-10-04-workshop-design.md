# "Defensive PHP" workshop: sample application (v1)

- Date: 2026-10-04
- Status: design validated in conversation, spec to be reviewed
- Workshop: October 8, 2026, 2 h, material for about 8 h

## 1. Goal

Build the application that serves as the starting point for a workshop on
defensive programming in PHP, from PHP 5 to PHP 8.5, plus the 8.6 additions
available through the Symfony polyfills.

The application is a **movie marathon planner**. A signed-in user picks a
date, a place and a number of films. They get 3 programmes of Pathé showtimes
that chain together, with no film already seen.

**The v1 principle**: a clean, working and safe project. It contains no bug
and no security flaw. What improves during the workshop is how easy it is for
a developer to use the exposed classes and functions correctly. In v1, the
contracts are implicit: arrays rather than DTOs, scalars rather than enums,
`false|result` returns, few and generic exceptions. The workshop makes them
explicit and checked by the language and the tooling.

> Previous attempts confused "security flaws" and "violations". This spec
> introduces no security flaw, no bug, and no list of "violations" in the
> repository. The mapping between practices and code lives in this spec
> (section 9) and in the trainer guide.

### Success criteria

1. `make up` then `make db-load` give a usable application locally, with no
   call to Pathé, with sign-in through the local OIDC provider.
2. The 3 programmes comply with the rules of section 5.
3. The web and the API offer the same use cases, through the same services.
4. Tests are green. PHPStan passes at level 5 with no errors.
5. Each exercise in sections 9 and 10 has a real, credible anchor point in
   the code.
6. No security flaw, confirmed by a dedicated review before October 8.

## 2. Constraints

- **Runtime**: `dunglas/frankenphp:1.13.0-php8.5-alpine` (PHP 8.5.11 ZTS, Caddy 2.11.7, Mercure 1.0),
  digest pinned in the Dockerfile. Worker mode enabled. Built-in Mercure hub
  enabled.
- **Framework**: Symfony 8.1 (8.2 comes out after the workshop).
- **Polyfills**: `symfony/polyfill-php86` (`clamp()`, `SortDirection`,
  `ARRAY_FILTER_USE_VALUE`, `grapheme_strrev()`) and
  `symfony/polyfill-time` (`Time\Duration`). The 8.6 features that
  affect the syntax or the engine (default values of `readonly` properties,
  partial application, `#[\Override]` on constants) are not available and
  will only be shown on slides.
- **Database**: MySQL 8.4 LTS. Distance calculations are done in PHP
  (Haversine formula): fewer than 100 cinemas, no need for spatial
  indexes.
- **Extensions**: the extensions shipped with PHP (intl, opcache,
  pdo_mysql, and zip in dev) go through `install-php-extensions`, provided by
  the FrankenPHP image: PIE only installs them with `--force`. PECL
  extensions go through PIE 1.5.1, versions pinned in the Dockerfile: apcu
  5.1.28 in the base, Xdebug 3.5.3 in the `dev` target.
- **Network**: Internet available on the day, with possible outages.
  The application must never call Pathé during the workshop.
- **Generated identifiers**: UUID v7 (Uid component). Pathé data
  keeps its slug as the natural key.
- **Time**: everything runs in UTC (`date.timezone = UTC`, UTC instants in
  the database). Local times are converted with the time zone of the cinema's
  chain: on input, at the SDK boundary, and on output, for display and in the
  API (ISO 8601 with offset). Each showtime also keeps its local calendar day
  (`local_date`), which the date filters use. `Europe/Paris` is a
  configuration value of the Pathé chain, never a constant of the code.
- **Chains**: v1 supports one chain, Pathé (France). The project is generic
  so that other chains with an unlimited pass can be added later (see
  section 15). The parameter `app.chains` (`config/packages/chains.yaml`)
  declares each chain: name, country, time zone. Cinemas, cities and films
  carry their chain; cinemas also carry the chain's country and time zone.
- **Third-party SDKs**: everything that talks to Pathé lives in
  `src/Sdk/Pathe`, written like an external library. It depends only on PSR
  interfaces: PSR-18 HTTP client, PSR-17 factories, PSR-6 cache, PSR-3 logger.
  Every collaborator is optional and is otherwise discovered with
  psr-discovery (`psr-discovery/*-implementations`). It never uses Symfony,
  Doctrine or the rest of the application. The application wires the
  implementations explicitly (Symfony HttpClient through `Psr18Client`,
  nyholm/psr7, a `cache.pathe` pool, Monolog).
- **Languages**: Identifiers, messages, comments, documentation, README and commits are in English. The UI is written in English and translated into French with the Symfony Translator (default locale en, French served to browsers that ask for it).
- **Git**: Conventional Commits in English, signed commits authored by `Thomas Dutrion <hello@tdutrion.fr>`.

## 3. Scope

### For October 8

- Docker compose: FrankenPHP (dev), MySQL, local OIDC provider, Messenger
  worker. A single multistage Dockerfile with the `dev` and `prod` targets.
- Pathé sync (command + scheduled task) and frozen dataset.
- Google, GitHub and local OIDC sign-in. Linking several connections
  to a single account.
- "Already seen" declared by the user.
- Planner in Twig (Symfony UX Turbo + Autocomplete) and as a JSON API
  (personal token).
- One Mercure notification: "programme updated" after a sync.
- PHPUnit, PHPStan, PHP-CS-Fixer, Makefile, trainer guide.

### Later (out of scope for this spec)

LinkedIn, OAuth Authorization Code + PKCE flow for a mobile application
(`league/oauth2-server-bundle`), other real-time uses of Mercure, a map with
Symfony UX Map, co-planning with several people, solution branch, other
cinema chains (section 15).

## 4. Architecture

### Containers

| Service  | Image                                       | Role                                         |
|----------|---------------------------------------------|----------------------------------------------|
| `php`    | Dockerfile, `dev` target                    | FrankenPHP: HTTPS, worker mode, Mercure hub  |
| `worker` | same image                                  | `messenger:consume` (scheduled sync)         |
| `db`     | `mysql:8.4`                                 | application database                         |
| `oidc`   | `ghcr.io/navikt/mock-oauth2-server:6.0.4`   | local OIDC provider, interactive sign-in (dev only) |

### Multistage Dockerfile

1. `base`: FrankenPHP image pinned by digest, `install-php-extensions
   @composer intl opcache pdo_mysql`, apcu via PIE 1.5.1 (binary mounted
   from `ghcr.io/php/pie:1.5.1-bin`, build dependencies installed then
   removed), common PHP settings (`date.timezone = UTC`). The Docker files live
   under `docker/` (`docker/frankenphp/`, `docker/mysql/`, `docker/oidc/`).
2. `dev`: `FROM base`, zip, Xdebug via PIE (disabled by default,
   can be enabled with an environment variable), `php.ini-development`,
   FrankenPHP watch mode.
3. `prod`: `FROM base`, `composer install --no-dev`,
   `dump-autoload --classmap-authoritative`, compiled assets, OPcache tuned
   for production, Composer and git removed, `www-data` user.

### Modules (namespaces under `App\`)

| Module     | Responsibility                                                                 | Depends on                 |
|------------|--------------------------------------------------------------------------------|----------------------------|
| `Sdk\Pathe` | Third-party SDK, framework-free: Pathé HTTP client (PSR-18/17/6/3, psr-discovery) and response mapping, local times to UTC (`src/Sdk/Pathe/`) | PSR interfaces only |
| `Catalog\Sync` | Fills the catalog from the Pathé SDK: synchronizer, `catalog:sync` command, schedule, Mercure notification | `Sdk\Pathe`, `Catalog`     |
| `Catalog`  | `City`, `Cinema`, `Film`, `Showtime` entities and their repositories                   | —                          |
| `Planner`  | Computation of the programmes from the catalog                                 | `Catalog`, `Account`       |
| `Account`  | `User`, linked connections, films already seen, API tokens                     | `Catalog`                  |
| `Security` | Generic OAuth authenticator, provider registry, token authenticator            | `Account`                  |
| `Web`      | Twig controllers, forms, UX components                                         | `Planner`, `Account`, `Catalog` |
| `Api`      | JSON controllers, OpenAPI documentation (Nelmio)                               | `Planner`, `Account`, `Catalog` |

The `Web` and `Api` controllers are thin. They call the **same** application
services: `PlannerService`, `SeenFilmService`, `AccountService`. No business
logic is duplicated between the two channels. A functional test checks that
the web and the API give the same result for the same request.

### Data flow

```
Pathé (/api/*) ──► PatheClient (PSR-18) ──► CatalogSynchronizer ──► MySQL ──► PlannerService ──► Web (Twig)
                       ▲                                                          └────► Api (JSON)
     catalog SQL dump (make db-dump / make db-load)
```

Only the sync command calls Pathé. The site only reads the database.

## 5. Domain

### Planning form

| Field    | Rule                                                                                                      |
|----------|-----------------------------------------------------------------------------------------------------------|
| Date     | a day for which showtimes are synchronized (Pathé publishes the current week, from Wednesday to Tuesday)  |
| Place    | a Pathé city (autocomplete on the synchronized cities) or the browser position                            |
| Radius   | 1 to 50 km, 10 km by default                                                                              |
| Films    | 2 to 5                                                                                                    |
| Version  | optional: VF, VOST, VO, VFST                                                                              |
| Ads      | checkbox "I accept arriving during the ads (15 minutes)"                                                  |

The center of the radius is either the barycenter of the cinemas of the
chosen city, or the browser position. The GPS position that Pathé gives for a
city is not reliable: Dijon is placed about 45 km from its actual
center (47.449, 5.583 instead of 47.32, 5.04).
The browser position arrives in a hidden field, in JSON format
(`{"lat": …, "lng": …}`).

### Candidate showtimes

A showtime is a candidate if it meets all these conditions:

- its `time` falls on the requested date (Paris time);
- its cinema is open and within the radius;
- `status = available` and `reservabilityEnd` is in the future;
- its film is not in the user's "already seen" list;
- its version matches, if a version is requested.

### Chaining rules

- A showtime occupies the range from `time` (start of the ads) to `endTime`
  (end of the film). Pathé gives `endTime = time + duration + 20 min`.
- For the next showtime, the earliest arrival time is:
  - the end of the previous one + 10 min, if it is the same cinema;
  - the end of the previous one + 10 min + the travel, if it is another
    cinema. The travel is the straight-line distance between the two
    cinemas, covered at 15 km/h.
- The next showtime is compatible if the earliest arrival is before or equal
  to its `time`. With the ads option, the arrival can be up to
  `time + 15 min`.
- A programme never contains the same film twice.

### The 3 programmes

1. The candidate showtimes are sorted by `time`. A depth-first search builds
   the sequences of N compatible showtimes, with a cap of 20,000 explored
   nodes. The cap keeps the response time under a second.
2. Each complete programme is scored: total wait time (sum of the gaps
   between arrival and `time`), then total distance traveled.
3. The 3 best programmes that are pairwise different are kept: each
   must have at least one film that the other does not have.
4. If fewer than 3 exist, the ones found are returned, with a message that
   explains why (no candidate showtime, radius too small, too many films
   requested…).

### "Already seen"

- "Already seen" button on each film, in the results and on the film page.
  Can be undone.
- List managed from the profile.
- "Mark this programme as seen" button that adds all its films.

### Accounts and sign-in

- A `User` (UUID v7) has one or more linked connections (`provider`,
  identifier at the provider, email, email verified or not).
- First sign-in through a provider: if the email is **verified by the
  provider** and already belongs to an account, the connection is linked to
  that account. Otherwise, a new account is created.
- From the profile, the signed-in user can link another provider or
  remove a connection, but not the last one.
- Providers in v1:
  - `google` and `local`: two OpenID Connect providers declared the same
    way (league `GenericProvider` + their URLs), scopes
    `openid email profile`, reading the standard claims `sub`, `email`,
    `email_verified`, `name`. `local` targets the local OIDC provider: it
    serves as a fallback without Internet and shows how a provider is added
    through configuration.
  - `github`: `league/oauth2-github`, scopes `read:user user:email`.
    GitHub is not an OpenID Connect provider for identity: its discovery
    document has neither a `userinfo` endpoint nor an `email` claim.
    The verified email is read from `/user/emails` (primary and verified
    only).
- Adding a provider = one KnpU configuration block + the environment
  variables, with no new code, unless the provider returns its user
  information in an unprecedented format.

### API

| Method  | Route                  | Use case                       |
|---------|------------------------|--------------------------------|
| GET     | `/api/plans`           | plan (same parameters as the form) |
| GET     | `/api/cities`          | list the cities                |
| GET     | `/api/films/{slug}`    | film page                      |
| GET     | `/api/me`              | profile                        |
| GET     | `/api/me/seen-films`   | list the films already seen    |
| PUT     | `/api/me/seen-films/{slug}` | mark a film as seen       |
| DELETE  | `/api/me/seen-films/{slug}` | remove a film already seen |

- Authentication: a personal token, generated from the profile, shown only
  once. Stored as a SHA-256 hash, revocable, with an expiration
  date. Sent in the `Authorization: Bearer` header.
- OpenAPI documentation generated by `nelmio/api-doc-bundle`.

## 6. Pathé data

Source: the doc maintained in `pathe-fr-unofficial-api-doc` (69 paths,
verified on October 4, 2026).

### Endpoints used

| Endpoint                                      | Usage                                                      |
|-----------------------------------------------|------------------------------------------------------------|
| `/cities`                                     | cities, GPS position, cinema slugs                         |
| `/cinemas`                                    | cinemas, addresses, GPS in `theaters[].gpsPosition` (`x` = latitude, `y` = longitude) |
| `/shows`                                      | films: running time, genres, poster, rating                |
| `/cinema/{slug}/shows`                        | which films play on which days in a cinema                 |
| `/show/{slug}/showtimes/{cinema}`             | all the showtimes of a film in a cinema, by date (one call covers the week) |
| `/versions`                                   | versions reference                                         |

### Sync

- Configurable scope: list of cities. Default: `paris`, `lyon`
  and `dijon` (18 cinemas: 13 in Paris, 3 in Lyon, 2 in Dijon), i.e. about
  700 requests and a dozen minutes at one request per second.
- Order: cities → cinemas → films → schedule of each cinema → showtimes
  of each film × cinema pair (one call per pair covers the whole published
  week).
- Records are updated by slug (no delete followed by reinsert). Showtimes
  that disappeared from the synchronized window are deleted.
- A showtime is stably identified by the `V{vista}S{session}` segment of its
  `refCmd` booking link.
- Politeness: User-Agent `PatheApiExplorer/1.0`, sequential requests,
  at most one per second. Immediate sync stop on a 403 or a
  429.
- Scheduled every 6 hours via Symfony Scheduler (transport
  `scheduler_catalog`), consumed by the `worker` service.
- Pathé times (`time`, `endTime`) have no time zone. They are local
  times of the chain: `PatheMapper::mapShowtimes()` converts them to UTC with
  the time zone that `app.chains` gives for Pathé, and keeps the local day.
- Reference data (cities, cinemas, films) is cached by the SDK for
  `PATHE_CACHE_TTL` seconds (PSR-6) when a cache is given.

### Frozen dataset (SQL dump)

- **Preparation, the day before (Wednesday, October 7)**: you run `make sync`
  on Paris, Lyon and Dijon (18 cinemas: 13 in Paris, 3 in Lyon, 2 in Dijon),
  then `make db-dump`. The command writes `data/catalog.sql.gz`: the
  **data** of the catalog tables (cities, cinemas, films, showtimes,
  versions), without the schema and without any user data. Wednesday works
  out well: it is the first day of Pathé's scheduling week,
  so the dump covers the whole week of the workshop Thursday.
- The file is committed to the repository before the `v1.0.0` tag: a
  `git clone` is enough, no download is needed on the day.
- **Attendee side**: `make db-load` runs the Doctrine migrations (the schema
  always comes from the migrations), empties the catalog tables,
  then imports the dump. The application is usable without a network.
- **Reuse after the workshop**: `bin/console catalog:shift-dates`
  shifts all the showtimes so that the first day of the dump becomes
  today.
- The three cities give three profiles: a big city where travel
  matters, a medium-sized city, a small city where programmes are
  rare ("fewer than 3 programmes" case).
- The tests do not use this dump. A test builder produces fake Pathé
  responses with the real shapes (`x`/`y` coordinates, `contentRating`
  as an object or `[]`, showtimes ending after midnight…), served by a fake
  PSR-18 client that replaces the real one in the test environment.

## 7. Security (in place from v1)

These practices are part of the starting code. The workshop can show them,
never "fix" them:

- parameterized queries everywhere (Doctrine);
- CSRF protection on all forms and on logout;
- automatic Twig escaping, no `|raw` on external data;
- OAuth `state` and PKCE parameters handled by the library, redirects
  only to internal routes;
- account linking only on a verified email;
- API tokens generated with `random_bytes()`, stored hashed, compared
  on their hash, never placed in a URL;
- no user-specific state in the services (worker mode);
- `#[\SensitiveParameter]` on secrets and tokens;
- secrets via environment variables or Symfony secrets, never
  committed. The Mercure default values ("ChangeMe") are
  replaced.

## 8. Style of v1

### Allowed (what the workshop evolves)

- associative arrays as input and output of the services, with
  `@param array` / `@return array` docblocks without a precise shape;
- strings and integers for business concepts: versions, statuses,
  slugs, dates as `Y-m-d`, durations in minutes;
- `X|false` or `?X` returns to signal a failure or an absence;
- generic `\RuntimeException` and `\InvalidArgumentException`, with a text
  message;
- Doctrine entities with getters, setters and nullable properties;
- `match` on strings to choose a behavior;
- `new \DateTimeImmutable()` and configuration parameters read as strings
  in the services;
- `declare(strict_types=1)` absent;
- arrays passed to the Twig templates, `strict_variables` disabled;
- `JsonResponse` built from arrays.

### Forbidden

- any security flaw, any known bug;
- logic duplicated between the web and the API;
- dead code, badly formatted code (PHP-CS-Fixer, `@Symfony` rules);
- red tests, PHPStan errors at level 5;
- misleading names: the code must stay readable and honest about what it
  does.

## 9. Practices and anchor points

Each row says where the practice applies in v1. The exercise number refers to
section 10. "Shown" means the practice is already in place (security) or is
not executable on PHP 8.5.

| Practice | PHP | v1 anchor point (naive form) | Target | Ex. |
|---|---|---|---|---|
| Class and array type declarations | 5.0/5.1 | `PatheMapper::mapCinema(array $raw)` | precise types, value objects | 1 |
| SPL exceptions (`InvalidArgumentException`, `OutOfRangeException`…) | 5.1 | `\RuntimeException` everywhere | SPL exceptions in the value objects | 1, 11 |
| `DateTimeImmutable` | 5.5 | `strtotime()` and minutes as integers in `ChainBuilder` | `ScreeningTime` | 3 |
| `finally` | 5.5 | sync lock released in every branch | `try/finally` | 11 |
| Named constructors | — | `new Coordinates($raw['x'], $raw['y'])` at every call | `Coordinates::fromPathe()` | 1 |
| Typed variadics | 5.6 | `new ShowtimeCollection(array $items)` | `ShowtimeList(Showtime ...$items)` | 12 |
| Scalar typing + `strict_types` | 7.0 | files without `strict_types`; `'3'` converted to `int` | `declare(strict_types=1)` everywhere | 13 |
| Return types | 7.0 | service methods without a return type | typed returns | 13 |
| `Throwable`/`Error`/`TypeError` hierarchy | 7.0 | `catch (\Exception)` in the sync | targeted `catch` blocks | 11 |
| `random_bytes` / `random_int` | 7.0 | API tokens | — | shown |
| `assert()` and `zend.assertions` | 7.0 | unchecked planner invariants | assertions in dev | 14 |
| Nullable types, `void`, `iterable` | 7.1 | `findBySlug(): ?array` | `find(): ?Film` / `get(): Film` | 6 |
| Class constant visibility | 7.1 | `public const` everywhere in `Planner` | `private const` | 13 |
| Multi-catch | 7.1 | repeated `catch` blocks | `catch (A \| B)` | 11 |
| `JSON_THROW_ON_ERROR` | 7.3 | browser position received as JSON in a hidden field, decoded with `json_decode()` + `null` test | flag + exception | 11 |
| Typed properties | 7.4 | `/** @var string|null */` on properties | typed properties | 13 |
| Covariance and contravariance | 7.4 | repository interface too broad | more precise returns | 13 |
| Union types | 8.0 | `int|string $filmId` | `FilmSlug` | 1 |
| `mixed`, `static`, `never` | 8.0/8.1 | `fail(): void` followed by a `return null` | `fail(): never` | 13 |
| Constructor property promotion | 8.0 | hand-written output DTOs | promotion + `readonly` | 8 |
| `match` | 8.0 | `switch` on the versions | exhaustive `match` on an enum | 2 |
| `throw` as an expression with `??` | 8.0 | repeated `if ($film === null) { throw … }` | `?? throw new FilmNotFound()` | 6 |
| Nullsafe operator | 8.0 | `$film->getContentRating() !== null ? $film->getContentRating()->getLabel() : null` in Twig and the API | `?->` or Null Object | 14 |
| Named arguments | 8.0 | `new Showtime($a, $b, $c, $d, $e)` | named arguments | 1 |
| `get_debug_type`, `ValueError` | 8.0 | error messages built with `gettype()` | precise messages | 11 |
| Backed enums, methods, `tryFrom()` | 8.1 | `'vf'`/`'vost'`, `'available'` as strings | `ShowtimeVersion`, `BookingStatus` | 2 |
| `readonly` properties | 8.1 | mutable `ScreeningTime` | `readonly` | 8 |
| `new` in initializers, Null Object | 8.1 | `?HubInterface $hub = null` + `if` | `NullPublisher` by default | 14 |
| Intersection types | 8.1 | `iterable $showtimes` | `Countable&IteratorAggregate` | 12 |
| First-class callables | 8.1 | `array_map([$this, 'map'], …)` | `$this->map(...)` | 12 |
| `array_is_list` | 8.1 | Pathé response `[]` or object indexed by date | normalization at the boundary | 1 |
| `readonly` classes | 8.2 | value objects with a forgotten property | `final readonly class` | 8 |
| DNF types, standalone `true`/`false`/`null` | 8.2 | `array|false` as a return | precise type or Result | 5 |
| `#[\SensitiveParameter]` | 8.2 | OAuth secrets, tokens | — | shown |
| Typed class constants | 8.3 | `const DEFAULT_RADIUS = 10` | `const int DEFAULT_RADIUS = 10` | 13 |
| `#[\Override]` | 8.3 | OAuth provider implementations | `#[\Override]` | 9 |
| `json_validate()` | 8.3 | same field: full decoding just to know whether it is valid | `json_validate()` | 11 |
| Deep cloning of `readonly` | 8.3 | cloned programme that shares its showtimes | `__clone` with reassignment | 8 |
| Precise date exceptions | 8.3 | `DateTimeImmutable::createFromFormat()` + `false` test | `DateMalformedStringException` | 3 |
| Property hooks | 8.4 | capacity `"244"` (Pathé string) converted everywhere | `set` hook or value object | 7 |
| Asymmetric visibility | 8.4 | modifiable `public array $films` on `Programme` | `public private(set)` | 7 |
| `array_find`/`array_any`/`array_all` | 8.4 | `foreach` + `break` | native functions | 12 |
| `#[\Deprecated]` | 8.4 | compatibility method kept during the refactoring | `#[\Deprecated]` | 13 |
| Implicit nullable deprecated | 8.4 | avoided in v1 (deprecated in 8.4) | — | shown |
| `BcMath\Number` | 8.4 | no monetary use in v1 | — | not used |
| Lazy objects | 8.4 | used by Symfony and Doctrine | — | shown |
| `#[\NoDiscard]` | 8.5 | `$programme->withShowtime($s);` result ignored | `#[\NoDiscard]` on withers and Result | 5 |
| `clone with` | 8.5 | hand-written withers | `clone($this, ['showtimes' => …])` | 8 |
| Pipe operator `\|>` | 8.5 | nested arrays in the Pathé mapping | readable pipeline | 15 |
| URI extension: building web service calls | 8.5 | `PatheClient` assembles its paths by concatenation and `rawurlencode()`, the base `https://www.pathe.fr/api/` lives as a string (`PatheClient::BASE_URL`), the GitHub `/user/emails` URL and those of the local provider are strings | a `PatheEndpoints` object built on `Uri\Rfc3986\Uri` (`resolve()`, `withQuery()`), configuration URLs validated at startup, `ext-uri` declared in `composer.json` | 15 |
| URI extension: reading a received URL | 8.5 | `preg_match` on the `refCmd` booking link | `Uri\Rfc3986\Uri::parse()` then reading the host and the path | 15 |
| `array_first`/`array_last` | 8.5 | `reset()`/`end()` on the showtimes | native functions | 12 |
| `final` promoted properties | 8.5 | — | `final` on promoted properties | 8 |
| `clamp()` | 8.6 (polyfill) | `max(1, min(50, $radius))` | `clamp()` in `Radius` | 4 |
| `SortDirection` enum | 8.6 (polyfill) | `'asc'`/`'desc'` as strings in lists | `\SortDirection` | 16 |
| `Time\Duration` | 8.6 (polyfill-time) | durations in whole minutes | `Time\Duration` | 3 |
| `readonly` default values, partial application | 8.6 | — | — | shown (slide) |
| Self-validating value objects | — | slugs, coordinates, radius as scalars | `CinemaSlug`, `Coordinates`, `Radius` | 1, 4 |
| Parse, don't validate (boundary) | — | inconsistent Pathé shapes propagated | normalization in `Pathe` | 1 |
| `find`/`get` pair | — | `findBySlug(): ?array` everywhere | `find(): ?X`, `get(): X` | 6 |
| Result for expected failures | — | `plan(): array\|false` | `PlanResult` | 5 |
| Exception hierarchy per boundary | — | `RuntimeException('Pathé error')` | `PatheApiException`, `BotBlockedException` | 11 |
| Tell, don't ask | — | `$showtime['status'] === 'available' && …` | `$showtime->isBookable($now)` | 7 |
| Immutability and withers | — | setters on a shared programme | withers | 8 |
| Entities with invariants | — | empty `User` then setters | named constructor, no setter | 7, 10 |
| Explicit boolean instead of an array | — | `AccountLinker::link(array $userInfo)` that reads `$userInfo['email_verified'] ?? false` | `UserInfo` with `VerifiedEmail` or `UnverifiedEmail` | 10 |
| Extension by registry | — | `match ($provider)` | interface + tagged services | 9 |
| Hidden inputs | — | `new \DateTimeImmutable('now', …)` in the services, "today" for a chain computed in two controllers | `ClockInterface` | 14 |
| Chain configuration | 8.1 | `app.chains` read as a raw array, time zone and country stored as strings | `Chain` value object, `\DateTimeZone` mapped by Doctrine, `ChainRegistry` | 18 |
| Typed configuration | — | `%env(PATHE_CITIES)%` split by hand | `%env(csv:…)%`, `%env(int:…)%` | 14 |
| Validated input DTOs | — | `$request->query->all()` | `#[MapQueryString]` + Validator | 4 |
| Output DTOs and API contract | — | `JsonResponse` built from arrays | DTO + ObjectMapper, precise OpenAPI | 16 |
| Translatable messages | — | flash messages translated in controllers with `TranslatorInterface::trans()` and `%placeholder%` arrays; version and status labels built from strings | `TranslatableMessage` objects, enums implementing `TranslatableInterface` | 16 |
| Doctrine `enumType` and embeddables | — | `string` columns, separate latitude and longitude | `enumType`, embedded `Coordinates` | 7 |
| Twig `strict_variables` | — | arrays and `default('')` | objects + `strict_variables` | 16 |
| PHPStan generics, array shapes | — | `@return array` | `list<Showtime>`, `array{…}` | 12 |
| PHPStan level and baseline | — | level 5 | max level, reduced baseline | 17 |
| Mutation testing (Infection) | — | untested guards | proven guards | 17 |

## 10. Exercise path

Each exercise starts from a precise point in the code, has a goal, and ends
when the tests pass again. Exercises 1 to 6 make up the 2 h path. The others
are extensions, to be done in any order.

| # | Theme | Indicative duration |
|---|---|---|
| 1 | Pathé boundary: value objects, response normalization | 25 min |
| 2 | Enums: versions and booking statuses | 15 min |
| 3 | Time: `ScreeningTime`, `Time\Duration` | 20 min |
| 4 | Planner input: DTO, `#[MapQueryString]`, `Radius`, `FilmCount` | 20 min |
| 5 | Planner output: typed collection, `PlanResult`, `#[\NoDiscard]` | 20 min |
| 6 | Repositories: `find`/`get`, business exceptions | 15 min |
| 7 | Entities: invariants, asymmetric visibility, property hooks, Doctrine | 45 min |
| 8 | Immutability: `readonly`, withers, `clone with`, deep cloning | 30 min |
| 9 | OAuth providers: registry, typed `UserInfo`, `#[\Override]` | 40 min |
| 10 | Accounts: typed `UserInfo` at linking, explicit verified email, `User` invariants | 30 min |
| 11 | Exceptions: Pathé hierarchy, targeted `catch` blocks, `JSON_THROW_ON_ERROR` | 30 min |
| 12 | Collections: typed lists, `array_find`, PHPStan generics | 30 min |
| 13 | Type system: `strict_types`, returns, `never`, typed constants | 30 min |
| 14 | Hidden inputs: clock, typed configuration, Null Object, assertions | 30 min |
| 15 | URLs and transformations: web service calls with the URI extension, pipe `\|>` | 35 min |
| 16 | Output: API DTOs, ObjectMapper, `SortDirection`, translatable messages, strict Twig | 40 min |
| 17 | Tooling: PHPStan max, Infection | 30 min |
| 18 | Chains and time zones: `Chain` value object, `\DateTimeZone` in Doctrine | 30 min |

Total ≈ 8 h 35. The trainer guide (`docs/exercises.md`) describes for
each exercise: the starting point, the goal, the pitfalls, the PHP version
concerned and the link with the deck.

## 11. Error handling (v1)

- **Sync**: a network error on a request is logged, then
  the sync moves on to the next pair. A 403 or a 429 stops the sync.
  The command returns a non-zero exit code on failure.
- **Planner**: invalid input returns the form with its errors (web)
  or a 422 (API). The absence of a programme is not an
  error: it is an empty result with a message.
- **Sign-in**: a refusal or an error from the provider redirects to the
  sign-in page with a message. No technical detail is displayed.
- **API**: errors as `application/problem+json`, without a trace.

## 12. Tests and quality

- **PHPUnit**, with three rules:
  - only essential tests, and only observable behavior: use cases, HTTP
    responses, database outcome, security rules. No test of implementation
    details (mappers, repositories, URL building): that keeps the tests
    valid during the workshop refactorings;
  - every collaborator is built with a test builder (`tests/Builder/`):
    entities, the fake Pathé API, OAuth clients, planner inputs. Real
    objects are preferred over mocks;
  - every test follows Arrange / Act / Assert.
- What is covered: catalog sync outcome, chaining rules and programme
  choice, planner use case, account linking rules (verified email only),
  GitHub verified-email reading, web journeys, API, web/API parity, French
  translation of the UI.
- **PHPStan**: `phpstan.dist.neon` at level 5, with no errors.
  `phpstan-max.neon` at max level with a baseline, which serves as the
  workshop goal.
- **PHP-CS-Fixer**: `@Symfony` rules.
- **Makefile**: `up`, `down`, `sh`, `console`, `composer`, `test`,
  `phpstan`, `cs`, `sync`, `db-dump`, `db-load`, `logs`.

## 13. Deliverables for October 8

1. The `workshop` repository: application, Dockerfile, compose, Makefile, tests.
   Attendees receive this complete and working v1 (tag
   `v1.0.0`) as a starting point; the exercises evolve it.
2. `data/catalog.sql.gz`, prepared and committed on Wednesday, October 7.
3. `README.md`: getting started, optional OAuth accounts, local
   provider.
4. `docs/exercises.md`: the trainer guide for sections 9 and 10.

## 14. Risks and open questions

- **Deadline**: 4 days. If time runs short, we remove, in this order, the
  Mercure notification, then Autocomplete (replaced by a dropdown
  list). The three sign-ins (Google, GitHub, local) stay in
  all cases.
- **Real OAuth accounts**: attendees cannot all create Google or GitHub
  applications. The local provider is the default path.
  The real providers work if you supply credentials.
- **Pathé**: the API is unofficial and can change. The frozen dataset
  protects the workshop.
- **Alpine and musl**: lower performance than Debian, with no impact for
  local use.
- **Daylight saving time**: `catalog:shift-dates` shifts showtimes by whole
  days. Across a daylight saving change, local times move by one hour. This is
  acceptable for a reused dataset and is a discussion topic of exercise 3.

## 15. Future work: other cinema chains (TODO)

The project is generic so that other chains with an unlimited pass can be
added. Each one would get its own SDK under `src/Sdk/<Chain>/` (same rules as
the Pathé SDK) and an entry in `app.chains`. Research of October 4, 2026;
prices change often, the point is that the product exists.

| Chain | Country | Unlimited product | Showtime data |
|---|---|---|---|
| Pathé | France | CinéPass (-26, Classique, Silver, Gold, Duo option) | supported (unofficial `/api/`) |
| Pathé | Netherlands | Pathé Unlimited / Unlimited Gold / Family | unknown; same platform as France likely, untested |
| Pathé | Switzerland | Pathé Pass Unlimited | unknown (403 to automated fetches) |
| UGC (+ mk2 and partners) | France | UGC Illimité (7J/7, Semaine, Week-end, Duo, -26, Famille) | no JSON API identified |
| UGC | Belgium | UGC Unlimited | unknown |
| Megarama | France | Megarama Illimitée | unknown, offer date unverified |
| Cineworld | UK, Ireland | Cineworld Unlimited | semi-public QuickBook JSON (`/uk/data-api-service/v1/quickbook/10108/...`), documented by community libraries |
| Odeon | UK | myLIMITLESS / myLIMITLESS Plus | unknown |
| Everyman | UK | Everywhere membership | unknown |
| Cinema City | Poland | Cinema City Unlimited | public QuickBook JSON (tested), same platform as Cineworld |
| Kinepolis | Belgium | Kinepolis Unlimited | unknown |
| Cinesa | Spain | Unlimited Card | unknown |
| UCI Kinowelt | Germany | UCI Unlimited Card | unknown |
| Yorck Kinogruppe | Germany (Berlin, Munich) | Yorck Unlimited | unknown |
| Cinfinity | Germany (independent cinemas) | Cinfinity | unknown |
| Cineville / nonstop | Netherlands, Belgium, Germany, Sweden, Austria (independent cinemas) | Cineville pass | unknown |
| Nordisk Film Biografer | Denmark, Norway | Bioplus Unlimited | unknown |
| AMC Theatres | USA | AMC Stubs A-List (up to 4 films a week) | official API, key required |
| Regal | USA | Regal Unlimited | undocumented JSON endpoints |
| Alamo Drafthouse | USA | Season Pass (one film a day) | public undocumented JSON (`/s/mother/v2/schedule/market/{market}`) |

Not unlimited (capped plans): Cinemark Movie Club, Cineplex CineClub, PVR INOX
Passport, Picturehouse (new 2026 scheme), Kinepolis France, Cinépolis, CGV.
No subscription found: CGR, mk2 (covered by UGC Illimité), Planet (Israel,
same QuickBook platform), Yelmo, Vue, Showcase, SF Bio, Finnkino, Hoyts, Event,
Village, Cineplexx, UCI Italy.

Before adding a second chain:

- **TODO: chain-scoped identifiers.** Slugs and `V{n}S{n}` showtime
  identifiers are Pathé's. The primary keys must include the chain (or become
  UUIDs with a unique `(chain, external_id)` key) before another chain can
  collide with them.
- **TODO: one film across chains.** The same film has a different slug in
  each chain: matching (by title, year, running time, or an external id such
  as TMDB) is needed so that "already seen" and "never the same film twice"
  work across chains.
- **TODO: cities shared by several chains.** A city is currently a Pathé
  city. Cities of different chains (Lyon for Pathé and UGC) must be merged,
  or the planner must search by position only.
- **TODO: areas that span time zones.** Within a radius of 50 km, cinemas
  may follow different time zones (border areas, the USA). The date filter
  uses each cinema's local day; the form must say which local day it means.
- **TODO: QuickBook adapter.** Cineworld and Cinema City share the QuickBook
  platform: one SDK could serve both, with the chain as a parameter.
