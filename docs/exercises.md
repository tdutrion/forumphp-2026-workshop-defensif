---
title: Trainer guide
nav_order: 4
---

# Trainer guide — defensive PHP workshop

The application is clean, tested and secure. What improves is how easy it is to
use its classes and functions correctly: contracts become explicit and checked
by the language. No exercise fixes a security flaw.

Rules of the game: each exercise ends when `make test` is green again (and
`make phpstan`, `make cs-check`, as in the CI). The tests mostly target
observable behavior: the pages and the JSON of the API never change; some unit
tests and builders will have to follow the new signatures, by design. To measure
the distance covered: `make phpstan-max` (regenerate the baseline with
`make phpstan-baseline` at the start of the session, never in between, or the
progress measure is lost).

2-hour track (16:00 to 18:00): exercises 1 to 6, timed for experienced
developers. 8 minutes of welcome, then the six exercises (100 minutes: about
2 minutes of briefing each, the rest hands-on; exercises 2 and 3 end with a
1 min 30 discussion), then 12 minutes of wrap-up and questions. The following
ones are extensions, in any order. The guide assumes they are done in order: an
exercise may build on the names an earlier one introduced. Each exercise has a
reference solution, one branch per exercise: anyone behind starts the next
exercise with `make exercise n=N`, which stashes their work and switches to the
reference solution of the previous one (exercises 1 to 6 add no migration and no
dependency, so it is instant). Each exercise of the track also gives an
acceptance test to bring in at the start, red until the exercise is done.

The code already shows the target style in a few places, to point at:
`FilmCatalogQuery` (readonly DTO bound with `#[MapQueryString]`), the backed
enums `WorkLinkStatus` and `Theme` (with `enumType`), the `Work` entity
(constructor, `describe()`).

## 1. Pathé boundary (10 min) — PHP 5.0 to 8.1

- **Starting point**: `PatheClient::getShowtimes(string $showSlug, string $cinemaSlug): array|false`
  (also `getShow()` and `getCinemaProgramme()`) and
  `PatheMapper::mapShowtimes(array $raw, string $timezone): array` / `mapCinema(array $raw): array`,
  called by `CatalogSynchronizer`. The SDK is already framework-free (PSR
  interfaces, psr-discovery as a fallback): the boundary is about what crosses
  it, not about how it talks HTTP.
- **Observation**: two strings of the same type side by side (they can be
  swapped), a raw response that is `[]` or an object indexed by date, `x`/`y`
  meaning latitude/longitude.
- **Goal**: value objects `ShowSlug`, `CinemaSlug` (validation in the
  constructor, `InvalidArgumentException`), `GpsPosition::fromApi(array)`
  (x/y become latitude/longitude; no position → `null`),
  `PatheShowtimes::fromApiResponse(array)` (normalization once at the
  boundary, `array_is_list`) passed to `mapShowtimes(PatheShowtimes, \DateTimeZone)`,
  named arguments. Everything stays in `App\Sdk\Pathe`, which keeps depending on
  PSR only (failures still return `false`: exercise 11). Update the SDK README.
- **Acceptance test**, to start red:
  `git checkout origin/exercise/01-pathe-boundary -- tests/Integration/Catalog/Sync/CatalogSyncTest.php`
  (a response of an unexpected shape is skipped silently: `errors` stays 0).
- **Deck**: Part 3 (the `getShowtimes → PatheShowtimes` case), Part 5.

## 2. Enums (15 min) — PHP 8.1

- **Starting point**: the version strings (`Showtime::$version`, `PlanType::VERSIONS`,
  `FilmCatalogQuery::$version`, the `vost`/`vo` tests in `ShowtimeRepository`
  and `FilmRepository`, the list in `film/catalog.html.twig`); the travel mode
  strings (`PlanType`, `PlannerService`) and `ChainBuilder::TRAVEL_MODES`,
  whose `?? self::TRAVEL_MODES['transit']` silently hides an unknown mode; the
  `'available'` status repeated in the queries of `ShowtimeRepository` and
  `FilmRepository`; the raw Pathé status and version strings that
  `PatheMapper::mapShowtimes()` copies as they are.
