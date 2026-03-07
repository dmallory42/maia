## Summary
- Describe the high-level change.
- Describe the user-visible or reviewer-relevant outcome.

## Problem
- What was broken, misleading, risky, or unnecessarily complex?
- If this addresses an audit item, link or reference it here.

## What Changed
- List the concrete implementation changes.
- Call out any intentional non-goals or scope limits.

## Why This Approach
- Explain the reasoning behind the chosen fix.
- Note any tradeoffs that reviewers should be aware of.

## Verification
- List the commands you ran.
- Include any targeted tests in addition to broader suite coverage.

## Checklist
- [ ] Tests added or updated where behavior changed
- [ ] Documentation updated where user-facing behavior changed
- [ ] `composer lint`
- [ ] `composer docs:coverage`
- [ ] `vendor/bin/phpunit`
