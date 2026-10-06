# CLAUDE.md

@AGENTS.md

## This project overrides AGENTS.md on these points

- Everything runs in Docker through the Makefile, never `symfony serve` nor host PHP: `make up`, `make test`, `make phpstan`, `make cs`, `make css`, `make console c="…"` (`make help` lists them all).
- The Flex contrib recipes are disabled: the bundles they would bring (dama, KnpU, Nelmio) are registered by hand in `config/bundles.php`.
- No `symfony/dotenv`: the application reads the real environment only. In development, `compose.override.yaml` hands `.env` (committed defaults) and `.env.local` (optional, git-ignored) to the `php` and `worker` containers as `env_file` (`make up` after editing them); test values are forced in `phpunit.dist.xml`; production uses the environment of the system only (`compose.prod.yaml`, run with `--env-file /dev/null`).
