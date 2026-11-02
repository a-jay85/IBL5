---
description: Golden snapshot rules for PHPUnit tests. Covers creation and regeneration with GoldenSnapshotTrait and UPDATE_SNAPSHOTS=1, and the switches of the five golden helpers that do not use the trait. Companion to phpunit-tests.md, which sits at its byte cap.
last_verified: 2026-10-30
paths: ibl5/tests/**/*.php
---

# PHPUnit Tests Detail

Companion to `phpunit-tests.md`. Both attach on `ibl5/tests/**/*.php`.

## Golden snapshots

Golden snapshot tests compare rendered output to a committed file under a `__snapshots__` directory beside the test. Use the shared trait `Tests\Support\GoldenSnapshotTrait` (`ibl5/tests/Support/GoldenSnapshotTrait.php`): add `use Tests\Support\GoldenSnapshotTrait;` and `use GoldenSnapshotTrait;` in the class, then call `$this->assertSnapshotMatches($actual, 'name.html')`.

- **A missing snapshot fails the test.** The message says to run with `UPDATE_SNAPSHOTS=1`. A helper that writes the file when it is absent lets a deleted or misnamed snapshot pass green, so never write one.
- **Create or regenerate with the switch.** From `ibl5/`, run `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit <test path>`. Only the literal value `1` writes; unset, empty, and `0` do not. With the switch on, the helper creates the directory, overwrites the file whether or not it exists, and skips the comparison. Review `git diff` on the `__snapshots__` files, then commit them with the test change.
- **Switch placement.** Keep the switch out of `ibl5/phpunit.xml`, CI, and shell profiles. Without it the comparison is an exact `assertSame` string match.
- **Default directory.** It is `__snapshots__` next to the test file. Override `protected function snapshotDirectory(): string` only when snapshots live elsewhere.

Five other golden helpers do not use the trait. Each already fails on a missing file and has its own switch:

| Test file | Switch |
|-----------|--------|
| `ibl5/tests/Module/ThemeIblOutputTest.php` | `THEME_GOLDEN_WRITE=1` |
| `ibl5/tests/FreeAgency/FreeAgencyViewTest.php` | `IBL_GOLDEN_CAPTURE=1` |
| `ibl5/tests/Trading/TradingViewGoldenMasterTest.php` | `IBL_GOLDEN_CAPTURE=1` |
| `ibl5/tests/Trading/TradeOfferFormViewTest.php` | `IBL_GOLDEN_CAPTURE=1` |
| `ibl5/tests/DatabaseIntegration/TeamServicePageDataCharacterizationTest.php` | `CAPTURE_GOLDENS=1` |

## Mutation Testing (Infection)

1. **Per-PR diff.** Every PR touching `classes/**/*.php`; scopes to changed lines (`--git-diff-filter=AM --git-diff-lines`), ~minutes, posts a PR comment.
2. **Weekly full.** Mon 03:00 UTC.
3. **On-demand full.** Apply the `mutation-test` label.

Thresholds: **100% MSI / 100% Covered MSI** (`ibl5/infection.json5`, `.github/workflows/mutation-pr.yml`). Config: `phpunit-mutation.xml`; per-file deferrals live in `infection.json5`.

**Local run.** `bin/mutation-diff <base-ref>`; details in `.claude/rules/phpstan-invocation.md`.