- **Goal**: `enum ShowtimeVersion: string` (with `label()`), `enum TravelMode: string`
  with `speedKmh()` and `fixedMinutes()`,
  `enum BookingStatus: string` with `isBookable()`, `tryFrom()` at the boundary
  (an unknown value skips the showtime and is logged), `enumType` in the
  Doctrine mapping, `EnumType` in the form, exhaustive `match`. The raw SQL
  queries pass `->value`. The cases are the values Pathé sends and the catalog
  stores: `BookingStatus` `available`, `soldout`, `cancelled` (check with a
  `SELECT DISTINCT status` before mapping: a guessed `sold_out` breaks the
  hydration of the dev catalog while the tests stay green); `ShowtimeVersion`
  `vf`, `vost`, `vo`, `vfst`, with `isOriginal()` for the `vost`/`vo` tests of
  the repositories.
- **Acceptance test**, to start red:
  `git checkout origin/exercise/02-enums -- tests/Unit/Planner/TravelModeTest.php tests/Unit/Catalog/BookingStatusTest.php tests/Unit/Catalog/ShowtimeVersionTest.php`
  (the enums do not exist yet).
- **Discussion topic**: why the languages (`Cinema::$language`,
  `Film::$originalLanguage`, the nationality table of `PatheMapper`) are an open
  set that stays a validated string rather than an enum.
- **Deck**: Part 5 (`BookingStatus`).

## 3. Time (20 min) — PHP 5.5, 8.3, 8.6 (polyfill)

- **Starting point**: `PlannerService::plan()` (UTC strings turned into
  integer timestamps), `PlannerService::format()` (a `DateTimeZone` built from
  the `'timezone'` string of each row), `ChainBuilder` (integer timestamps,
  constants in minutes `MARGIN_MINUTES`, `ADS_MINUTES`),
  `PatheMapper::mapShowtimes()` (a malformed Pathé date aborts the whole sync),
  `CatalogSynchronizer` (`date()` + `strtotime()`).
- **Goal**: `final readonly class ScreeningTime` on `DateTimeImmutable` in UTC
  with `localTime(\DateTimeZone)`, `plus(Duration)` and
  `until(ScreeningTime): Duration`; `Time\Duration` (`symfony/polyfill-time`)
  for the margin, the ads and the travel (a `Duration` cannot be a class
  constant: a private method or a promoted property); precise date exceptions
  (`DateMalformedStringException`, `DateInvalidTimeZoneException`, 8.3) caught
  at the boundary. "Now" stays `new \DateTimeImmutable('now')` until
  exercise 14. The JSON times of `/api/plans` do not change. The polyfill's
  `Duration` is new to everyone: `fromMinutes()`, `fromHours()`, `add()`,
  `negate()`, `Duration::compare()`, the public `seconds` and `nanoseconds`
  (no bridge to `DateInterval`: `plus()` adds the seconds by hand).
- **Acceptance test**, to start red:
  `git checkout origin/exercise/03-time -- tests/Unit/Planner/ScreeningTimeTest.php tests/Unit/Sdk/Pathe/PatheMapperTest.php`
  (`ScreeningTime` also needs `compare()` and `isAfter()`, and `PatheMapper` an
  optional PSR-3 logger to log the skipped showtimes).
- **Discussion topic**: `catalog:shift-dates` shifts showtimes by whole days;
  across a daylight saving change, a 21:40 showtime becomes 20:40 or 22:40
  locally. Instant vs local time: which one should a shift keep?
- **Deck**: Part 5 (`ScreeningTime`).

## 4. Planner input (20 min) — PHP 8.0, 8.6 (polyfill)

- **Starting point**: `PlannerService::plan(array $criteria, string $userId)`
  (`$criteria['radius'] ?? self::DEFAULT_RADIUS_KM`,
  `($criteria['from'] ?? null) ?: null`) and `planFromForm(array $data, …)`,
  which resolves `city`/`position`; `PlanType` without `data_class`, submitted
  by hand from the query string in `Api\Controller\PlanController` and turned
  into `{field, message}` errors; the time range as two `'H:i'` strings
  compared as text.
