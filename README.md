# Maia Framework

Maia is an opinionated, API-first PHP framework built as a mono-repo with four packages:

- `maia/core`: app kernel, DI container, HTTP layer, routing, middleware, exceptions, logging, testing helpers
- `maia/orm`: PDO connection wrapper, query builder, models, relationships, schema builder, migrator
- `maia/auth`: JWT auth, API key auth, CORS, rate limiting, security headers, validation
- `maia/cli`: command framework and `maia` project/inspection/migration/scaffolding commands

## Status

Implementation plan in `docs/plans/2026-02-25-maia-framework-plan.md` is fully implemented through Task 29.

## Requirements

- PHP `^8.2`
- Composer

## Install

```bash
composer install
```

## Test

```bash
vendor/bin/phpunit
```

Current suite includes package-level tests plus integration tests under `tests/Integration`.

## CLI

The repo ships a binary entrypoint:

```bash
php bin/maia --help
```

Available commands:

- `new`
- `up`
- `create:controller`
- `create:service`
- `create:model`
- `create:middleware`
- `create:request`
- `create:migration`
- `create:test`
- `migrate`
- `migrate:rollback`
- `migrate:status`
- `routes`
- `describe`

JSON output is supported where relevant with `--json`.

## Typical Workflow

```bash
# scaffold an app
php bin/maia new my-app

# inspect routes
php bin/maia routes --json

# run migrations
php bin/maia migrate

# start local server inside app directory
php bin/maia up --port 8000
```

## Repository Layout

```text
bin/                # framework CLI entry point
src/Core/           # core package
src/Orm/            # ORM package
src/Auth/           # auth/security package
src/Cli/            # CLI package
docs/plans/         # original design and implementation plan
tests/Integration/  # full-stack integration tests
```
