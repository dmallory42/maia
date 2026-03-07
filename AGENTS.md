# AGENTS

## Project

- This repository is the framework source for `maia/framework`.
- Treat `src/Core`, `src/Orm`, `src/Auth`, and `src/Cli` as internal modules of one public package, not separate public products.

## Primary Commands

- Install dependencies: `composer install`
- Lint: `composer lint`
- Documentation coverage: `composer docs:coverage`
- Test threshold: `composer test:threshold`
- Full test suite: `vendor/bin/phpunit`
- Scaffold smoke test: `bash tools/smoke-new-app.sh`

## Release Checks

- CI should cover PHP `8.2`, `8.3`, and `8.4`.
- Confirm the scaffold smoke test passes before release.
- Check `CHANGELOG.md` and `docs/RELEASING.md` before tagging.

## Discovery

- User-facing usage examples live in `docs/EXAMPLES.md`.
- Contributor workflow lives in `CONTRIBUTING.md`.
- Release workflow lives in `docs/RELEASING.md`.

## Comment Guidance

- Prefer comments and docblocks that explain intent, constraints, behavior, or non-obvious tradeoffs.
- Do not add low-value comments that merely restate the code, such as “Input value”, “Output value”, or “sets the property”.
- For straightforward methods, a concise summary is enough; omit verbose param/return prose unless it adds real context.
