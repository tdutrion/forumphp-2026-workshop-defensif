# CLAUDE.md

@AGENTS.md

## This project overrides AGENTS.md on these points

- Everything runs in Docker through the Makefile, never `symfony serve` nor host PHP: `make up`, `make test`, `make phpstan`, `make cs`, `make css`, `make console c="…"` (`make help` lists them all).
- The Flex contrib recipes are disabled: the bundles they would bring (dama, KnpU, Nelmio) are registered by hand in `config/bundles.php`.
