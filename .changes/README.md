# Changelog Fragments

Each pull request must add at least one Markdown file under `.changes/unreleased/`.

These files serve two purposes:

- They are required before a PR can merge.
- They are the source material for the next release notes and `CHANGELOG.md` update.

## File naming

Use a short descriptive filename, for example:

- `.changes/unreleased/restrictive-cors-default.md`
- `.changes/unreleased/route-param-validation.md`

## File format

Keep entries short and user-facing. A simple bullet list is enough.

Example:

```md
- Fixed typed route parameters so invalid `int`, `float`, and `bool` segments return `404` instead of being silently coerced.
- Added `put()`, `patch()`, and `delete()` helpers to the testing harness.
```
