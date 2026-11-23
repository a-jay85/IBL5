---
description: ADR-0278. Trace-pinned evidence may cite a capture hosted in the private jsb-native repo, pinned by commit and sha256.
last_verified: 2026-11-22
---

# ADR-0278: Trace-pinned evidence may cite a jsb-native capture

**Status:** Accepted
**Date:** 2026-11-22

## Context

ADR-0266 lets a port PR clear the engine golden hold when a `TestTracePinned*` test pins the port to a raw JSB capture on the base branch. A capture is a per-call dump of the original binary, so it is derived from `jumpshot.exe`. That content belongs in the private jsb-native repo. IBL5 is public, so its `engine/testdata/jsb-captures/` directory cannot hold it. The jsb-native parity loop writes its captures to jsb-native `captures/`. Before this change, no port PR could cite one.

## Decision

An evidence entry may set `capture_host: jsb-native`. It then names `captures/<name>.jsonl` and pins `capture_commit` (a full 40-character sha) and `capture_sha256`. `tools/postplan-harness/harness/trace_pinned.py` finds jsb-native beside the main IBL5 checkout from `git rev-parse --git-common-dir`, with no env override. It reads the capture bytes from that commit object and ignores the jsb-native working tree. The commit must be reachable from jsb-native `main`, and the bytes must match the recorded sha256. The coverage run and the perturbed run load a staged copy of those bytes through `JSB_TRACE_CAPTURE_DIR`. Every other hold reason from ADR-0266 still applies. New hold reasons are `capture-bad-host`, `capture-commit-bad`, `capture-host-unavailable`, `capture-commit-off-main`, and `capture-not-at-commit`. The owner chose on 2026-11-22 to skip trace review for this path. The loop messages the owner only when it is blocked.

## Alternatives Considered

- Commit each capture to IBL5 in its own PR. Rejected because the capture is derived from the binary and IBL5 is public.
- Read the capture from the jsb-native working tree. Rejected because an uncommitted or rewritten file could then clear the hold.
- Point the check at jsb-native through an env var. Rejected because an env var could aim the arming check at any repo.

## Consequences

- Positive: a port PR can clear the golden hold against a private capture without the owner reviewing a capture PR.
- Positive: no address, capture content, or decompiled text enters IBL5.
- Negative: CI has no jsb-native checkout, so a hosted entry returns `capture-host-unavailable` there and arming holds. Only the local post-plan harness can verify it.
- Negative: a hosted pinned test skips in CI. `jsbtrace.SkipIfAbsent` prints the reason as a visible `--- SKIP` line.

## References

- ADR-0266 (`ibl5/docs/decisions/0266-trace-pinned-golden-clearance.md`).
- `tools/postplan-harness/harness/trace_pinned.py`
- `tools/postplan-harness/tests/test_trace_pinned.py`
- `engine/internal/jsbtrace/jsbtrace.go` (`SkipIfAbsent`)
- `engine/internal/jsbtrace/external_test.go`
- `engine/docs/jsb-trace-capture.md`
- `engine/testdata/jsb-captures/README.md`
