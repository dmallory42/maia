## Maia v0.2.0

This release focuses on framework correctness, safer defaults, and cleanup of duplicated or misleading behavior across the stack.

### Highlights

- Fixed the ORM bootstrap path so the documented setup now works as shown.
- Applied configured container factories and singletons during application bootstrap.
- Rejected invalid typed route parameters with `404` instead of silently coercing them.
- Made CORS restrictive by default and added a default API-safe CSP header.
- Hardened rate limiting around trusted proxy behavior and added persistent rate-limit storage support.
- Added `Maia\Orm\OrmException` for ORM and migration domain failures.
- Added `Validator::extend()` for custom validation rules.
- Added `put()`, `patch()`, and `delete()` HTTP test helpers.

### Included

- HTTP/runtime fixes for route dispatch, request header fallback handling, header normalization, and scaffolded SQLite DSN resolution
- Security and auth improvements for JWT algorithms, CORS defaults, rate limiting, security headers, and unique validation behavior
- ORM and query-builder cleanup through domain exceptions, reflection caching, and shared clause compilation
- CLI and contributor workflow improvements for scaffolding, migration commands, PR templates, and changelog tooling
- Documentation cleanup replacing low-signal docblocks with intentful API docs and clearer contributor guidance

### Release readiness

- CI coverage for PHP 8.2, 8.3, and 8.4
- Scaffold smoke test coverage for a freshly generated app
- Changelog-fragment workflow now in place for future release note generation

### Links

- Examples: `docs/EXAMPLES.md`
- Contributing: `CONTRIBUTING.md`
- Release process: `docs/RELEASING.md`
