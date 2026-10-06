# AGENTS.md

Instructions for coding agents (Claude Code reads them through `CLAUDE.md`).

This is ScreenRoute, a Symfony application (check `composer.json` for the exact
Symfony/PHP versions, and `symfony.lock` for the recipes that ran). It already
uses Doctrine ORM (MySQL), Twig with Twig components and Tailwind, an API
(Serializer, Nelmio API doc), SecurityBundle with OAuth/OIDC sign-in
(KnpU OAuth2 client), Messenger, Scheduler, Lock and Mercure. Build on them
rather than adding parallel stacks.

## Ask before generating

If a new feature leaves these open, ask rather than guess:

- Persistence: a new entity (Doctrine ORM, with a migration), or none?
- Interface: server-rendered (Twig), API, or both?
- Access: public, signed-in users, or API tokens?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option rather than scaffolding a full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `make composer c="require <package>"` and let the
Flex recipe register the bundle and generate its config. Don't hand-edit
`config/bundles.php` or hand-write a bundle's base config; that's what the recipe
is for. Don't skip a good-fit component just because it isn't installed yet;
installing it is one command.

One exception: the Flex contrib recipes are disabled in this project, so the
bundles they would bring (dama, KnpU, Nelmio) are registered by hand in
`config/bundles.php`.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

And the conventions of this project:

- Code, comments, docs and commit messages are in English. Every user-facing
  string goes through the Translator (`translations/messages.{en,fr}.yaml`,
  `validators.{en,fr}.yaml`).
- A button that changes something posts a form and redirects
  (Post/Redirect/Get, 303), back to the page it came from. No AJAX and no Turbo
  Drive or Turbo Streams for these actions.
- The interface uses the design system only: its tokens and Twig components, see
  `docs/design-system.md` and https://localhost/design-system.

## Everyday workflow

- Everything runs in Docker through the Makefile, never `symfony serve` nor the
  PHP of the host: `make up`, `make test`, `make phpstan`, `make cs`, `make css`,
  `make console c="…"` (`make help` lists them all).
- When something fails, read `var/log/dev.log` (inside the container: `make sh`)
  and the web profiler (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `make console c="make:…"` with every
  argument passed up front and `--no-interaction` where supported: makers prompt
  on a terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- Schema changes go through migrations (`make console c="make:migration"`, then
  `make console c="doctrine:migrations:migrate"`), never
  `doctrine:schema:update` or hand-written SQL.

## Configuration

There is no `symfony/dotenv`: the application reads the real environment only,
through `%env(...)%`.

- Development: `compose.override.yaml` hands `.env` (committed, defaults only) and
  `.env.local` (optional, git-ignored, real secrets and personal overrides) to the
  `php` and `worker` containers as `env_file`. Run `make up` after editing either.
- Tests: their values are forced in `phpunit.dist.xml`.
- Production: the environment of the system only, never a `.env` file
  (`compose.prod.yaml`, run with `--env-file /dev/null`; see
  `docs/self-hosting.md`).

## Testing

Functional/HTTP tests extend `WebTestCase`; service-level tests extend
`KernelTestCase`. Arrange the data with the builders of `tests/Builder/`, and
keep each test in arrange-act-assert order. Run `make test`
(`make test c="--filter X"` for a subset). A feature isn't done until it has a
test that exercises it the way a caller would, an HTTP request for a controller
or a service call for a service, not just "it didn't throw."

The CI (`.github/workflows/ci.yml`) runs `make lint`, `make cs-check`,
`make phpstan` and `make test` on every pull request: run them before pushing.

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset): `make cs` fixes, `make cs-check` only checks. PHPStan (`make phpstan`)
must stay green.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `make console c="about"`: versions, environment, paths.
- `make console c="debug:router"`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `make lint` (container, Twig, YAML, `composer validate` and `audit`): validate
  before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