- **Goal**: `PlanRequest` DTO (readonly, constructor promotion except for the
  text fields normalized in the constructor, `#[Assert\...]`, the enums of
  exercise 2) with the fields of `PlanType` (`city`/`position`, resolved by
  `plan()` itself: `planFromForm()` disappears), used as `data_class` of
  `PlanType` (built at once by its `mapFormsToData()`) and bound with
  `#[MapQueryString(validationFailedStatusCode: 422)]` in
  `Api\Controller\PlanController` (the default status is 404), refusing
  unknown parameters (`ALLOW_EXTRA_ATTRIBUTES => false` and
  `COLLECT_EXTRA_ATTRIBUTES_ERRORS => true` in its `serializationContext`:
  without the second one, the Serializer throws and the API answers 500); an
  empty parameter (`films=`, `travelMode=`) still means "not given", which the
  ObjectNormalizer refuses for an `int` or an enum: a denormalizer dedicated to
  `PlanRequest` drops the empty values first (`PlanRequestDenormalizer`); the date checked against the days with bookable
  showtimes by an `#[AvailableDate]` constraint (the choices of the former
  `ChoiceType` did it);
  `ApiExceptionListener` renders the violations in the existing problem format
  (`title`, `errors[{field, message}]`); `plan(PlanRequest, …)`; value objects
  `FilmCount` (with the polyfill 8.6 `clamp()` when falling back to fewer films:
  the 1–8 range itself stays a 422) and `TimeRange`, built from the validated
  DTO (the Serializer cannot build value objects whose constructor throws).
- **Acceptance test**, to start red:
  `git checkout origin/exercise/04-planner-input -- tests/Functional/Api/PlanApiTest.php`
  (an unknown `radius` is reported on the root form `plan`, not on `radius`;
  the same file checks that empty parameters mean "not given").

## 5. Planner output (20 min) — PHP 8.2, 8.5

- **Starting point**: `plan(): array|false` (`false` = no showtime, shown as
  `'no_showtime'`) with `'reason'` as a string (`'no_programme'`,
  `'not_enough_programmes'`, `'fewer_films'`; `'unknown_location'`, returned
  without a `'films'` key since exercise 4), the number of films kept in a
  loose `'films'` key and the draw in `'seed'`; each caller (`HomeController`,
  `Api\Controller\PlanController`, `home/index.html.twig`) tests the strings
  again.
- **Goal**: `PlanResult`: a success (programmes, number of films, seed,
  optional `PlanNotice` enum `FewerFilms`/`NotEnoughProgrammes`) or a failure
  (`PlanFailure` enum `NoShowtime`/`NoProgramme`/`UnknownLocation`); the
  programmes stay arrays (exercise 8 makes them objects); the API JSON keeps its
  shape (`reason` = the enum value); `#[\NoDiscard]` on `plan()` and on the withers:
  `FilmCount::fewer()`, `ScreeningTime::plus()`.
- **Acceptance test**, to start red:
  `git checkout origin/exercise/05-planner-output -- tests/Unit/Planner/PlanResultTest.php`
  (`PlanResult` with `isSuccess()`, `reason()`, a `ProgrammeList` with
  `isEmpty()`, `PlanFailure::messageKey()`; a success without programme is an
  `InvalidArgumentException`).
- **Deck**: Part 8 (Result, `#[NoDiscard]`).

## 6. Repositories (15 min) — PHP 7.1, 8.0

- **Starting point**: `FilmRepository::findBySlug(string): ?array` (a wrapper
  over `findBySlugs()`), whose `null` is tested by `Web\Controller\FilmController`
  and `Api\Controller\FilmController`; `$em->find(Film::class, $slug)` +
  `return false` in `SeenFilmService` and `UnwantedFilmService`.
- **Goal**: `findFilm(FilmSlug): ?Film` / `getFilm(FilmSlug): Film` pair
  (`FilmSlug` is the catalog's, distinct from the SDK's `ShowSlug`),
  `?? throw new FilmNotFound(...)`, domain exception translated into a 404 in
  the controller, which receives a `FilmSlug` (a value resolver and a route
  requirement `FilmSlug::PATTERN`); the film page and `/api/films/{slug}` keep
  their output (the open-data ids are read through `film.work`).
- **Trap to show**: a forgotten `findBySlug()` call silently becomes Doctrine's
  magic `findBy(['slug' => …])`, which returns `[]`, never `null`.
- **Trap to expect**: an autoconfigured value resolver has priority 0, after
  the built-in `RequestAttributeValueResolver` (100), which passes the raw
  string: a `TypeError`, so a 500 instead of a 404. Give it a priority above
  100 (`#[AutoconfigureTag('controller.argument_value_resolver', ['priority' => 150])]`).
