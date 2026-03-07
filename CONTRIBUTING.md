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
docs/plans/         # original design and implementation plan
docs/EXAMPLES.md    # practical app usage examples
tests/Integration/  # full-stack integration tests
```

## Release process

See [docs/RELEASING.md](docs/RELEASING.md).
