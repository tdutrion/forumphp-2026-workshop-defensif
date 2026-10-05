# Trainer guide — defensive PHP workshop

The application is clean, tested and secure. What improves is how easy it is to
use its classes and functions correctly: contracts become explicit and checked
by the language. No exercise fixes a security flaw.

Rules of the game: each exercise ends when `make test` is green again.
The tests mostly target observable behavior; some unit tests will have to
follow the new signatures, by design. To measure the distance covered:
`make phpstan-max` (the baseline shrinks).

2-hour track: exercises 1 to 6. The following ones are extensions, in any
order.

## 1. Pathé boundary (25 min) — PHP 5.0 to 8.1

- **Starting point**: `PatheClient::getShowtimes(string $showSlug, string $cinemaSlug): array|false`
  and `PatheMapper::mapShowtimes(array $raw, string $timezone): array` / `mapCinema(array $raw): array`.
  The SDK is already framework-free (PSR-18/17/6/3 only): the boundary is about
  what crosses it, not about how it talks HTTP.
- **Observation**: two strings of the same type side by side (they can be
  swapped), a raw response that is `[]` or an object indexed by date, `x`/`y`
  meaning latitude/longitude.
- **Goal**: value objects `ShowSlug`, `CinemaSlug` (validation in the
  constructor, `InvalidArgumentException`), `Coordinates::fromPathe(array)`,
  `ShowtimeList::fromApiResponse(array)` (normalization once at the
  boundary, `array_is_list`), named arguments.
- **Deck**: Part 3 (the `getShowtimes → ShowtimeList` case), Part 5.

## 2. Enums (15 min) — PHP 8.1

- **Starting point**: `Showtime::$version` (`'vf'`, `'vost'`…), `PlanType::VERSIONS`,
  the travel mode strings (`'walking'`, `'transit'`…) and `ChainBuilder::TRAVEL_MODES`,
  whose `?? self::TRAVEL_MODES['transit']` silently hides an unknown mode, the language codes
  (`Cinema::$language`, `Film::$originalLanguage`) and the nationality → language table of `PatheMapper`,
  the `'available'` status tested in `ShowtimeRepository::findCandidates()`, and the raw Pathé
  status strings that `PatheMapper::mapShowtimes()` copies as they are.
- **Goal**: `enum ShowtimeVersion: string` (with `label()`), `enum TravelMode: string`
  with `speedKmh()` and `fixedMinutes()`,
  `enum BookingStatus: string` with `isBookable()`, `tryFrom()` at the boundary,
  `enumType` in the Doctrine mapping, `EnumType` in the form, exhaustive `match`.
- **Deck**: Part 5 (`BookingStatus`).

## 3. Time (20 min) — PHP 5.5, 8.3, 8.6 (polyfill)

- **Starting point**: `PlannerService::plan()` (UTC strings turned into
  integer timestamps), `PlannerService::format()` (a `DateTimeZone` built from
  the `'timezone'` string of each row), `ChainBuilder` (integer timestamps,
  constants in minutes `MARGIN_MINUTES`, `ADS_MINUTES`).
- **Goal**: `ScreeningTime` on `DateTimeImmutable` in UTC with a
  `localTime(\DateTimeZone)` method, `Time\Duration` (`symfony/polyfill-time`)
  for the margin, the ads and the travel, precise date exceptions
  (`DateMalformedStringException`, 8.3).
- **Discussion topic**: `catalog:shift-dates` shifts showtimes by whole days;
  across a daylight saving change, a 21:40 showtime becomes 20:40 or 22:40
  locally. Instant vs local time: which one should a shift keep?
- **Deck**: Part 5 (`ScreeningTime`).

## 4. Planner input (20 min) — PHP 8.0, 8.6 (polyfill)

- **Starting point**: `PlannerService::plan(array $criteria, string $userId)`
  (`$criteria['radius'] ?? self::DEFAULT_RADIUS_KM`, `$criteria['from'] ?? null`), `PlannerService::planFromForm()`,
  `PlanType` without `data_class`, the time range as two `'H:i'` strings compared as text.
- **Goal**: `PlanRequest` DTO (readonly, constructor promotion);
  `#[MapQueryString]` + Validator in `Api\Controller\PlanController`;
  value objects `FilmCount` (with the polyfill 8.6 `clamp()`) and `TimeRange`.

## 5. Planner output (20 min) — PHP 8.2, 8.5

- **Starting point**: `plan(): array|false` + `'reason'` as a string
  (`'no_programme'`, `'not_enough_programmes'`, `'fewer_films'`, `'unknown_location'`),
  with the number of films kept in a loose `'films'` key.
- **Goal**: `PlanResult` (success with a `ProgrammeList`, failure with a
  `PlanFailure` enum), `#[\NoDiscard]` on the result and on the withers.