- **Acceptance test**, to start red:
  `git checkout origin/exercise/06-repositories -- tests/Unit/Catalog/FilmSlugTest.php tests/Unit/Catalog/FilmSlugValueResolverTest.php tests/Integration/Catalog/FilmRepositoryTest.php`
  (`FilmSlug` also has a `MAX_LENGTH` of 150).
- **Deck**: Part 6 (`find()` vs `get()`).

## 7. Entities (45 min) — PHP 8.4

- **Starting point**: setters and nullables in `Catalog\Entity\*` (`Work`
  already shows the target: constructor, `describe()`); `CatalogSynchronizer`
  updates the entities through setters; separate nullable `Cinema::$latitude` /
  `$longitude`; `Showtime::$capacity` stored as a string (`"244"`), never read;
  the bookable rule only exists as SQL in the repositories.
- **Goal**, on `Showtime` and `Cinema`: named constructors and
  intention-revealing mutators (`$showtime->reschedule(...)` rather than
  setters); asymmetric visibility `public private(set)` instead of trivial
  getters; `set` property hook on the capacity (or a `Capacity` value object)
  and its migration (Doctrine hydration bypasses the hook); a domain
  `?Coordinates` exposed by a virtual `get` hook over the two columns (Doctrine
  has no nullable embeddable; an `#[ORM\Embedded]` would need
  `columnPrefix: false` to keep the columns read by raw SQL), built from the
  SDK's `GpsPosition` in `CatalogSynchronizer`; Tell don't ask
  (`$showtime->isBookableAt($now)`, relying on `BookingStatus::isBookable()`,
  unit-tested).
