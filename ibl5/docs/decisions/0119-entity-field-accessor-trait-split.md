---
description: Narrows ADR-0001's trait rejection — a single-consumer trait carrying only one entity's own pure field reads is a file split, not composition, and is permitted.
last_verified: 2026-09-04
---

# ADR-0119: Entity field-accessor trait splits are not trait composition

**Status:** Accepted
**Date:** 2026-09-04

## Context

ADR-0001 rejected trait-based composition when it established the interface-driven Repository/Service/View architecture, on two grounds: traits cannot be mocked in PHPUnit, so every test would hit the real database; and a class with three traits has three responsibilities hiding inside one name, defeating the whole clarity goal. ADR-0003 and ADR-0014 each rejected a trait alternative for a third reason — a trait offers no mechanical enforcement against a contributor writing the logic inline anyway. All three traits in `ibl5/classes/` today are multi-consumer DRY helpers. Maintenance backlog item 1.33 then produced a case none of those three rejections anticipated: `ibl5/classes/Player/Player.php` had accumulated 66 pure field getters of the form `return $this->playerData?->x;`, and the item's stated goal is reducing that file's size while keeping `Player` source-compatible for 138 caller files. Delegating those getters to collaborator objects leaves the file the same length — a delegating stub is the same six lines as the body it replaces — and raises adding one player attribute from a four-site edit to a six-site edit, re-opening the exact defect finding 1.10 closed.

## Decision

A trait is permitted when **all** of the following hold: it has exactly one consumer; every method it carries is a pure read of a single property the consumer owns; it declares no property, no constructor, and no dependency of its own; and the consumer already declares those methods on an interface. Such a trait is a *file split of one class's own accessors*, not composition, and it is not covered by ADR-0001's rejection. A trait carrying a collaborator call, a repository call, I/O, a decision, or any state is trait composition and remains rejected. `Player\PlayerIdentityGetters`, `Player\PlayerContractGetters`, and `Player\PlayerRatingsGetters` are the first application; each touches only `$this->playerData`, and every collaborator-backed method (`$this->nameDecorator`, `$this->injuryCalculator`, `$this->contractCalculator`, `$this->contractValidator`, `$this->repository`) stays in `Player.php`. Enforced by `ibl5/tests/Player/PlayerPublicApiSurfaceTest.php`, which pins the consumer's full public signature surface so the split cannot change what callers see.

## Alternatives Considered

- **Delegation to per-domain value-object collaborators** — each getter becomes a one-line call to a domain object. Rejected because: the stub is the same six lines as the body, so the file does not shrink at all, and it raises the add-an-attribute cost from four sites to six, regressing finding 1.10.
- **An abstract base-class chain** — `Player extends PlayerContractAccessors extends PlayerRatingsAccessors extends PlayerIdentityAccessors`. Rejected because: a four-deep inheritance chain built solely to split a file is a worse artifact than a trait, it consumes PHP's single-inheritance slot permanently, and it would need this same ADR anyway.
- **Rewriting the 138 call sites to read the domain objects directly** — the textbook extraction. Rejected because: 138 files of churn for a size finding is disproportionate blast radius, and `Player\Contracts\PlayerInterface` would still have to declare all 73 methods.
- **Deleting the redundant `@see` docblocks only** — shaves roughly 73 lines with no extraction. Rejected because: it is cosmetic, leaves the domains unseparated, and does not address the finding.

## Consequences

- Positive: `Player.php` drops from 671 LOC to roughly 275, below the 500-LOC hot-file threshold, and each domain's getters are findable in one file.
- Positive: adding a player attribute stays a four-site edit (`PlayerData`, `PlayerRepository::FIELD_MAP`, the relevant trait, `PlayerInterface`) — the collaborator design would have made it six.
- Positive: ADR-0001's mockability objection is not engaged — these traits touch no database and no collaborator, so there is nothing to mock.
- Negative: a future reader must know that `Player`'s getters live in three trait files, not in `Player.php`. The class docblock names them.
- Negative: the permission is stated in prose here, not mechanically enforced — a contributor could add a collaborator call to one of these traits and nothing would fail. The four conditions above are deliberately narrow so that a violation is obvious in review.

## References

- `ibl5/docs/decisions/0001-interface-driven-architecture.md` — the trait-composition rejection this ADR narrows
- `ibl5/classes/Player/Player.php` — the consumer; declares the three traits and retains every collaborator-backed method
- `ibl5/tests/Player/PlayerPublicApiSurfaceTest.php` — pins the public signature surface across the split
- `ibl5/docs/backlog/maintenance-backlog.md` — item 1.33, the finding this resolves
