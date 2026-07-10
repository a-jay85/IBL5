---
description: ORDER BY on rendered/LIMIT-cut output must be a total order (final term unique within the result set); enforced by a PHPStan proxy rule, not provable totality.
last_verified: 2026-07-05
---

# ADR-0083: Rendered `ORDER BY` Must Be a Total Order (Unique Final Tiebreaker)

**Status:** Accepted
**Date:** 2026-07-05
**Deciders:** A-Jay

## Context

- A visual-regression check (CI #1329) flaked intermittently. Root cause: a `SELECT ... ORDER BY <non-unique columns>` with no unique final tiebreaker. When the leading sort columns tie, MariaDB returns the tied rows in an incidental, engine-chosen order that is not stable across data changes. When the ci-seed data shifted, the tie order flipped, the rendered HTML reordered, and the pixel snapshot diverged — a real product bug (non-deterministic user-facing output), surfaced as a "flaky" test.
- Indeterminate tie order matters specifically when the query's output is **rendered** (row order is user-visible) or **`LIMIT`-cut** (which rows survive the cut depends on tie order — a different row can be dropped run-to-run). For such queries, a *partial* order (ties unresolved) is a latent bug even when tests pass today.
- The offending SeasonHighs query is already fixed ad-hoc in a separate worktree — it is **not** part of this ADR/PR; it is named only as the motivating incident.
- Several other queries share the same shape and remain unresolved (StandingsRepository win/loss rankings, DraftHistoryRepository, RefreshPlayoffSeriesResultsStep). They are captured as tracked debt (baselined) and swept by a follow-up plan, `sort-determinism-offender-sweep` (not this PR — see `## Out of Scope`).
- The reference-correct idiom already exists in the codebase: CareerLeaderboards, SeasonLeaderboards, Voting, and AllStarAppearances append `, pid ASC` as the final ORDER BY term, making the sort total (`pid` is the player-row PK, unique within the result set).
- Adding the enforcement rule creates a new `ibl5/phpstan-rules/*.php` file, which trips the `bin/adr-check` decision-trigger (new PHPStan rule = architectural constraint requiring an ADR). This ADR is that resolution — no `no-adr:` bypass comment is used.

## Decision

- **Convention:** any SQL `ORDER BY` whose result is rendered to the user or cut by `LIMIT` MUST express a **total order** — its FINAL ordering term must be a column that is unique within the result set (normally the row primary key, e.g. `pid`, `id`, `box_id`, `SchedID`). Leading sort terms express intent; the final unique term breaks all remaining ties deterministically. The canonical form is the existing `..., pid ASC` idiom.
- **Enforcement:** a custom PHPStan rule, `OrderByRequiresUniqueTiebreakerRule` (namespace `PHPStanRules\`, identifier `ibl.orderByMissingTiebreaker`), inspects static SQL string literals (`PhpParser\Node\Scalar\String_`) and flags an `ORDER BY` whose final term is not a recognized-unique column. Full rule design is in `ibl5/phpstan-rules/OrderByRequiresUniqueTiebreakerRule.php`.
- **Deliberate proxy, not a proof:** true totality of an arbitrary `ORDER BY` is undecidable at the static-analysis layer (the rule cannot know a column's uniqueness from a string literal). The rule therefore enforces a **checkable proxy** — "final term is a name on a curated unique/PK allowlist" — and is intentionally conservative: it favors false-negatives (misses) over false-positives (noise), because existing violations are captured in the PHPStan baseline as tracked debt and the goal is to stop *new* partial-order queries, not to retrofit-prove every existing one. The allowlist and carve-outs (notably `LIMIT 1` single-row results) are the concrete, reviewable definition of the proxy.
- **Scope of enforcement:** static string literals only. Concatenated/interpolated SQL is separately banned by `BanSqlStringConcatenationRule` / `BanSqlStringInterpolationRule`, so ORDER BY clauses reliably live in static literals; walking `Concat`/`Encapsed` nodes is out of scope.
- **Rollout:** existing violations are captured in `ibl5/phpstan-baseline.neon` (zero code churn) and remain as tracked debt; the follow-up `sort-determinism-offender-sweep` plan drives them to zero. New violations in new code are blocked at PR time.

## Consequences

- New code that renders or `LIMIT`-cuts an `ORDER BY` must end it with a recognized-unique column, or acknowledge a genuine exception with a native `// @phpstan-ignore ibl.orderByMissingTiebreaker` plus a justifying note (same acknowledgment mechanism as ADR-0077).
- The rule's baselined violations introduce a new identifier into `phpstan-baseline.neon`; `phpstan-baseline-counts.json` must be refreshed in this same PR (via `php bin/check-baseline-drift --update`) or the PR-blocking drift check fails.
- The proxy allowlist is a curated, hand-maintained set; adding a genuinely-unique column name that the rule doesn't yet recognize is a one-line allowlist edit in the rule.

## Lineage

None.

## References

- `ibl5/phpstan-rules/OrderByRequiresUniqueTiebreakerRule.php`
- `ibl5/phpstan-baseline.neon`
- `ibl5/docs/decisions/0077-guard-htmlsanitizer-trusted-with-phpstan-rule.md`
