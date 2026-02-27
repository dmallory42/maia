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

## Start From Scratch (New App)

This flow gets you from an empty directory to a running API with database-backed routes.

1. Scaffold a project.
Creates a new Maia application skeleton with standard directories (`app/`, `config/`, `routes/`, `database/`, `public/`) and framework dependency wiring.

```bash
# from this framework repo
php bin/maia new my-app
```

2. Enter the app and initialize local environment values.
Moves into the generated project and creates a local runtime config file from the template.

```bash
cd my-app
cp .env.example .env
```

3. Create the SQLite database file used by default config.
The default database DSN points to `database/database.sqlite`, so this file should exist before migrations run.

```bash
touch database/database.sqlite
```

4. Scaffold your first controller.
Generates an attribute-based controller class in `app/Controllers` so you can add endpoints quickly.

```bash
vendor/bin/maia create:controller UserController
```

5. Register controllers in `routes/api.php`, then load that file from `public/index.php`.
Controllers are registered explicitly at bootstrap time; this keeps startup behavior predictable.

Full wiring examples are in [docs/EXAMPLES.md](docs/EXAMPLES.md).

6. Run migrations and start the app.
Applies pending schema changes, then starts the local PHP dev server using `public/index.php` as the front controller.

```bash
vendor/bin/maia migrate
vendor/bin/maia up --port 8000
```

## Test

```bash
vendor/bin/phpunit
```

Current suite includes package-level tests plus integration tests under `tests/Integration`.

Threshold-based unit test gate (default 95% pass rate):

```bash
composer test:threshold
```

Enforces a minimum passing-test threshold locally and in hooks.

Documentation coverage enforcement (minimum 95%):

```bash
composer docs:coverage
```

Checks docblock coverage against the configured minimum.

PSR-12 linting:

```bash
composer lint
```

Runs PSR-12 style checks for CI parity.

Enable the repository pre-commit hook to enforce the test pass-rate gate locally:

```bash
git config core.hooksPath .githooks
```

Configures local Git commits to run the threshold gate automatically.

Optionally override the hook threshold:

```bash
MAIA_TEST_THRESHOLD=100 git commit -m "..."
```

Overrides the default threshold when you want stricter commit-time checks.

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

# inside the generated app
cd my-app

# inspect routes
vendor/bin/maia routes --json

# run migrations
vendor/bin/maia migrate

# start local server inside app directory
vendor/bin/maia up --port 8000
```

## Repository Layout

```text
bin/                # framework CLI entry point
src/Core/           # core package
src/Orm/            # ORM package
src/Auth/           # auth/security package
src/Cli/            # CLI package
docs/plans/         # original design and implementation plan
docs/EXAMPLES.md    # practical app usage examples
tests/Integration/  # full-stack integration tests
```
