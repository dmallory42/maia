# Contributing

## Local setup

Install dependencies for framework development:

```bash
composer install
```

Enable the repo hook if you want local pre-commit enforcement:

```bash
git config core.hooksPath .githooks
```

## Quality checks

Run the main checks before opening a PR:

```bash
composer lint
composer docs:coverage
composer test:threshold
vendor/bin/phpunit
```

If you want a stricter commit threshold:

```bash
MAIA_TEST_THRESHOLD=100 git commit -m "..."
```

## Changelog entries

Every pull request must add a fragment under `.changes/unreleased/`.

Create one with:

```bash
composer changelog:new -- short-description
```

Then replace the starter bullet with a short user-facing note describing the change.

## Comments and docblocks

- Prefer comments that explain intent, constraints, edge cases, or tradeoffs.
- Avoid boilerplate docblocks that only restate the method signature or add placeholders like `Input value` / `Output value`.
- If a method is straightforward, keep the docblock brief rather than padding it with low-signal prose.

## Smoke test

Verify that a newly scaffolded app installs and boots:

```bash
bash tools/smoke-new-app.sh
```

## Repository layout

```text
bin/                # framework CLI entry point
src/Core/           # core framework module
src/Orm/            # ORM module
src/Auth/           # auth/security module
src/Cli/            # CLI module
docs/EXAMPLES.md    # practical app usage examples
tests/Integration/  # full-stack integration tests
```

## Release process

See [docs/RELEASING.md](docs/RELEASING.md).
