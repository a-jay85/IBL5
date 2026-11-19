---
description: An engine port whose Go test pins a raw JSB capture already on the base branch clears its golden-snapshot hold with the trace-pinned token, checked by harness.trace_pinned, with no owner and no second reviewer.
last_verified: 2026-11-18
---

# ADR-0266: Trace-Pinned Tests Clear the Engine Golden Hold

**Status:** Accepted
**Date:** 2026-11-18
**Deciders:** repo owner

## Context

Every engine port PR that regenerates `engine/internal/sim/testdata/golden.json` waits on the owner. Arming condition (5) holds any headless golden change, and condition (7) holds the plan's `golden-new` token. Five ports in a row (#3908, #3918, #3949, #4008 and #4040) sat in that hold. The owner cannot judge a regenerated snapshot by eye either. ADR-0231 gave a second reviewer the power to clear some holds, but the reviewer cannot judge an engine snapshot any better than the owner. The owner decided that a Go test that reproduces the original JSB binary's own recorded outputs is stronger evidence than a person reading the diff.

## Decision

A plan whose only hold token is `trace-pinned` gets conditions (5) and (7) lifted at arming time when `tools/postplan-harness/harness/trace_pinned.py` returns `ok`. The module reads the committed evidence file `engine/trace-evidence/<branch>.json` and checks five things. Every changed path is an engine `*.go` file, a golden file, or the evidence file itself. The named `TestTracePinned*` test is in the diff and its blob matches the evidence. The raw capture under `engine/testdata/jsb-captures/` is on the base branch with the recorded sha256. The test passes under `go test -json`, and a skip or a missing event counts as not run. The test fails when the harness reruns it against a copy of the capture with shifted outputs. That perturbation run is the guard against a test that copies its expected values from the new code. The token sits in a new `self-cleared` set in `bin/lib/hold-trigger-tokens.txt` and in `SELF_CLEARED_PLAN_TRIGGERS` in `armable.py`. It never reaches the second reviewer, so any verdict other than `ok` keeps the owner hold. A PR that adds or edits a raw capture always holds for the owner, because condition (5) now also fires on the capture directory and the lift refuses it. That owner review of each capture's header (source tool, JSB build, function address) is the bound on a fabricated capture. `bin/check-plan` gate `[H]` fails a plan that names `trace-pinned` next to another token or has no `harness.trace_pinned check` step outside a fenced block. `pr-triage --arm` stays owner-only for every golden change, since it has no checkout, no Go toolchain and no way to run the perturbation test.

## Alternatives Considered

- **Seat route.** Send `trace-pinned` to the second reviewer, the way `sql-equivalent` works. Rejected because the seat cannot judge an engine snapshot, and the owner decided the test replaces review for this class.
- **Derived fixtures.** Let the port PR carry fixtures built from the capture. Rejected because the same PR could then write both the fixture and the code that matches it. Anchoring on a capture already on the base branch forces the capture through its own owner-held PR first.
- **Shell clearance.** Honor the token in `bin/lib/pr-armable.sh` for `pr-triage --arm`. Rejected because it would need a second, bash copy of the perturbation run, and the shell path has no checkout to run it in.

## Consequences

- Positive: an engine port with a trace-pinned test merges with no owner step once its capture is on master.
- Positive: the first port for a new JSB function ships as two PRs. The capture lands in an owner-held PR, then the code lands with `trace-pinned`.
- Negative: a capture whose header is wrong but plausible could pass the owner's capture review and then anchor a wrong port. The runbook `engine/docs/jsb-trace-capture.md` lists what the owner checks.
- Negative: arming now runs `go test` twice for a `trace-pinned` plan, bounded at 300 seconds per run.

## References

- `tools/postplan-harness/harness/trace_pinned.py`: the evidence checker and its `record` and `check` commands.
- `tools/postplan-harness/harness/armable.py`: `trace_pinned_lifts` and the condition (5) and (7) branches.
- `bin/lib/hold-trigger-tokens.txt`: the `self-cleared` set.
- `bin/check-plan`: gate `[H]`'s stand-alone and check-step rules.
- `engine/internal/jsbtrace/jsbtrace.go`: the Go capture loader.
- `ibl5/docs/decisions/0231-second-reviewer-clears-holds.md`: the reviewer seat this class bypasses.
