---
description: PHPStan/Infection tooling — always use composer scripts; baseline management; infection exclude staleness; constant() false positives.
last_verified: 2026-09-16
paths:
  - "**/*.php"
  - "ibl5/composer.json"
---

# PHPStan Invocation

**Never call `vendor/bin/phpstan` directly.** Always use the composer scripts:

```bash
composer run analyse              # production code
composer run analyse:tests        # test code
composer run analyse:baseline     # regenerate production baseline
composer run analyse:tests:baseline  # regenerate test baseline
```

These scripts set `--memory-limit=1G` and `--autoload-file` for the custom PHPStan rules bootstrap. Direct `vendor/bin/phpstan` calls skip both, causing OOM failures and missing custom rules.

If you need extra flags (e.g. `--no-progress`), append them via composer's `--` separator:

```bash
composer run analyse -- --no-progress
```

## Baseline management

**New PHPStan rule + baseline entries → two files must be updated:**
```bash
composer run analyse:baseline          # regenerates phpstan-baseline.neon
composer run analyse:tests:baseline   # regenerates phpstan-tests-baseline.neon (tests only)
php ibl5/bin/check-baseline-drift --update   # syncs phpstan-baseline-counts.json
```
CI's "Check baseline drift" compares live counts against `ibl5/phpstan-baseline-counts.json`. A new rule identifier shows as `INCREASE: ibl.<rule>: new (N entries)` → exit 1. Both steps are required; committing only one fails CI.

**After method renames that touch test files:** run `analyse:tests:baseline` in addition to `analyse:baseline` — they maintain separate files; renaming a method makes old entries "not matched" in `ibl5/phpstan-tests-baseline.neon`.

**`constant()` dynamic access → `classConstant.unused` false positives.** Constants accessed only via `constant(self::class . '::G_' . $label)` are opaque to PHPStan. Baseline them with `composer run analyse:tests:baseline`, then run `php ibl5/bin/check-baseline-drift --update`.

## Infection exclude staleness

When a new PHPUnit test is added for a class excluded from `ibl5/infection.json5` (e.g., a View deferred as "no output-assertion test yet"), CI's `Infection exclude staleness` job (`ibl5/bin/check-infection-excludes`) fires:
```
ERROR: 1 stale exclude(s) in infection.json5 now has a test
```
This also fails `Tests and Analysis` and `Infection PHP (per-PR diff)`. **Fix:** remove the exclude line from `ibl5/infection.json5`. Any plan adding a new test for a View class must grep `ibl5/infection.json5` for the class name before committing.
