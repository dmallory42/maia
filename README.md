# ✨ Maia Framework

Maia is an opinionated, API-first PHP framework distributed as a single Composer package: `maia/framework`.

It includes:

- App kernel, dependency injection, routing, middleware, exceptions, logging, and HTTP testing helpers
- SQLite-friendly ORM primitives including query building, models, relationships, schema building, and migrations
- Auth and security helpers for JWT, API keys, CORS, rate limiting, validation, and security headers
- A CLI for scaffolding, migration management, and route/app inspection

👉 See [docs/EXAMPLES.md](docs/EXAMPLES.md) for full usage patterns — bootstrap, controllers, models, validation, relationships, testing, and more.

Recent additions include query-builder `upsert()`, `join()` / `leftJoin()`, `groupBy()` / `having()`, SQLite pragma helpers via `Connection::sqlite()`, and generic response caching middleware in `maia/core`.

## 📋 Requirements

- PHP `^8.2`
- Composer

## 🚀 Getting Started

```bash
composer install
```

To use Maia in an application:

```bash
composer require maia/framework:^0.1
```

### Start a new app from scratch

This flow gets you from an empty directory to a running API with database-backed routes.

**1. Scaffold a project**

Creates a new Maia app skeleton with standard directories (`app/`, `config/`, `routes/`, `database/`, `public/`) and framework wiring.

```bash
php bin/maia new my-app
```

**2. Set up local environment**

```bash
cd my-app
cp .env.example .env
touch database/database.sqlite
```

**3. Scaffold your first controller**

```bash
vendor/bin/maia create:controller UserController
```

**4. Register controllers and wire bootstrap**

Controllers are registered explicitly at boot time — this keeps startup behavior predictable. Full wiring examples are in [docs/EXAMPLES.md](docs/EXAMPLES.md).

**5. Migrate and run**

```bash
vendor/bin/maia migrate
vendor/bin/maia up --port 8000
```

🎉 You've got a running API.

## 🧪 Quality & Testing

```bash
vendor/bin/phpunit                # run full test suite
```

| Command | What it does |
|---------|-------------|
| `composer test:threshold` | Enforces minimum 95% test pass rate |
| `composer docs:coverage` | Checks docblock coverage (minimum 95%) |
| `composer lint` | PSR-12 style checks |

### 🪝 Pre-commit hook

Enable the repo hook to enforce the test gate on every commit:

```bash
git config core.hooksPath .githooks
```

Override the threshold if you want stricter checks:

```bash
MAIA_TEST_THRESHOLD=100 git commit -m "..."
```

## 🔧 CLI

```bash
php bin/maia --help
```

| Command | Description |
|---------|-------------|
| `new` | Scaffold a new Maia application |
| `up` | Start the local PHP dev server |
| `create:controller` | Generate a controller class |
| `create:service` | Generate a service class |
| `create:model` | Generate a model class |
| `create:middleware` | Generate a middleware class |
| `create:request` | Generate a form request class |
| `create:migration` | Generate a migration file |
| `create:test` | Generate a test class |
| `migrate` | Run pending migrations |
| `migrate:rollback` | Roll back the last migration batch |
| `migrate:status` | Show migration status |
| `routes` | List registered routes |
| `describe` | Inspect a command's details |

All commands support `--json` for machine-readable output where relevant.

## 📁 Repository Layout

```text
bin/                # framework CLI entry point
src/Core/           # core framework module
src/Orm/            # ORM module
src/Auth/           # auth/security module
src/Cli/            # CLI module
docs/plans/         # original design and implementation plan
docs/EXAMPLES.md    # practical app usage examples
tests/Integration/  # full-stack integration tests
```

## 📜 License

Maia is licensed under the GNU GPL v3 or later. See [LICENSE](LICENSE).