- **Deck**: Part 8 (Result, `#[NoDiscard]`).

## 6. Repositories (15 min) — PHP 7.1, 8.0

- **Starting point**: `FilmRepository::findBySlug(string): ?array`,
  the controllers that test `null` (`Web\Controller\FilmController`, `Api\Controller\FilmController`).
- **Goal**: `findFilm(FilmSlug): ?Film` / `getFilm(FilmSlug): Film` pair,
  `?? throw new FilmNotFound(...)`, domain exception translated into a 404 in the controller.
- **Deck**: Part 6 (`find()` vs `get()`).

## 7. Entities (45 min) — PHP 8.4

- **Starting point**: setters and nullables in `Catalog\Entity\*` and
  `Account\Entity\*`; separate `Cinema::$latitude` / `$longitude`;
  `Showtime::$capacity` stored as a string (`"244"`).
- **Goal**: named constructors and invariants (`User::fromProviderIdentity()`),
  asymmetric visibility `public private(set)` instead of trivial getters,
  `set` property hook on the capacity (or `Capacity` value object),
  `Coordinates` Doctrine embeddable, Tell don't ask (`$showtime->isBookableAt($now)`).
- **Deck**: Part 4 (asymmetric visibility), Part 7 (property hooks, Tell don't ask).

## 8. Immutability (30 min) — PHP 8.1 to 8.5

- **Starting point**: programmes and showtimes handled as mutable arrays
  in `ChainBuilder::summarize()` and `PlannerService::format()`.
- **Goal**: `final readonly class Programme`, withers with `clone with`
  (8.5), deep cloning (8.3), `final` property promotion (8.5).
- **Deck**: Part 4.

## 9. OAuth providers (40 min) — PHP 8.3

- **Starting point**: `OAuthUserInfoExtractor::extract()` and its
  `match ($provider) { 'google' => …, 'github' => …, 'local' => … }`.
- **Goal**: `UserInfoProvider` interface + tagged services
  (`#[AutoconfigureTag]`, `#[AutowireIterator]`), `#[\Override]` on the
  implementations, adding LinkedIn through configuration alone + one class.

## 10. Accounts (30 min)

- **Starting point**: `AccountService::loginWithProvider(string $provider, array $userInfo)`
  which reads `$userInfo['emailVerified']`.
- **Goal**: typed `UserInfo`, explicit verified email (`VerifiedEmail` /
  `UnverifiedEmail`), `User` invariants (at least one connection).

## 11. Exceptions (30 min) — PHP 5.5 to 8.3

- **Starting point**: `PatheClient` (generic `\RuntimeException`),
  `SyncCommand` (`catch (\RuntimeException)`), `LocationResolver::fromPosition()`
  (`json_decode()` + result test).
- **Goal**: `PatheApiException` hierarchy (abstract) →
  `BotBlockedException`, `RateLimitedException`, `PatheUnavailableException`;
  targeted `catch` and multi-catch; `@throws` checked by PHPStan;
  `JSON_THROW_ON_ERROR` + `JsonException`; `json_validate()` (8.3); `finally`.
- **Deck**: Part 8.

## 12. Collections (30 min) — PHP 5.6, 8.1, 8.4, 8.5

- **Starting point**: arrays of showtimes in `ChainBuilder`, `ProgrammeSelector`, `PlannerService`.
- **Goal**: `ShowtimeList` (typed variadic constructor, `Countable&IteratorAggregate`),
  `array_find` / `array_any` / `array_all` (8.4), `array_first` / `array_last` (8.5),
  PHPStan generics (`@template`, `list<Showtime>`), first-class callables.

## 13. Type system (30 min) — PHP 7.0 to 8.4

- **Starting point**: no file has `declare(strict_types=1)`; `@param array`
  docblocks without a shape; untyped constants (`ChainBuilder::MARGIN_MINUTES`).
- **Goal**: `strict_types` everywhere (PHP-CS-Fixer rule `declare_strict_types`),
  precise return types, typed constants (8.3), `never`, `#[\Deprecated]`
  (8.4) on methods kept during the transition.

## 14. Hidden inputs (30 min)

- **Starting point**: `new \DateTimeImmutable('now', …)` in `PlannerService::plan()`,
  `CatalogSynchronizer` and `ShiftDatesCommand`, the current instant built in
  `Web\Controller\HomeController` and `Api\Controller\PlanController` for
  `CatalogCalendar::availableDates()`,
  `PATHE_CITIES` split with `explode()`, `CatalogUpdatePublisher` with
  `?HubInterface $hub = null`, `PatheClient` falling back to psr-discovery
  when a collaborator is missing.
- **Goal**: `ClockInterface` (and `MockClock` in the tests, which also makes
  "the form opens on tomorrow after the last showtime" testable at any hour), `%env(csv:PATHE_CITIES)%`, Null Object
  (`NullCatalogPublisher` via `new` in the initializer), `assert()` on the
  planner's internal invariants.

## 15. URLs and transformations (35 min) — PHP 8.5

- **Starting point**:
  - `PatheClient` builds its paths by concatenation
    (`'show/'.rawurlencode($showSlug).'/showtimes/'.rawurlencode($cinemaSlug)`)
    and the base `https://www.pathe.fr/api/` only exists as a string
    (`PatheClient::BASE_URL`, `$baseUrl` constructor argument);
  - `OAuthUserInfoExtractor::fromGithub()` calls a hard-coded URL;
    the local provider URLs are environment strings;
  - `PatheMapper::mapShowtimes()` reads the booking link with `preg_match`;
  - nested arrays in `mapCinema()` / `mapFilm()`.
- **Goal**:
  - a `PatheEndpoints` object built on `Uri\Rfc3986\Uri`: the base is
    parsed once, each call is obtained with `resolve()` and `withQuery()`,
    and the PSR-17 factory receives `$uri->toString()`; an invalid base fails at
    construction, not on the first call;
  - the configuration URLs (local provider, GitHub) become URI objects
    validated as soon as the service is constructed;
  - `Uri\Rfc3986\Uri::parse()` to read the host and path of the booking link;
  - `ext-uri` declared in `composer.json` (always present in PHP 8.5, but the contract becomes explicit);
  - pipe operator `|>` to chain the mapper's transformations.
- **Discussion topic**: RFC 3986 (`Uri\Rfc3986\Uri`) for server-to-server
  calls, WHATWG (`Uri\WhatWg\Url`) for what mimics a browser; what
  `rawurlencode()` used to do by hand.

## 16. API output and templates (40 min) — PHP 8.6 (polyfill)

- **Starting point**: `JsonResponse` built from arrays in
  `Api\Controller\*`; `?order=asc|desc` as a string in
  `Api\Controller\SeenFilmController::list()`; Twig templates fed with arrays;
  flash messages translated by hand where they are added
  (`$this->translator->trans('%provider% account linked.', ['%provider%' => $provider])`
  in `ConnectController`, `OAuthAuthenticator` and `ProfileController`).
- **Goal**: output DTOs + ObjectMapper or Serializer (the OpenAPI doc
  becomes precise by itself); `\SortDirection` enum (polyfill 8.6);
  `TranslatableMessage` (or `t()`) stored in the flash bag and translated by
  the template, so a message keeps its parameters until it is displayed;
  `strict_variables: true` in `config/packages/twig.yaml` and objects passed to templates.

## 17. Tooling (30 min)

- **Starting point**: `make phpstan` (level 5, green) and `make phpstan-max` (baseline).
- **Goal**: make the baseline shrink; install Infection and show that an
  untested safeguard lets a mutant survive.

## 18. Chains and time zones (30 min) — PHP 8.1, 8.4

- **Starting point**: the `app.chains` parameter (`config/packages/chains.yaml`)
  read as a raw array (`$chains['pathe']['timezone']`) in
  `CatalogSynchronizer`, `ShiftDatesCommand` and the controllers; `Cinema::$timezone`
  and `Cinema::$country` as free strings; `CatalogSynchronizer::CHAIN = 'pathe'`.
- **Goal**: a `Chain` value object (identifier, name, `CountryCode`,
  `\DateTimeZone`) built once from the configuration, a `ChainRegistry`
  service, `\DateTimeZone` stored through a Doctrine type instead of a string;
  then sketch how a second SDK (Cineworld, see the spec's future work) would plug in.

## 19. UI component contracts (20 min) — PHP 8.1, 8.4

- **Starting point**: the anonymous components of `templates/components/`
  take free strings (`<twig:Button variant="primray">`, `<twig:Alert type="warning">`):
  `html_cva()` silently ignores an unknown value and renders an unstyled element.
- **Goal**: class components (`#[AsTwigComponent]`) whose props are enums
  (`ButtonVariant`, `AlertType`), `mount()` with typed parameters, and a failure
  in development instead of a silent fallback; the style guide shows every case.
- **Link with the design system**: `docs/design-system.md`.

## Practices already in place (to show, not to fix)

Parameterized queries, CSRF (forms and logout), Twig escaping,
OAuth state + PKCE, account linking on verified email only, random API
tokens stored hashed (`ApiTokenService`), `#[\SensitiveParameter]`,
no user state in services (worker mode), instants stored in UTC and shown
in each cinema's time zone, a third-party SDK that depends on PSR interfaces
only (`src/Sdk/Pathe`), every user-facing string translatable.
