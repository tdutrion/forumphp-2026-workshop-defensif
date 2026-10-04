# Movie Marathon — defensive PHP workshop

A Symfony 8.1 application that plans movie marathons from Pathé showtimes. It
is the starting point of a workshop on defensive programming in PHP: the code
is clean, tested and secure, but its contracts are deliberately implicit
(arrays, scalars, `false|result` returns). The exercises are in
[`docs/exercises.md`](docs/exercises.md).

## Requirements

Docker with Compose v2 and `make`. Nothing else runs on the host.

## Getting started

```bash
make up        # build and start FrankenPHP, MySQL, the worker and the local OIDC provider
make db-load   # reset the database and import the Pathé catalog (data/catalog.sql.gz)
```

Open https://localhost (accept the local certificate) and sign in with
**Local provider**: any username works, no Internet connection needed.

The interface is in English, and in French when the browser asks for it
(`translations/*.fr.yaml`). Instants are stored in UTC and shown in the time
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
| `make db-dump` | Write `data/catalog.sql.gz` from the current catalog |
| `make sh` / `make console c="…"` | Shell / Symfony console in the PHP container |

The API is documented at https://localhost/api/doc; create a personal token
from your profile page.

## Production image

`docker build --target prod .` builds a production image without development
tools, running as a non-root user.
