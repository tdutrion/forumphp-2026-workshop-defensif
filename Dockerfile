#syntax=docker/dockerfile:1

# Versions: the FrankenPHP image is pinned by digest (refresh it with `docker buildx imagetools inspect`)
FROM dunglas/frankenphp:1.13.0-php8.5-alpine@sha256:b64048cc72ee412fd7247f45d70011c385850c0bfd0b467cd26cdccc99e158ca AS frankenphp_upstream


# Base image
FROM frankenphp_upstream AS frankenphp_base

SHELL ["/bin/ash", "-eux", "-o", "pipefail", "-c"]

WORKDIR /app

# Permanent dependencies, Composer, and extensions shipped with PHP
# hadolint ignore=DL3018
RUN <<-EOF
	apk add --no-cache file git
	install-php-extensions @composer intl opcache pdo_mysql
EOF

# Pinned APCu, installed with PIE (build dependencies removed afterwards)
# hadolint ignore=DL3018
RUN --mount=type=bind,from=ghcr.io/php/pie:1.5.1-bin,source=/pie,target=/usr/local/bin/pie <<-EOF
	apk add --no-cache --virtual .pie-build-deps $PHPIZE_DEPS linux-headers
	pie install --no-cache apcu/apcu:5.1.28
	apk del --no-network .pie-build-deps
	rm -rf /config/pie
EOF

# Tailwind CSS standalone CLI (no Node.js), pinned and verified by checksum
ARG TAILWIND_VERSION=v4.3.3
ARG TARGETARCH
RUN <<-EOF
	case "$TARGETARCH" in
		amd64) arch=x64; sum=a04d34ceacc8f52cbe8920ad846cdeb61d3d0021dba32db0d1f77c9d9fad7a6c ;;
		arm64) arch=arm64; sum=71ea4be79c9de9827545682df3e040053fb535d37c71ed2cfdedf9385a0868e0 ;;
		*) echo "Unsupported architecture: $TARGETARCH" >&2; exit 1 ;;
	esac
	wget -qO /usr/local/bin/tailwindcss "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-linux-${arch}-musl"
	echo "$sum  /usr/local/bin/tailwindcss" | sha256sum -c -
	chmod +x /usr/local/bin/tailwindcss
EOF

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link docker/frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 docker/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]


# Development image: Composer, zip, Xdebug
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

# hadolint ignore=DL3018
RUN --mount=type=bind,from=ghcr.io/php/pie:1.5.1-bin,source=/pie,target=/usr/local/bin/pie <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-php-extensions zip
	apk add --no-cache --virtual .pie-build-deps $PHPIZE_DEPS linux-headers
	pie install --no-cache xdebug/xdebug:3.5.3
	apk del --no-network .pie-build-deps
	rm -rf /config/pie
	adduser -D -s /bin/ash nonroot
	git config --system --add safe.directory /app
EOF

COPY --link docker/frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]


# Production image: no development tools, non-root user
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link docker/frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Dependencies are only reinstalled if composer.* changes
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link --exclude=docker/ . ./

# hadolint ignore=DL3018
RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	composer dump-env prod
	composer run-script --no-dev post-install-cmd
	php bin/console importmap:install
	php bin/console tailwind:build --minify
	php bin/console asset-map:compile
	rm -f /usr/local/bin/tailwindcss
	chmod +x bin/console
	rm -f /usr/local/bin/composer
	apk add --no-cache libcap-setcap
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp
	apk del --no-network git libcap-setcap
	chown -R www-data:www-data /data/caddy /config/caddy var
EOF

USER www-data