- **Deck**: Part 4 (asymmetric visibility), Part 7 (property hooks, Tell don't ask).

## 8. Immutability (30 min) — PHP 8.1 to 8.5

- **Starting point**: programmes and their showtimes handled as mutable arrays
  in `ChainBuilder::summarize()` and `PlannerService::format()`, read by key in
  `ProgrammeSelector`, `Api\Controller\PlanController` and
  `home/_programme.html.twig`.
- **Goal**: `final readonly class Programme` and `ScheduledShowtime` (the
  candidate showtimes of the search, built once from the SQL rows) in
  `PlanResult`; withers with `clone with` (8.5), defined in the class (a
  readonly property cannot be cloned with from outside) and carrying
  `#[\NoDiscard]` (`ScheduledShowtime::withTransition()`); `final` property
  promotion (8.5): why it adds nothing in a `final` class; deep cloning (8.3,
  `__clone` may reinitialize a readonly property): not needed here because
  every part of a programme is immutable, to discuss (when would a `__clone`
  be needed? a `DateTime` in a readonly object). The JSON of `/api/plans` and
  the page stay identical.
- **Deck**: Part 4.

## 9. OAuth providers (40 min) — PHP 8.3

- **Starting point**: `OAuthUserInfoExtractor::extract()` and its
  `match ($provider) { 'local' => …, 'github' => …, default => OIDC }`
  returning an array, and a second table per provider, `OAuthProviders::SCOPES`.
- **Goal**: `UserInfoProvider` interface (user info and scopes) returning a
  readonly `UserInfo`, tagged services indexed by provider
  (`#[AutoconfigureTag]`, `#[AsTaggedItem]`, `#[AutowireLocator]`),
  `#[\Override]` on the implementations, an OpenID Connect provider as the
  fallback so that an OIDC provider (Keycloak in the tests, LinkedIn) still
  needs configuration alone; a non-OIDC provider (e.g. Discord) = configuration
  + one class.

## 10. Accounts (30 min)

- **Starting point**: `AccountService::loginWithProvider()` and `linkProvider()`
  reading the verified email of the identity; `new User()` + setters, persisted
  before its first connection; "at least one connection" checked by
  `AccountService::removeLinkedAccount()`.
- **Goal**: explicit verified email (`VerifiedEmail` / `UnverifiedEmail`) in
  the `UserInfo` of exercise 9; `User::fromProviderIdentity(string $provider, UserInfo)`
  so that a `User` is born with its first connection; the "at least one
  connection" invariant inside `User`.

## 11. Exceptions (30 min) — PHP 5.5 to 8.3

- **Starting point**: `PatheClient` (generic `\RuntimeException` on HTTP 403/429
  and when no PSR-18/17 implementation is found; `false` for every other
  failure), `CatalogSyncRunner` (the same `\RuntimeException` when the lock is
  taken), `SyncCommand` and `SyncCatalogHandler` (`catch (\RuntimeException)`
  for both), `LocationResolver::fromPosition()` and the genres of
  `FilmCatalog`/`FilmRepository` (`json_decode()` + result test). Already in
  place, to show: `JSON_THROW_ON_ERROR` in both SDKs, `finally` around the lock.
- **Goal**: `PatheApiException` hierarchy (abstract) → `BotBlockedException`
  (403), `RateLimitedException` (429), `PatheUnavailableException` (5xx,
  network: replaces `false`, caught per cinema by the synchronizer, so a
  partial sync stays partial); a distinct exception for the lock; targeted
  `catch` and multi-catch; `@throws` checked by PHPStan (`exceptions.check` in
  `phpstan.dist.neon`, with `PatheApiException` as the only checked exception
  class: by default every exception but `Error` is checked, and `make phpstan`
  turns red everywhere); `JsonException` (`JSON_THROW_ON_ERROR` for the genres
  of `FilmCatalog`/`FilmRepository`, our own data); `json_validate()` (8.3) in
  `LocationResolver`, where invalid JSON from the browser stays an ordinary
  `false`. The Wikidata
  SDK keeps `false`: its failure never stops a sync.
- **Deck**: Part 8.

## 12. Collections (30 min) — PHP 5.6, 8.1, 8.4, 8.5

- **Starting point**: the candidate showtimes of
  `ChainBuilder::build(array $showtimes, …)` (`ScheduledShowtime`, exercise 8),
  the `$path` arrays of `ChainBuilder::explore()` (`$path[\count($path) - 1]`,
  `in_array(…, array_column($path, 'workId'))`), the lists of programmes in
  `ProgrammeSelector`, `ProgrammeList` (a bare `list<Programme>` since
  exercise 8) and `Programme::$showtimes`.
- **Goal**: `ScheduledShowtimeList` (typed variadic constructor
  `ScheduledShowtime ...$showtimes`, `Countable&IteratorAggregate`) and
  `ProgrammeList` on the same pattern, immutable (`with()`, `sortedByStart()`,
  `take()` carry `#[\NoDiscard]`); `array_any` (8.4) for "is this work already
  in the path", `array_first` / `array_last` (8.5) for the ends, `array_find`
  (8.4) wherever a loop looks for one element (the primary verified email of
  GitHub); PHPStan generics (`@implements \IteratorAggregate<int, Programme>`,
  `list<…>`), first-class callables (`ScheduledShowtime::fromRow(...)`).

## 13. Type system (30 min) — PHP 7.0 to 8.4

- **Starting point**: only the generated migrations declare
  `strict_types=1`; `@param array` docblocks without a shape; untyped constants
  (`ProgrammeSelector::POOL`, `CatalogCalendar::CACHE_KEY`, `FilmPage::PAGE_SIZES`…).
- **Goal**: `strict_types` everywhere (PHP-CS-Fixer rule `declare_strict_types`,
  with `setRiskyAllowed(true)`, so the CI enforces it on new files too), precise
  return types and array shapes, typed constants (8.3), `never`,
  `#[\Deprecated]` (8.4) on methods kept for outside callers only
  (`failOnDeprecation` fails the suite as long as the application calls them).

## 14. Hidden inputs (30 min)

- **Starting point**: the current instant built in place:
  `new \DateTimeImmutable('now', …)` in `PlannerService::plan()`,
  `CatalogSynchronizer` (the day synchronized and the `$now` given to
  `WorkLinker`), `'today'` in `ShiftDatesCommand`, where the plan form and
  request are built for `CatalogCalendar::availableDates()`, in the account
  services (`ApiTokenService` without a time zone); `usleep()` in both SDKs;
  `PATHE_CITIES` (and `OAUTH_PROVIDERS`) split with `explode()` + `trim` +
  `array_filter`; `CatalogUpdatePublisher` with `?HubInterface $hub = null`
  (never null in the application); `PatheClient` and `WikidataClient` falling
  back to psr-discovery when a collaborator is missing.
- **Goal**: `ClockInterface` (`composer require symfony/clock`; `MockClock` in
  the tests, which also makes "the form opens on tomorrow after the last
  showtime" testable at any hour), `%env(csv:PATHE_CITIES)%` (an empty value
  still means every city; spaces are no longer trimmed), Null Object
  (`NullCatalogPublisher` via `new` in the initializer), optional SDK
  collaborators defaulting to a Null Object (`NullLogger`) rather than
  discovery, `assert()` on the planner's internal invariants (active in
  development and tests only).

