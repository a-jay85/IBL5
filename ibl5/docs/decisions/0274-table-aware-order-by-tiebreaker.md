---
description: The ORDER BY tiebreaker rule also accepts an ordering when its columns plus the WHERE-bound columns cover a PRIMARY KEY or NOT NULL UNIQUE key of the single FROM table, read from the schema dump.
last_verified: 2026-11-21
---

# ADR-0274: Table-Aware ORDER BY Tiebreaker Check

**Status:** Accepted
**Date:** 2026-11-21
**Deciders:** A-Jay

## Context

ADR-0083 enforces total ORDER BY clauses with `ibl.orderByMissingTiebreaker`, a rule that accepts a query only when its final sort term is a column name on a short allowlist. The allowlist knows nothing about tables. It forced 40 inline ignores on orderings that were provably total, such as one ending on `pickid`, the primary key of `ibl_draft_picks`, or on a composite UNIQUE key. It also accepted `sid` on any table, even though `sid` is unique only in `nuke_stories` and is a plain foreign key elsewhere.

## Decision

`OrderByRequiresUniqueTiebreakerRule` keeps its carve-outs (no ORDER BY, GROUP BY, LIMIT 1) and its allowlist as a fallback, with `sid` removed from the list. It adds one accept path. A query passes when it reads a single table present in `ibl5/docs/schema/current-schema.sql`, and the ORDER BY columns plus the `col = ?` columns of an OR-free AND chain contain every column of one usable key. A usable key is a PRIMARY KEY or a UNIQUE key whose columns are all NOT NULL. When two rows tie on the whole ORDER BY, they then share that key, so they are the same row.

`SchemaKeyMap` parses the dump once per PHPStan worker. `OrderBySqlShape` reads the query and returns null for anything it cannot read safely: JOIN, UNION, DISTINCT, HAVING, a subquery, a comma FROM list, a qualified `db.table`, or a sprintf directive. A null shape gets no table-aware acceptance, so the allowlist alone judges it. A WHERE binds no column when its text holds OR, NOT, XOR, BETWEEN, CASE, `||` or `!`. Only a `?` placeholder binds a column, and an ORDER BY name the SELECT list defines with AS never counts as a column. The tests live in `ibl5/tests/PHPStanRules/OrderByRequiresUniqueTiebreakerRuleTest.php`.

## Alternatives Considered

- **Generated key file:** commit a key list built from the schema. Rejected because it needs a generator script, a freshness gate, and a second copy of schema facts that can drift. The dump CI already regenerates serves the same purpose.
- **Trailing-column match:** compare only the last ORDER BY columns with a key. Rejected because key containment is sound for every column order, while a suffix match rejects `ORDER BY year, award, name` when the key is declared `(name, year, award)`.
- **Key map only:** drop the allowlist. Rejected for this change, because every resolvable query ending in a non-unique `pid` or `teamid` would start failing. Those are true positives that belong in a separate burn-down.
- **Parse more shapes:** read JOINs, DISTINCT and sprintf directives. Rejected because each needs reasoning about row multiplication or injected text that a regex cannot do safely.

## Consequences

- Positive: 18 inline ignores left `ibl5/classes`, and a new query that ends on a real key passes with no ignore.
- Positive: `sid` is now proven per table. A query over a table where `sid` is not a key fails.
- Negative: a table missing from the dump gets no key-map acceptance. That covers views, and a table whose migration merged before CI refreshed the dump. Run `bin/regen-schema-dump` in that PR or keep an inline ignore.
- Negative: four SQL fragments built by concatenation that end on `nuke_stories.sid` now carry inline ignores, because the rule sees no FROM in the fragment.
- Negative: the PHPStan result cache does not hash the dump, so a dump-only change can leave a stale local verdict until the cache refreshes. CI's analysis decides the merge.

## References

- ADR-0083 (`ibl5/docs/decisions/0083-order-by-requires-unique-tiebreaker.md`), which this refines. It stays unchanged because ADRs are append-only.
- `ibl5/phpstan-rules/OrderByRequiresUniqueTiebreakerRule.php`
- `ibl5/phpstan-rules/SchemaKeyMap.php`
- `ibl5/phpstan-rules/OrderBySqlShape.php`
- `ibl5/docs/schema/current-schema.sql`
- `bin/regen-schema-dump`
