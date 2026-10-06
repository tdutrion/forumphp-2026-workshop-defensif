# Self-hosting ScreenRoute

ScreenRoute is free software, published under the
[GNU AGPL-3.0](../LICENSE): you can run your own instance, for yourself, your
friends or your company. This guide takes you to a working instance with
HTTPS, sign-in and an up-to-date catalog, in one of three ways.

## Choose how to host it

| | [A. Docker](#a-docker-recommended) | [B. A VM without Docker](#b-a-virtual-machine-without-docker) | [C. Shared hosting](#c-shared-hosting-infomaniak-ovhcloud) |
|---|---|---|---|
| For | a server of yours (VPS, dedicated, home server) | a server where Docker is not wanted | a shared web hosting plan |
| Web server | FrankenPHP, in a container | FrankenPHP, a single binary | Apache and PHP-FPM of the host |
| HTTPS | automatic (Let's Encrypt) | automatic (Let's Encrypt) | the host's certificate |
| Catalog kept up to date | every 6 hours, by the worker | every 6 hours, by the worker | by a scheduled task, where the plan allows it |
| "Programme updated" live notice (Mercure) | yes | yes | no (the pages work without it) |
| Effort | lowest | medium | medium, and depends on the plan |

Whatever the option, production uses **no `.env` file**: the application reads
the environment only. The `.env` file of the repository holds the public
development defaults and must never reach a server.

## What every option needs

- **PHP 8.5** with the `intl`, `pdo_mysql`, `ctype`, `iconv` and `opcache`
  extensions (the Docker image brings them; check the others with `php -m`).
- **MySQL 8.4** (MySQL 8.0 or a recent MariaDB may work, but only 8.4 is tested).
- A **domain name** pointing to the server or the hosting plan.
- An account with at least one **sign-in provider** (GitHub or Google).

### Create the sign-in apps

ScreenRoute never stores passwords: people sign in with an existing account.
Create one OAuth app per provider you want to offer, with this callback URL
(replace the domain):

| Provider | Where | Callback URL |
|----------|-------|--------------|
| GitHub | Settings › Developer settings › OAuth Apps › New OAuth App | `https://movies.example.org/connect/github/check` |
| Google | Google Cloud console › APIs & Services › Credentials › Create OAuth client ID (Web application) | `https://movies.example.org/connect/google/check` |

Keep the client ID and the client secret of each app. Another OpenID Connect
provider (Keycloak, LinkedIn...) can be added by configuration alone: see the
comment in `config/packages/knpu_oauth2_client.yaml`.

> **Never enable the `local` provider in production.** It is the offline
> provider of the development environment and accepts any username.

### The configuration

The variables a production instance reads. With Docker (option A), Compose
builds most of them from a few settings: see that section.

| Variable | Value |
|----------|-------|
| `APP_ENV`, `APP_DEBUG` | `prod` and `0`. |
| `APP_SECRET` | Long random string (`openssl rand -hex 32`). |
| `DATABASE_URL` | `mysql://user:password@host:3306/database?serverVersion=8.4.0&charset=utf8mb4` |
| `LOCK_DSN` | The same value as `DATABASE_URL` (the sync lock lives in MySQL). |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` |
| `MAILER_DSN` | `null://null` (the application sends no e-mail). |
| `DEFAULT_URI` | `https://movies.example.org` (links built outside a web request). |
| `MERCURE_URL`, `MERCURE_PUBLIC_URL`, `MERCURE_RESOURCE_IDENTIFIER` | `https://movies.example.org/.well-known/mercure` (shared hosting: see option C). |
| `MERCURE_JWT_SECRET` | Random string of 32 characters or more. |
| `OAUTH_PROVIDERS` | Comma-separated providers offered on the sign-in page: `github`, `google`, or both. |
| `OAUTH_GITHUB_CLIENT_ID`, `OAUTH_GITHUB_CLIENT_SECRET` | Credentials of the GitHub OAuth app (empty without `github`). |
| `OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET` | Credentials of the Google OAuth client (empty without `google`). |
| `OAUTH_LOCAL_CLIENT_ID`, `OAUTH_LOCAL_CLIENT_SECRET`, `OAUTH_LOCAL_URL_AUTHORIZE`, `OAUTH_LOCAL_URL_TOKEN`, `OAUTH_LOCAL_URL_USERINFO` | Empty (development provider). |
| `PATHE_CITIES` | Pathé cities of the catalog, as comma-separated slugs, e.g. `paris,lyon,dijon`. Empty: every Pathé city. |
| `PATHE_DELAY_MS`, `PATHE_CACHE_TTL` | `1000` and `3600`: one request per second to Pathé, answers kept an hour. |
| `CATALOG_SCHEDULE_ENABLED` | `1` to let the worker synchronize every 6 hours, `0` otherwise. |
| `SOURCE_CODE_URL` | Repository offered to your users in the footer (see [your obligations](#your-obligations)). |

### The release archive (options B and C)

Without Docker on the server, deploy a ready-made copy of the application:
dependencies installed, CSS and JavaScript built. Make it on your own machine
with Docker, from the production image:

```bash
git clone https://github.com/tdutrion/forumphp-2026-workshop-defensif.git screenroute
cd screenroute
docker build --target frankenphp_prod -t screenroute:release .
id=$(docker create screenroute:release)
docker cp "$id":/app screenroute-release && docker rm "$id"
rm -rf screenroute-release/var/cache/*
tar czf screenroute-release.tar.gz -C screenroute-release .
```

The archive (about 110 MB) holds no configuration and no `.env` file. On the
server, after each upload, warm the cache up once the variables are set:
`php bin/console cache:warmup`.

## A. Docker (recommended)

The production setup is `compose.yaml` plus `compose.prod.yaml`: FrankenPHP
(web server, PHP and the Mercure hub in one container), a worker that keeps the
catalog up to date, and MySQL.

**Requirements:** a Linux server with Docker Engine and Docker Compose v2,
`git` and `curl`, the domain's DNS records pointing to it, and the ports
80/TCP, 443/TCP and 443/UDP (HTTP/3) open: FrankenPHP gets and renews a Let's
Encrypt certificate by itself.

### 1. Get the code

```bash
git clone https://github.com/tdutrion/forumphp-2026-workshop-defensif.git screenroute
cd screenroute
```

### 2. Configure the instance

Set the settings in the environment of the account that runs Docker Compose,
for instance in its `~/.profile` (readable by that account only). Compose
builds the variables of [the configuration](#the-configuration) from them:

```bash
cat >> ~/.profile <<EOF
export SERVER_NAME=movies.example.org
export HOST_IP=0.0.0.0
export APP_SECRET=$(openssl rand -hex 32)
export CADDY_MERCURE_JWT_SECRET=$(openssl rand -hex 32)
export MYSQL_PASSWORD=$(openssl rand -hex 16)
export MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)
export OAUTH_PROVIDERS=github
export OAUTH_GITHUB_CLIENT_ID=your-client-id
export OAUTH_GITHUB_CLIENT_SECRET=your-client-secret
export PATHE_CITIES=paris,lyon,dijon
EOF
chmod 600 ~/.profile
. ~/.profile
```

| Setting | Required | Meaning |
|---------|----------|---------|
| `SERVER_NAME` | yes | Domain of the instance, used for HTTPS. |
| `HOST_IP` | yes | `0.0.0.0` to accept connections from the Internet (the default, `127.0.0.1`, only serves the server itself). |
| `APP_SECRET` | yes | Long random string (Symfony secret). |
| `CADDY_MERCURE_JWT_SECRET` | yes | Random string of 32 characters or more, signs the live catalog updates. |
| `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | yes | Database passwords. |
| `OAUTH_PROVIDERS` and the credentials | yes | See [the configuration](#the-configuration). |
| `PATHE_CITIES` | no | Unset: `paris,lyon,dijon`; set but empty: every Pathé city. |
| `CATALOG_SCHEDULE_ENABLED` | no | `1` (default) synchronizes the catalog every 6 hours, `0` never. |
| `SOURCE_CODE_URL` | no | Repository offered to your users in the footer. |

Compose stops with `required variable ... is missing a value` as long as a
required setting is missing. The commands below pass `--env-file /dev/null` so
that Compose never reads the development `.env` file of the repository.

### 3. Start the instance

An alias saves typing the Compose files and options:

```bash
alias mm='docker compose --env-file /dev/null -f compose.yaml -f compose.prod.yaml'
mm up --build --wait
```

This builds the production image (no development tools, running as a non-root
user), creates the database schema (migrations run at every start), and starts
the web server and the worker. Open `https://movies.example.org`: the landing
page shows up. The catalog is still empty: [load it](#load-the-catalog), with
`mm exec php bin/console …` for the commands and
`mm exec -T database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'`
as the MySQL client.

### Updating

```bash
git pull
mm up --build --wait
```

Database migrations run by themselves when the containers start.

### Backups

The data lives in two Docker volumes: `database_data` (accounts and catalog)
and `caddy_data` (HTTPS certificates). A database backup, and its restoration:

```bash
mm exec -T database sh -c 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' | gzip > "backup-$(date +%F).sql.gz"
gunzip -c backup-2026-10-05.sql.gz | mm exec -T database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

## B. A virtual machine without Docker

The same stack as option A, run by systemd: the
[FrankenPHP](https://frankenphp.dev) binary (web server, PHP 8.5 and the
Mercure hub in one file), the worker, and MySQL from the distribution. The
example is a Debian or Ubuntu VM; the domain points to it and the ports 80 and
443 are open.

### 1. Install FrankenPHP and MySQL

```bash
curl https://frankenphp.dev/install.sh | sh
sudo mv frankenphp /usr/local/bin/
frankenphp php-cli -v                                   # PHP 8.5
frankenphp php-cli -m | grep -E 'intl|pdo_mysql'        # both listed
sudo apt install mysql-server                           # or MySQL 8.4 from dev.mysql.com
```

Create a database and its user:

```bash
sudo mysql -e "CREATE DATABASE screenroute CHARACTER SET utf8mb4;
  CREATE USER 'screenroute'@'localhost' IDENTIFIED BY 'a-long-random-password';
  GRANT ALL ON screenroute.* TO 'screenroute'@'localhost';"
```

### 2. Deploy the application

Copy [the release archive](#the-release-archive-options-b-and-c) to the VM,
and unpack it into `/app` (the path the web server configuration expects),
owned by a dedicated account:

```bash
sudo useradd --system --home /app screenroute
sudo mkdir -p /app && sudo tar xzf screenroute-release.tar.gz -C /app
sudo chown -R screenroute: /app
```

### 3. Configure the instance

The variables of [the configuration](#the-configuration) go into one file read
by systemd, readable by root only, plus the settings of the web server:

```bash
sudo install -m 600 /dev/null /etc/screenroute.env
sudo editor /etc/screenroute.env
```

```ini
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=...
DATABASE_URL=mysql://screenroute:a-long-random-password@127.0.0.1:3306/screenroute?serverVersion=8.4.0&charset=utf8mb4
LOCK_DSN=mysql://screenroute:a-long-random-password@127.0.0.1:3306/screenroute?serverVersion=8.4.0&charset=utf8mb4
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null
DEFAULT_URI=https://movies.example.org
MERCURE_URL=https://movies.example.org/.well-known/mercure
MERCURE_PUBLIC_URL=https://movies.example.org/.well-known/mercure
MERCURE_RESOURCE_IDENTIFIER=https://movies.example.org/.well-known/mercure
MERCURE_JWT_SECRET=...
OAUTH_PROVIDERS=github
OAUTH_GITHUB_CLIENT_ID=...
OAUTH_GITHUB_CLIENT_SECRET=...
OAUTH_GOOGLE_CLIENT_ID=
OAUTH_GOOGLE_CLIENT_SECRET=
OAUTH_LOCAL_CLIENT_ID=
OAUTH_LOCAL_CLIENT_SECRET=
OAUTH_LOCAL_URL_AUTHORIZE=
OAUTH_LOCAL_URL_TOKEN=
OAUTH_LOCAL_URL_USERINFO=
PATHE_CITIES=paris,lyon,dijon
PATHE_DELAY_MS=1000
PATHE_CACHE_TTL=3600
CATALOG_SCHEDULE_ENABLED=1
SOURCE_CODE_URL=https://github.com/tdutrion/forumphp-2026-workshop-defensif
# Web server (Caddyfile of the repository)
SERVER_NAME=movies.example.org
MERCURE_PUBLISHER_JWT_KEY=<the MERCURE_JWT_SECRET value>
MERCURE_SUBSCRIBER_JWT_KEY=<the MERCURE_JWT_SECRET value>
```

The web server uses the Caddyfile of the repository,
`docker/frankenphp/Caddyfile` (not in the archive): copy it to
`/etc/screenroute.Caddyfile`.

### 4. Create the services

`/etc/systemd/system/screenroute-web.service`:

```ini
[Unit]
Description=ScreenRoute web server (FrankenPHP)
After=network-online.target mysql.service

[Service]
User=screenroute
WorkingDirectory=/app
EnvironmentFile=/etc/screenroute.env
ExecStartPre=/usr/local/bin/frankenphp php-cli bin/console cache:warmup
ExecStartPre=/usr/local/bin/frankenphp php-cli bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
ExecStart=/usr/local/bin/frankenphp run --config /etc/screenroute.Caddyfile
AmbientCapabilities=CAP_NET_BIND_SERVICE
Restart=always

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/screenroute-worker.service`:

```ini
[Unit]
Description=ScreenRoute worker (scheduled catalog sync)
After=screenroute-web.service

[Service]
User=screenroute
WorkingDirectory=/app
EnvironmentFile=/etc/screenroute.env
ExecStart=/usr/local/bin/frankenphp php-cli bin/console messenger:consume scheduler_catalog --time-limit=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now screenroute-web screenroute-worker
```

Open `https://movies.example.org`, then [load the catalog](#load-the-catalog).
Commands run as the service would, with its variables:

```bash
sudo -u screenroute sh -c 'set -a; . /etc/screenroute.env; cd /app && frankenphp php-cli bin/console catalog:sync'
```

**Updating:** build a new archive, unpack it over `/app` (keep `var/` if you
like), then `sudo systemctl restart screenroute-web screenroute-worker`
(migrations and cache warm-up run at start). **Backups:** `mysqldump`, as in
option A, without `mm exec`.

## C. Shared hosting (Infomaniak, OVHcloud)

A shared plan runs PHP behind the host's Apache, with no Docker, no root
access and no process that runs on its own. ScreenRoute works there with three
limits:

- **No Mercure hub:** the "programme updated" notice does not show up live.
  The pages work without it (the application logs the failed notice and goes
  on). A managed Mercure hub can fill the gap: set the `MERCURE_*` variables to it.
- **No worker:** set `CATALOG_SCHEDULE_ENABLED=0`, and synchronize the catalog
  with a scheduled task where the plan allows it (see each host below), or by
  hand over SSH, or by loading the published dumps.
- **SSH is needed** for the database migrations and the commands. Choose a plan
  that offers it.

### 1. Upload the application

Upload [the release archive](#the-release-archive-options-b-and-c), unpacked,
next to the web root (for instance in `~/screenroute`), over SFTP or rsync.
Then point the domain (or subdomain) **to its `public/` folder**: everything
else must stay out of reach of the web.

### 2. Configure the instance

On shared hosting, the variables of [the configuration](#the-configuration)
are given by Apache. Create `public/.htaccess` with them and the routing of
every request to Symfony:

```apache
DirectoryIndex index.php

# Configuration (no .env file): https://github.com/tdutrion/forumphp-2026-workshop-defensif/blob/main/docs/self-hosting.md
SetEnv APP_ENV prod
SetEnv APP_DEBUG 0
SetEnv APP_SECRET "..."
SetEnv DATABASE_URL "mysql://user:password@host:3306/database?serverVersion=8.4.0&charset=utf8mb4"
SetEnv LOCK_DSN "mysql://user:password@host:3306/database?serverVersion=8.4.0&charset=utf8mb4"
SetEnv MESSENGER_TRANSPORT_DSN "doctrine://default?auto_setup=0"
SetEnv MAILER_DSN null://null
SetEnv DEFAULT_URI https://movies.example.org
SetEnv MERCURE_URL https://movies.example.org/.well-known/mercure
SetEnv MERCURE_PUBLIC_URL https://movies.example.org/.well-known/mercure
SetEnv MERCURE_RESOURCE_IDENTIFIER https://movies.example.org/.well-known/mercure
SetEnv MERCURE_JWT_SECRET "..."
SetEnv OAUTH_PROVIDERS github
SetEnv OAUTH_GITHUB_CLIENT_ID "..."
SetEnv OAUTH_GITHUB_CLIENT_SECRET "..."
SetEnv OAUTH_GOOGLE_CLIENT_ID ""
SetEnv OAUTH_GOOGLE_CLIENT_SECRET ""
SetEnv OAUTH_LOCAL_CLIENT_ID ""
SetEnv OAUTH_LOCAL_CLIENT_SECRET ""
SetEnv OAUTH_LOCAL_URL_AUTHORIZE ""
SetEnv OAUTH_LOCAL_URL_TOKEN ""
SetEnv OAUTH_LOCAL_URL_USERINFO ""
SetEnv PATHE_CITIES paris,lyon,dijon
SetEnv PATHE_DELAY_MS 1000
SetEnv PATHE_CACHE_TTL 3600
SetEnv CATALOG_SCHEDULE_ENABLED 0
SetEnv SOURCE_CODE_URL https://github.com/tdutrion/forumphp-2026-workshop-defensif

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

Apache never serves a `.htaccess` file. These variables reach the web pages
only: in an SSH session, export them before running a command. A small helper
does it from the same file:

```bash
cd ~/screenroute
load() { eval "$(sed -nE 's/^SetEnv ([A-Z_]+) "?([^"]*)"?$/export \1="\2"/p' public/.htaccess)"; }
load && php bin/console cache:warmup && php bin/console doctrine:migrations:migrate --no-interaction
```

Then [load the catalog](#load-the-catalog) with `load && php bin/console …`,
or import a dump with the MySQL client or the phpMyAdmin of the host.

### Infomaniak

- **Plan:** [Web hosting](https://www.infomaniak.com/en/hosting/prices-and-characteristics)
  offers PHP 8.5, SSH and MySQL. Create the database in the Manager
  (Hosting › Databases); its host name is shown there.
- **Web root:** Manager › your site › *Manage advanced settings*: set the
  directory of the site to `screenroute/public` and the PHP version to 8.5.
- **Variables:** either `SetEnv` in `public/.htaccess` as above, or Manager ›
  your site › *Manage advanced settings* › **PHP / Apache** › *Environment
  variables* ([documentation](https://www.infomaniak.com/en/support/faq/2087/using-php-environment-variables)).
  Keep `public/.htaccess` either way, for the routing; with the Manager, the
  SSH helper above needs the variables copied somewhere it can read them.
- **Scheduled sync:** the task scheduler of web hosting plans calls a URL,
  every 15 minutes at most often
  ([documentation](https://www.infomaniak.com/en/support/faq/2161/task-scheduler-cronjob));
  ScreenRoute has no URL that runs a sync. Synchronize by hand over SSH, load
  the published dumps, or move to a Cloud Server, whose crontab runs commands
  ([documentation](https://www.infomaniak.com/en/support/faq/350/crontab-for-cloud-server)):
  `0 */6 * * * php ~/screenroute/bin/cron-catalog-sync.php` (the script reads
  the variables of `public/.htaccess`, see OVHcloud below).

### OVHcloud

- **Plan:** [Web Hosting](https://docs.ovhcloud.com/en/guides/web-cloud/web-hosting/web-hosting-main-info)
  Pro or above, for SSH ([documentation](https://docs.ovhcloud.com/en/guides/web-cloud/web-hosting/ssh-on-webhosting));
  PHP 8.5 runs in the **stable64** environment. Create the MySQL database in
  the Control Panel (Web Cloud › Hosting plans › Databases).
- **PHP version:** in `.ovhconfig` at the root of the FTP space
  ([documentation](https://docs.ovhcloud.com/en/guides/web-cloud/web-hosting/configure-your-web-hosting)):

  ```ini
  app.engine=php
  app.engine.version=8.5
  http.firewall=none
  environment=production
  container.image=stable64
  ```

- **Web root:** Control Panel › Multisite: set the root folder of the domain
  to `screenroute/public`.
- **Variables:** `SetEnv` in `public/.htaccess`, as above.
- **Scheduled sync:** the scheduled tasks run a PHP file, once an hour at most,
  without arguments, for 60 minutes at most
  ([documentation](https://docs.ovhcloud.com/en/guides/web-cloud/web-hosting/cron-tasks)).
  Create a task on `screenroute/bin/cron-catalog-sync.php`, PHP 8.5, every 6
  hours: the script reads the variables of `public/.htaccess` and runs
  `catalog:sync`. Keep `PATHE_CITIES` short enough for the sync to end within
  the hour (one city takes a few minutes).

## Load the catalog

Either way works. The worker (options A and B) or the scheduled task (option C)
then keeps the catalog up to date.

**From the published dump** (a few seconds, the cities Paris, Lyon and Dijon).
Pick the latest `catalog-YYYY-MM-DD.sql.gz` on the
[catalog release](https://github.com/tdutrion/forumphp-2026-workshop-defensif/releases/tag/catalog)
and import it into an empty catalog with the MySQL client of your option, then
clear the catalog cache. With Docker:

```bash
curl -fSL https://github.com/tdutrion/forumphp-2026-workshop-defensif/releases/download/catalog/catalog-2026-10-05.sql.gz \
  | gunzip -c \
  | mm exec -T database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
mm exec php bin/console cache:pool:clear cache.catalog
```

**From pathe.fr** (several minutes per city: one request per second, to be
gentle with Pathé): `php bin/console catalog:sync` (with Docker:
`mm exec php bin/console catalog:sync`).

Reload the landing page: it lists the cities of the catalog. Sign in, and plan
a first marathon.

## Your obligations

- **AGPL-3.0.** Your users must be offered the source code of the version you
  run. Without changes, the default `SOURCE_CODE_URL` does it. If you change the
  code, publish your version under the AGPL-3.0 and set `SOURCE_CODE_URL` to it.
- **Legal pages.** The pages of `templates/legal/` describe the official
  instance: its publisher, its host and its privacy policy. Rewrite them for
  your instance (in France, the legal notice and the privacy policy are
  mandatory as soon as the instance is public).
- **Name.** Your instance must not present itself as the official ScreenRoute
  service.
- **Wikidata.** Works are linked to Wikidata (CC0) during the synchronization; the requests
  name your instance by its `SOURCE_CODE_URL`, as Wikidata asks. A film can be linked by hand:
  `php bin/console work:link <film> <Q-id>`.
- **Pathé.** The catalog comes from the public website of Pathé. Keep the
  synchronization gentle (`PATHE_DELAY_MS`, one second between requests by
  default) and the booking links pointing to Pathé.

## Troubleshooting

| Symptom | Check |
|---------|-------|
| `required variable ... is missing a value` (Docker) | The setting in the environment of the shell (`echo $APP_SECRET`), then the alias. |
| `Environment variable not found: "..."` | A variable of [the configuration](#the-configuration) is missing: in the environment (A), `/etc/screenroute.env` (B) or `public/.htaccess` (C); then warm the cache up again. |
| Certificate error, or the site does not answer (A, B) | The DNS records of the domain, ports 80 and 443 open (and `HOST_IP=0.0.0.0` with Docker); then `mm logs php` or `journalctl -u screenroute-web`. |
| Every page but the home page answers 404 (C) | `public/.htaccess` and its rewrite rules; the web root must be `public/`. |
| Sign-in fails after the provider page | The callback URL of the OAuth app must be exactly `https://<domain>/connect/<provider>/check`. |
| No date to choose, empty programmes | The catalog is empty or outdated: [load it](#load-the-catalog); then the logs of the worker or of the scheduled task. |

Options A and B expect FrankenPHP to face the Internet directly. Running it
behind another reverse proxy (TLS terminated elsewhere) is not covered by this
guide.
