# AGENTS

## CLI Commands
- `maia new <name>` scaffolds a Maia app project.
- `maia up [--port <port>]` starts the local PHP dev server.
- `maia routes` lists routes; use `--json` for structured output: `{"routes":[...]}`.
- `maia describe` emits project structure; use `--json` for machine-readable manifest containing routes/models/middleware/config.
- `maia migrate`, `maia migrate:rollback`, and `maia migrate:status` manage database migrations.
- `maia migrate --json` outputs `{"migrated":<count>}`.
- `maia migrate:rollback --json` outputs `{"rolled_back":<count>}`.
- `maia migrate:status --json` outputs `{"migrations":[{"migration":"...","ran":true|false}]}`.
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