## 15. URLs and transformations (35 min) — PHP 8.5

- **Starting point**:
  - `PatheClient` builds its paths by concatenation
    (`'show/'.rawurlencode(…).'/showtimes/'.rawurlencode(…)`, `'cinema/…/shows'`,
    `'?'.http_build_query(…)`) and the base `https://www.pathe.fr/api/` only
    exists as a string (`PatheClient::BASE_URL`, `$baseUrl` constructor
    argument); `WikidataClient` does the same with `BASE_URL.'?'.http_build_query()`;
  - the GitHub provider (exercise 9) calls a hard-coded URL; the local
    provider URLs are environment strings handed to `knpu_oauth2_client.yaml`;
  - `PatheMapper::mapShowtimes()` checks the booking link and extracts the
    showtime id with `preg_match`; `mapFilm()` checks the poster with
    `str_starts_with()`;
  - nested arrays in `mapCinema()` / `mapFilm()`.
- **Goal**:
  - a `PatheEndpoints` object built on `Uri\Rfc3986\Uri`: the base is parsed
    once and checked (https, a host, a path ending in `/`: `new Uri('')` does
    not throw), each call is obtained with `resolve()` (the slugs of exercise 1
    guarantee there is nothing to encode: `resolve()` does not encode) and
    `withQuery()`, the PSR-17 factory receives `$uri->toString()`; the cache key
    stays derived from the path; the same `withQuery()` in `WikidataClient`;
  - the GitHub emails endpoint resolved from the provider's API domain (the
    URL of `/user`, `resolve('/user/emails')`: GitHub Enterprise included); the
    local provider URLs through a custom `%env(uri:…)%` processor that fails,
    with the name of the variable, on anything that is not an absolute http(s)
    URL with a host (not `https_uri`: the offline provider is plain http);
  - `Uri\Rfc3986\Uri::parse()` to read the scheme, host and path of the
    booking link (the id is a path segment);
  - `ext-uri` declared in `composer.json` (always present in PHP 8.5, but the
    contract becomes explicit);
  - pipe operator `|>` to chain the mapper's transformations.
- **Trap to show**: `Uri::parse('http:///x')` succeeds, with an *empty* host
  (not `null`), and `new Uri('')` does not throw: checking `null === getHost()`
  is not enough.
- **Discussion topic**: RFC 3986 (`Uri\Rfc3986\Uri`) for server-to-server
  calls, WHATWG (`Uri\WhatWg\Url`) for what mimics a browser; what
  `rawurlencode()` used to do by hand, and what guarantees it now.

## 16. API output and templates (40 min) — PHP 8.6 (polyfill)

- **Starting point**: `JsonResponse` built from arrays in `Api\Controller\*`;
  `?order=asc|desc` read as a string in `Api\Controller\SeenFilmController::list()`,
  upper-cased by `SeenFilmService::listSeenFilms()` and checked again in
  `FilmRepository::findBySlugs(array, string $direction)`; `'ASC'`/`'DESC'`
  strings in the `orderBy()` calls of the repositories (Doctrine reports the
  deprecation in the profiler: "use an instance of SortDirection instead");
  Twig templates fed with arrays (`strict_variables: false`); flash messages
  translated where they are added
  (`$this->translator->trans('security.account_linked', ['%provider%' => $provider])`
  in `ConnectController`, `trans($exception->getMessageKey())` in
  `OAuthAuthenticator`, which drops the message data, and `SettingsController`).
