# Self-hosting Movie Marathon

Movie Marathon is free software, published under the
[GNU AGPL-3.0](../LICENSE): you can run your own instance, for yourself, your
friends or your company. This guide takes a server with Docker to a working
instance with HTTPS, sign-in and an up-to-date catalog.

The production setup is `compose.yaml` plus `compose.prod.yaml`: FrankenPHP
(web server, PHP and the Mercure hub in one container), a worker that keeps the
catalog up to date, and MySQL.

## Requirements

- A Linux server with Docker Engine and Docker Compose v2.
- A domain name whose DNS records point to the server, and the ports 80/TCP,
  443/TCP and 443/UDP (HTTP/3) open: FrankenPHP gets and renews a Let's Encrypt
  certificate by itself.
- `git` and `curl` on the server.
- An account with at least one sign-in provider (GitHub or Google), to create an
  OAuth app.

## 1. Get the code

```bash
git clone https://github.com/tdutrion/forumphp-2026-workshop-defensif.git movie-marathon
cd movie-marathon
```

## 2. Create the sign-in apps

Movie Marathon never stores passwords: people sign in with an existing account.
Create one OAuth app per provider you want to offer, with this callback URL
(replace the domain):

| Provider | Where | Callback URL |
|----------|-------|--------------|
| GitHub | Settings › Developer settings › OAuth Apps › New OAuth App | `https://movies.example.org/connect/github/check` |
| Google | Google Cloud console › APIs & Services › Credentials › Create OAuth client ID (Web application) | `https://movies.example.org/connect/google/check` |

Keep the client ID and the client secret of each app for the next step. Another
OpenID Connect provider (Keycloak, LinkedIn...) can be added by configuration
alone: see the comment in `config/packages/knpu_oauth2_client.yaml`.

> **Never enable the `local` provider in production.** It is the offline
> provider of the development environment and accepts any username.

## 3. Configure the instance

Create `.env.prod.local` at the root of the project. Git ignores it, and it is
never copied into the Docker image:

```bash
cat > .env.prod.local <<EOF
SERVER_NAME=movies.example.org
HOST_IP=0.0.0.0
APP_SECRET=$(openssl rand -hex 32)
CADDY_MERCURE_JWT_SECRET=$(openssl rand -hex 32)
MYSQL_PASSWORD=$(openssl rand -hex 16)
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)
OAUTH_PROVIDERS=github
OAUTH_GITHUB_CLIENT_ID=your-client-id
OAUTH_GITHUB_CLIENT_SECRET=your-client-secret
PATHE_CITIES=paris,lyon,dijon
EOF
chmod 600 .env.prod.local
```

| Variable | Required | Meaning |
|----------|----------|---------|
| `SERVER_NAME` | yes | Domain of the instance, used for HTTPS. |
| `HOST_IP` | yes | `0.0.0.0` to accept connections from the Internet (the default, `127.0.0.1`, only serves the server itself). |
| `APP_SECRET` | yes | Long random string (Symfony secret). |
| `CADDY_MERCURE_JWT_SECRET` | yes | Random string of 32 characters or more, signs the live catalog updates. |
| `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | yes | Database passwords. |
| `OAUTH_PROVIDERS` | yes | Comma-separated providers offered on the sign-in page: `github`, `google`, or both. |
| `OAUTH_GITHUB_CLIENT_ID`, `OAUTH_GITHUB_CLIENT_SECRET` | with `github` | Credentials of the GitHub OAuth app. |
| `OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET` | with `google` | Credentials of the Google OAuth client. |
| `PATHE_CITIES` | no | Pathé cities of the catalog, as slugs (default `paris,lyon,dijon`). |
| `CATALOG_SCHEDULE_ENABLED` | no | `1` (default) synchronizes the catalog every 6 hours, `0` never. |
| `SOURCE_CODE_URL` | no | Repository offered to your users in the footer (see [your obligations](#your-obligations)). |

Compose stops with `required variable ... is missing a value` as long as a
required variable is not set: the development defaults are public and must
never reach production.

## 4. Start the instance

Every command of this guide goes through the same Compose files and settings.
An alias saves typing them:

```bash
alias mm='docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod.local'
mm up --build --wait
```

This builds the production image (no development tools, running as a non-root
user), creates the database schema (migrations run at every start), and starts
the web server and the worker. Open `https://movies.example.org`: the landing
page shows up. The catalog is still empty.

## 5. Load the catalog

Either way works; the worker then keeps the catalog up to date by itself.

**From the published dump** (a few seconds, the cities Paris, Lyon and Dijon).
Pick the latest `catalog-YYYY-MM-DD.sql.gz` on the
[catalog release](https://github.com/tdutrion/forumphp-2026-workshop-defensif/releases/tag/catalog),
on an empty catalog:

```bash
curl -fSL https://github.com/tdutrion/forumphp-2026-workshop-defensif/releases/download/catalog/catalog-2026-10-05.sql.gz \
  | gunzip -c \
  | mm exec -T database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
mm exec php bin/console cache:pool:clear cache.catalog
```

**From pathe.fr** (several minutes per city: one request per second, to be
gentle with Pathé):

```bash
mm exec php bin/console catalog:sync
```

Reload the landing page: it lists the cities of the catalog. Sign in, and plan
a first marathon.

## Updating

```bash
git pull
mm up --build --wait
```

Database migrations run by themselves when the containers start.

## Backups

The data lives in two Docker volumes: `database_data` (accounts and catalog)
and `caddy_data` (HTTPS certificates). A database backup:

```bash
mm exec -T database sh -c 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' | gzip > "backup-$(date +%F).sql.gz"
```

And its restoration, into the instance:

```bash
gunzip -c backup-2026-10-05.sql.gz | mm exec -T database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

## Your obligations

- **AGPL-3.0.** Your users must be offered the source code of the version you
  run. Without changes, the default `SOURCE_CODE_URL` does it. If you change the
  code, publish your version under the AGPL-3.0 and set `SOURCE_CODE_URL` to it.
- **Legal pages.** The pages of `templates/legal/` describe the official
  instance: its publisher, its host and its privacy policy. Rewrite them for
  your instance (in France, the legal notice and the privacy policy are
  mandatory as soon as the instance is public).
- **Name.** Your instance must not present itself as the official Movie
  Marathon service.
- **Pathé.** The catalog comes from the public website of Pathé. Keep the
  synchronization gentle (`PATHE_DELAY_MS`, one second between requests by
  default) and the booking links pointing to Pathé.

## Troubleshooting

| Symptom | Check |
|---------|-------|
| `required variable ... is missing a value` | The variable in `.env.prod.local`, and the `--env-file` option (the alias). |
| Certificate error, or the site does not answer | The DNS records of the domain, ports 80 and 443 open, `HOST_IP=0.0.0.0`; then `mm logs php`. |
| Sign-in fails after the provider page | The callback URL of the OAuth app must be exactly `https://<domain>/connect/<provider>/check`. |
| No date to choose, empty programmes | The catalog is empty or outdated: step 5, then `mm logs worker`. |

This setup expects FrankenPHP to face the Internet directly. Running it behind
another reverse proxy (TLS terminated elsewhere) is not covered by this guide.
