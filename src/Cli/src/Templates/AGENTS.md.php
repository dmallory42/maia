<?php

return static function (array $vars): string {
    return <<<'MD'
# AGENTS

## CLI Commands
- `maia routes` lists routes; use `--json` for structured output.
- `maia describe` emits project structure; use `--json` for machine-readable manifest.
- `maia migrate`, `maia migrate:rollback`, and `maia migrate:status` manage database migrations.
- `maia create:*` commands scaffold application components.

## Conventions
- Controllers: `app/Controllers`
- Services: `app/Services`
- Models: `app/Models`
- Middleware: `app/Middleware`
- Requests: `app/Requests`

## Discovery
- Use `maia routes --json` to discover runtime endpoints.
- Use `maia describe --json` to inspect routes, models, middleware, and config state.
MD;
};