- **Goal**: output DTOs filled by ObjectMapper (`composer require symfony/object-mapper`)
  or the Serializer, described in the documentation with
  `#[OA\Response(content: new Model(type: …))]`, with the same JSON as today
  (the tests compare it key by key); `\SortDirection` (polyfill 8.6) in the
  repository signatures and a backed `SortOrder` enum at the HTTP boundary
  (`\SortDirection` is a pure enum: no `from()`), bound by
  `#[MapQueryString(validationFailedStatusCode: 400)]`; `TranslatableMessage`
  (or `t()`) in the flash bag, translated by the layout (`|trans`);
  `strict_variables: true` and objects passed to templates.

## 17. Tooling (30 min)

- **Starting point**: `make phpstan` (level 5, green, run by the CI) and
  `make phpstan-max` (max level minus `phpstan-baseline.neon`, regenerated by
  `make phpstan-baseline`).
- **Goal**: make the baseline shrink; `composer require --dev infection/infection`,
  run it with coverage (`XDEBUG_MODE=coverage`: Xdebug is off by default) on one
  unit-tested class (`--filter=src/Planner/ProgrammeSelector.php --threads=1`:
  the other tests share one database), and show that an untested safeguard lets
  a mutant survive.

## 18. Cinema chains and time zones (30 min) — PHP 8.1, 8.4

- **Starting point**: the `app.chains` parameter (`config/packages/chains.yaml`)
  read as a raw array in `CatalogSynchronizer` (`$this->chains[self::CHAIN]`:
  `timezone`, `country`, `language`), `ShiftDatesCommand`
  (`$this->chains['pathe']['timezone']`) and `Web\Controller\HomeController`
  (with `app.chains_planned`, for the landing page); `Cinema::$timezone`,
  `Cinema::$country`, `City::$country` and the `$chain` column of
  `City`/`Cinema`/`Film` as free strings; `CatalogSynchronizer::CHAIN = 'pathe'`.
- **Goal**: a `CinemaChain` value object (identifier, name, countries as
  `CountryCode` checked with `Intl\Countries`, default `\DateTimeZone`, language)
  built once from the configuration, a `CinemaChainRegistry` service (also
  serving the planned chains of the landing page); the cinema keeps its own
  time zone (a chain can span several: AMC, Cineworld UK/IE), stored as a
  `\DateTimeZone` through a custom Doctrine type or a property hook; the SDK
  still receives a `\DateTimeZone`, never the application's object; then sketch
  how a second SDK would plug in (Cineworld, the spec's "QuickBook adapter").
  `CinemaChain`, not `Chain`: the planner's `ChainBuilder` chains programmes.

## 19. UI component contracts (20 min) — PHP 8.1, 8.4

- **Starting point**: the anonymous components of `templates/components/` take
  free strings (`<twig:Button variant="primray">`, `<twig:Alert type="warning">`,
  `tag`, `size`, `tone`): `html_cva()` silently ignores an unknown value and
  renders the base classes without colours (an Alert also loses its `flash-*`
  hook), and an unknown `tag` renders an invalid element.
- **Goal**: class components (`#[AsTwigComponent]` in `src/Twig/Components/`,
  already mapped in `twig_component.yaml`) whose props are enums
  (`ButtonVariant`, `ButtonSize`, `ButtonTag`, `AlertType`, `BadgeTone`);
  `mount(string …)` converting with `from()` (or `tryFrom()` + `assert()`,
  active in development only), since templates pass strings (the flash type
  included); the style guide loops on `enum_cases()`; a component test proves
  that an unknown value fails. Update the component table of
  `docs/design-system.md`.
- **Link with the design system**: `docs/design-system.md`.

## Practices already in place (to show, not to fix)

Parameterized queries, CSRF (forms and logout), Twig escaping,
OAuth state + PKCE, account linking on verified email only (compared exactly),
random API tokens stored hashed (`ApiTokenService`), `#[\SensitiveParameter]`,
no user state in services (worker mode), instants stored in UTC and shown
in each cinema's time zone, third-party SDKs that depend on PSR interfaces
only (`src/Sdk/Pathe`, `src/Sdk/Wikidata`), every user-facing string
translatable, mutual exclusion with `symfony/lock` stored in MySQL
(`CatalogSyncRunner`), duplicate-safe marks (unique key + `INSERT IGNORE`),
clickjacking protection (`X-Frame-Options`, `frame-ancestors`), no `.env` in
production (configuration from the environment only), a production image
without development dependencies, CI actions pinned by commit SHA with
read-only permissions, tests that fail on any deprecation, notice or warning.
