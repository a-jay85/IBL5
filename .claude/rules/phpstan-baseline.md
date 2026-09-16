---
description: PHPStan baseline rules — the two-step regen (neon + counts JSON) the check-baseline-drift gate requires, entries vs. occurrences when auditing a baseline, the constant() dynamic-access false positive, and the analyse:tests merge gate.
paths:
  - "ibl5/phpstan*.neon"
  - "ibl5/phpstan-baseline-counts.json"
last_verified: 2026-09-16
---

# PHPStan Baseline Rules

## `analyse:tests` is merge-blocking

CI runs `composer run analyse:tests` as a gated job. A non-zero result — genuine errors
**or** `ignore.unmatched` stale-baseline entries — fails the build. Any PR that changes
the test-error set must leave `analyse:tests` at 0 to merge.

## Growing a baseline is a TWO-file change

Regenerating a baseline `.neon` alone fails the `check-baseline-drift` CI gate. Both
steps, both files committed (run from `ibl5/`, the cwd the CI job uses):

```bash
composer run analyse:tests:baseline -- --no-progress   # regenerates phpstan-tests-baseline.neon
php bin/check-baseline-drift --update                  # updates phpstan-baseline-counts.json
```

Committing only the `.neon` produces:

```
INCREASE: [phpstan-tests-baseline.neon] classConstant.unused: new (42 entries)
```

`phpstan-baseline-counts.json` tracks counts per identifier per baseline file;
`ibl5/bin/check-baseline-drift` (a required check) fails when a baseline grew without
`--update`. It is a separate gate from PHPStan analysis itself.

Do **not** blind full-regen — it sprawls unrelated drift into the diff. And a burndown
PR's final regen must *fix* genuine new errors, not re-baseline them.

## Auditing a baseline: entries ≠ occurrences

`grep -c "identifier: X"` counts baseline **entries** (distinct message+path blocks), not
error occurrences. One entry can carry `count: N`, so the true occurrence total is the
**sum of `count:` fields** for that identifier. PR bodies and burndown item counts quote
occurrences; an identifier histogram quotes entries. They diverge hard —
`phpunit.assertEquals`: 52 entries = 802 occurrences; `staticMethod.dynamicCall`: 218
entries = 858; `argument.type`: 94 entries = 257. Reconcile any "plan says 805, baseline
shows 52" gap by summing `count:` before concluding the scope changed. It usually didn't.

**`path:` comes AFTER `identifier:` inside an entry — never grep with `-B`.** An entry is
`- / message: / identifier: / count: / path:`. To map identifier→file, capture the `path:`
that *follows* the identifier (`grep -A`, or
`perl -0777 -ne 'while(/identifier:\s*ibl\.X.*?\n((?:.*\n)*?)\s*path:\s*(\S+)/g){print "$2\n"}'`).
A `grep -B5 "identifier: X" | grep path:` pairs each path with the **previous** entry — an
off-by-one that silently yields a wrong file list and cost a re-planned PR in 2026-06.

Treat the live baseline as ground truth: master green ⇒ the baseline *is* the complete
violation set ⇒ a file with no entry has no violation.

To confirm a committed baseline is in sync with the installed PHPStan, run
`composer run analyse` / `composer run analyse:tests`; `[OK] No errors` means no regen is
needed. Local `vendor/bin/phpstan --version` can lag `composer.lock` — `composer install`
first.

## `constant()` dynamic access is an expected false positive

PHPStan cannot see a constant reached only through `constant()`:

```php
constant(self::class . '::G_' . strtoupper($label))
```

Every such constant gets `classConstant.unused`. These are false positives — the
constants *are* used at runtime. **Baseline them** (`composer run analyse:tests:baseline`,
then `php bin/check-baseline-drift --update`); do not change the access pattern, which is
intentional for capture-or-assert helpers. This shows up in golden-master /
characterization tests where many `G_*` constants are read through a shared
`assertGolden(string $label, string $actual)`; a file with 30+ `G_*` constants adding 30+
baseline entries is expected, not a smell.
