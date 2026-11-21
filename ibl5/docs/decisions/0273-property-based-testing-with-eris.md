---
description: ADR-0273. Property tests on pure money and rules math use giorgiosironi/eris, a dev-only dependency, wrapped in a trait that pins the seed to 20261120 and replays through ERIS_SEED
last_verified: 2026-11-21
---

# ADR-0273: Property-based testing with Eris

**Status:** Accepted
**Date:** 2026-11-21

## Context

The suite has no property testing. Every test checks named examples. Two rules are stated as invariants over a range of inputs: the max contract raise and salary scales in `ibl5/docs/LEAGUE_RULES.md`, and the head-to-head tiebreaker, where every game has one winner and one loser. Example tests cover the cases someone thought of. A property test checks the rule across many generated inputs, shrinks a failing input, and prints a seed to replay it.

Adding property tests needs a library, so `composer.json` gains a new `require-dev` entry.

## Decision

Add `giorgiosironi/eris` ^1.3 as a dev-only dependency. Production code never loads it.

Tests use the `Tests\Support\PinnedErisSeed` trait. It wraps `Eris\TestTrait` and pins the seed to 20261120, so CI runs are deterministic. A failure prints a `Reproduce with:` line. Setting `ERIS_SEED` to digits replays that seed or explores a new one. A non-digit value throws `InvalidArgumentException`.

Tests draw scalar values only (`Generators::choose`, `Generators::elements`) and cap iterations at 200. A test that needs a composite input builds it from one scalar seed through an object-scoped `Random\Randomizer`. Property tests live in registered suite directories, so they also run under `ibl5/phpunit-mutation.xml`.

## Alternatives Considered

- **innmind/black-box 7.0.1.** A property-testing library that also installs against PHPUnit 13. Rejected because its PHPUnit trait reads only a set-size variable and a shrinking toggle, and takes no seed, so a CI failure cannot be replayed. Its 6.x line would work with an older PHPUnit, but pinning an old major for a new dependency adds upgrade debt.
- **An in-repo `mt_srand` loop.** Needs no package. Rejected because it gives no shrinking and no replay line, and each new property would rebuild both. Eris supplies both for one MIT dev package that requires only `php ^8.1`.
- **Pin the seed with an `ERIS_SEED` entry in `ibl5/phpunit.xml`.** Needs no PHP code. Rejected because it edits the shared runner config and does not reach `ibl5/phpunit-mutation.xml` unless duplicated there. The trait applies under both configs and rejects a malformed seed.
- **Eris `vector` and `tuple` generators.** Give smaller counterexamples. Rejected because in the prototype a failing vector property exhausted the 128 MB memory limit while Eris built shrink candidates, before it printed the replay line. A scalar matrix seed shrinks cheaply and still replays exactly.
- **Exclude the property tests from `ibl5/phpunit-mutation.xml`.** Rejected because it adds an exclusion line to keep in sync and loses their kill power on later edits to `ContractRules` and `AggregateTiebreaker`. The pinned seed removes the flakiness that would argue for excluding them.

## Consequences

- Positive: a rule stated as an invariant gets checked across many inputs, with a shrunk counterexample and a replay line on failure.
- Positive: CI results are deterministic, and Infection runs stay stable from run to run.
- Negative: one more dev package to keep current.
- Negative: scalar-only generators give less minimal counterexamples than vector shrinking would.
- Negative: `ERIS_SEED` reaches only tests that use the trait. Other seeded loops keep their own seeds, and the `PLR_PROPERTY_SEED` of the sibling PLR property tests stays a separate variable.

## References

- `ibl5/tests/Support/ErisSeed.php`
- `ibl5/tests/Support/PinnedErisSeed.php`
- `ibl5/tests/League/ErisSeedTest.php`
- `ibl5/tests/League/ContractRulesPropertyTest.php`
- `ibl5/tests/Standings/AggregateTiebreakerPropertyTest.php`
- `ibl5/composer.json`
- `ibl5/phpunit-mutation.xml`
- `ibl5/docs/LEAGUE_RULES.md`
- `.claude/rules/phpunit-tests-detail.md`
