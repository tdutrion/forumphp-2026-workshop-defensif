# Movie Marathon — defensive PHP workshop

A Symfony 8.1 application that plans movie marathons from Pathé showtimes. It
is the starting point of a workshop on defensive programming in PHP: the code
is clean, tested and secure, but its contracts are deliberately implicit
(arrays, scalars, `false|result` returns). The exercises are in
[`docs/exercises.md`](docs/exercises.md).

## Requirements

Docker with Compose v2, `make` and `curl`. Nothing else runs on the host.

## Getting started

```bash
make up        # build and start FrankenPHP, MySQL, the worker and the local OIDC provider
make db-load   # reset the database and import the Pathé catalog (downloaded once from the GitHub release)
```

The ports are published on 127.0.0.1 only: the development secrets are public. Set
`HOST_IP=0.0.0.0` to reach the application from another device.

Open https://localhost (accept the local certificate) and sign in with
**Local provider**: any username works, no Internet connection needed.

The interface is in English, and in French when the browser asks for it. Templates and code
use translation keys (`planner.form.date`, `seen.mark`…); the texts live in
`translations/messages.{en,fr}.yaml` and `translations/validators.{en,fr}.yaml`. After adding
a key, run `make console c="translation:extract en --force --format=yaml --sort=asc --domain=messages"`
to list it in the English catalog, write its text, then translate it in the French one; form labels and
constraint messages are not detected by the extractor and are added by hand. Instants are stored in UTC and shown in the time
zone of each cinema's chain (`config/packages/chains.yaml`). Everything that
talks to Pathé lives in a framework-free SDK, see
[`src/Sdk/Pathe/README.md`](src/Sdk/Pathe/README.md). The interface is built with
Tailwind CSS and a small design system, see [`docs/design-system.md`](docs/design-system.md)
and https://localhost/design-system.

## Optional: real providers

Create OAuth apps and set the credentials in `.env.local`:

| Provider | Callback URL | Variables |
|----------|--------------|-----------|
| Google | `https://localhost/connect/google/check` | `OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET` |
| GitHub | `https://localhost/connect/github/check` | `OAUTH_GITHUB_CLIENT_ID`, `OAUTH_GITHUB_CLIENT_SECRET` |

Then list them in `OAUTH_PROVIDERS` (e.g. `local,github,google`).

## Everyday commands

| Command | Purpose |
|---------|---------|
| `make test` | PHPUnit (creates the test database if needed) |
| `make phpstan` | PHPStan, level 5 (must stay green) |
| `make phpstan-max` | PHPStan, max level against the baseline (workshop progress) |
| `make cs` | PHP-CS-Fixer (`@Symfony`) |
| `make css` / `make css-watch` | Build the Tailwind CSS once / on every change |
| `make sync` | Sync the catalog from pathe.fr (network; `c="--city=dijon"`) |
| `make db-dump` | Write `data/catalog-<today>.sql.gz` from the current catalog and pin it in the Makefile |
| `make db-download` | Download the pinned dump again from the `catalog` GitHub release |
| `make db-upload` | Publish the pinned dump on the `catalog` GitHub release (`gh` CLI, signed in) |
| `make sh` / `make console c="…"` | Shell / Symfony console in the PHP container |

The catalog is never synchronized on its own: set `CATALOG_SCHEDULE_ENABLED=1` in
`.env.local` to let the `worker` service sync it every 6 hours. A manual and a scheduled
sync never run together (lock in MySQL).

The API is documented at https://localhost/api/doc; create a personal token
from **My profile › My settings** (https://localhost/settings).

## Production and self-hosting

`compose.prod.yaml` runs the production image (`frankenphp_prod` target: no
development tools, non-root user) with every secret required. The step-by-step
guide is [`docs/self-hosting.md`](docs/self-hosting.md).

## License

[GNU AGPL-3.0](LICENSE) © TDUTRION SOLUTIONS. Every page of an instance links
to its source code (`SOURCE_CODE_URL`), as the license requires.
