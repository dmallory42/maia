# maia

## Purpose

Maia is an API-first PHP framework distributed as a single Composer package: `maia/framework`.

## Repository Structure

- `src/Core` contains the app kernel, DI container, routing, middleware, HTTP primitives, logging, and testing helpers.
- `src/Orm` contains the database connection layer, query builder, models, relationships, schema builder, and migrator.
- `src/Auth` contains JWT, API key, rate limiting, CORS, validation, and security-header helpers.
- `src/Cli` contains the `maia` CLI and project scaffolding templates.
- `docs/EXAMPLES.md` contains user-facing framework examples.
- `docs/RELEASING.md` contains the release checklist.
- `tools/smoke-new-app.sh` verifies that a freshly scaffolded app can install and boot.

## Development

- Install dependencies: `composer install`
- Lint: `composer lint`
- Docs coverage: `composer docs:coverage`
- Tests: `composer test:threshold`
- Full PHPUnit: `vendor/bin/phpunit`
- Smoke test: `bash tools/smoke-new-app.sh`

## Comment Guidance

- Write comments and docblocks that explain purpose, constraints, edge cases, or tradeoffs.
- Avoid boilerplate comments that just mirror the code or repeat parameter names with no added meaning.
- Prefer fewer high-signal comments over blanket documentation that adds noise.

## Release

- CI must pass on PHP `8.2`, `8.3`, and `8.4`.
- Run the smoke test before tagging.
- Review `CHANGELOG.md`.
- Follow `docs/RELEASING.md`.
