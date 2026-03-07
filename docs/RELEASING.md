# Releasing Maia

Use this checklist before creating a release tag such as `v0.1.0`.

## Pre-release checks

Run the repo quality gates locally or confirm they are green in CI:

```bash
composer lint
composer docs:coverage
composer test:threshold
vendor/bin/phpunit
```

Every merged PR should already have contributed a changelog fragment under `.changes/unreleased/`.
Use those fragments as the source material for the next release notes and for the new release entry in `CHANGELOG.md`.

## Scaffold smoke test

The smoke test proves that a newly generated Maia app can install dependencies, register a route, and serve a request.

In CI, the generated app installs `maia/framework` from the local checkout through a Composer path repository because the release tag does not exist on Packagist yet.

Run it locally with:

```bash
tools/smoke-new-app.sh
```

## Release steps

1. Confirm CI is green on PHP `8.2`, `8.3`, and `8.4`.
2. Confirm the scaffold smoke test passes.
3. Review `.changes/unreleased/` and turn those fragments into the new release entry in `CHANGELOG.md`.
4. Move the release-ready notes from `.changes/unreleased/` into the tagged `CHANGELOG.md` entry.
5. Clear or archive the used fragments after the release notes are captured.
6. Create and push the tag:

```bash
git tag v0.1.0
git push origin v0.1.0
```

7. Publish the GitHub release notes for the tag using the same finalized notes.
