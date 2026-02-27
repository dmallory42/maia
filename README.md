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

```bash
# from this framework repo
php bin/maia new my-app
```

Why: generates a ready-to-run app skeleton (`app/`, `config/`, `routes/`, `database/`, `public/`) with Maia wired as a dependency.

2. Enter the app and initialize local environment values.

```bash
cd my-app
cp .env.example .env
```

Why: the framework loads `.env` at runtime for config like DB DSN, JWT secret, and log level.

3. Create the SQLite database file used by default config.

```bash
touch database/database.sqlite
```

Why: default `config/database.php` points to `sqlite:database/database.sqlite`.

4. Scaffold your first controller.

```bash
vendor/bin/maia create:controller UserController
```

Why: creates an attribute-based controller class so you can add routes immediately.

5. Register controllers in `routes/api.php`, then load that file from `public/index.php`.

Why: controller classes are not auto-discovered at runtime; explicit registration keeps startup predictable.

Full wiring examples are in [docs/EXAMPLES.md](docs/EXAMPLES.md).

6. Run migrations and start the app.

```bash
vendor/bin/maia migrate
vendor/bin/maia up --port 8000
```

Why: migrations create schema state first; `up` runs PHP's built-in server with `public/index.php` as front controller.

## Test

```bash
vendor/bin/phpunit
```

Current suite includes package-level tests plus integration tests under `tests/Integration`.

Threshold-based unit test gate (default 95% pass rate):

```bash
composer test:threshold
```

Use this to enforce a minimum passing rate locally and in hooks without requiring 100% green during iterative work.

Documentation coverage enforcement (minimum 95%):

```bash
composer docs:coverage
```

Use this to keep class/method docblocks from regressing below agreed coverage.

PSR-12 linting:

```bash
composer lint
```

Use this for consistent style and CI parity before opening a PR.

Enable the repository pre-commit hook to enforce the test pass-rate gate locally:

```bash
git config core.hooksPath .githooks
```

This ensures `git commit` runs the threshold gate automatically.

Optionally override the hook threshold:

```bash
MAIA_TEST_THRESHOLD=100 git commit -m "..."
```

Use this when you want stricter local enforcement for a specific change.

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
