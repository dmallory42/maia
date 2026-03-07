# Changelog

## v0.2.0

Quality and correctness release focused on safer defaults, clearer failure modes, and lower-maintenance framework internals.

- HTTP and app runtime:
  fixed the documented ORM bootstrap flow, applied configured container factories and singletons during app startup, rejected invalid typed route parameters with `404`, captured fallback CGI headers like `Content-Type`, aligned request/response header normalization, and normalized scaffolded relative SQLite DSNs against the project root.
- Security and auth:
  restricted JWT signing algorithms to the supported HMAC allowlist, made CORS restrictive by default, hardened rate-limit client identification around trusted proxies, added persistent rate-limit storage support, added a default `Content-Security-Policy: default-src 'none'` header, and made the `unique` validation rule fail explicitly when no uniqueness checker is configured.
- ORM and query building:
  added `Maia\Orm\OrmException` for ORM and migration domain failures, cached per-model reflection metadata, and consolidated internal WHERE/HAVING clause compilation without changing generated SQL.
- CLI, testing, and tooling:
  added `put()`, `patch()`, and `delete()` HTTP test helpers, consolidated repeated CLI `create:*` scaffolding logic, consolidated migration-command path and connection resolution, required changelog fragments for PRs, added `composer changelog:new`, and added a repository pull-request template.
- Validation, caching, and docs:
  added `Validator::extend()` for custom validation rules, made filesystem response-cache failures explicit with optional logging, replaced low-signal generated docblocks with intentful API docs, and added contributor guidance to avoid boilerplate comments.

## v0.1.0

Initial public release of `maia/framework`.

- API-first framework bootstrap with routing, controller registration, middleware, exception handling, logging, and HTTP testing helpers
- ORM support for models, relationships, schema migrations, query building, joins, grouping, `having`, and `upsert`
- Auth and security helpers including JWT, API keys, rate limiting, CORS, validation, and security headers
- CLI scaffolding for apps, controllers, services, models, middleware, requests, tests, and migrations
- SQLite bootstrap helpers and generic response caching middleware
