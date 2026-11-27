---
description: ADR-0286. JSB trace captures live in IBL5 under engine/testdata/jsb-captures/. Retires the jsb-native capture host of ADR-0278 and adds a per-file size cap.
last_verified: 2026-11-27
---

# ADR-0286: JSB trace captures live in IBL5

**Status:** Accepted
**Date:** 2026-11-24

## Context

ADR-0278 hosted captures in the private jsb-native repo. CI has no jsb-native checkout, so `harness.trace_pinned` held every hosted entry as `capture-host-unavailable`, and the pinned Go tests skipped. The owner decided on 2026-11-24 to move the captures into IBL5. That decision accepts that the capture files become public.

## Decision

Captures live at `engine/testdata/jsb-captures/`. `capture_host: jsb-native` is retired. An entry that carries it holds as `capture-bad-host`.

The defense from ADR-0266 stays. The harness reads the capture bytes from the committed object on the base branch, checks `capture_sha256`, and requires the working tree to match. It offers no env override and no pointer to another repo.

`_check_capture` enforces a per-file cap of 8 MiB (8388608 bytes) as hold reason `capture-too-large`. `test_every_committed_capture_under_size_cap` pins the cap against every committed capture. The largest capture today is `pl-0009.jsonl` at 7113143 bytes.

**Base-branch rule.** A trace-pinned check reads the capture from base. A PR that adds a capture together with its evidence holds as `capture-in-diff` and can never clear `trace-pinned`. `allowlist_verdict` rejects the capture path before `_check_capture` runs. A capture file always routes to `golden-new` (ADR-0266), so that PR goes to the owner. The normal path is ADR-0266's two PRs. The owner-held capture PR lands first, then the port PR records against master.

**Fresh-capture handoff.** The parity loop's `trace` and `record` write the capture into an IBL5 worktree named `jsb-capture-<row>`. That worktree ships as the owner-held capture PR. `port` refuses until the file is on IBL5 `origin/master` with the same sha256.

**Capture content.** A scan of the ten files this change adds, for IPv4 addresses, `192.168.`, `/Users/`, `C:\`, `password`, `utm`, `ssh` and `localhost` found nothing. The header carries `format`, `source`, `jsb_build`, `function` (a Ghidra `FUN_` name) and `address`. Records carry `caller` addresses. These fields belong to the capture format, and `engine/testdata/jsb-captures/README.md` requires `address`. They are accepted. The binary, decompiled C, the master reference, VM details and the whole-day replay recordings (ADR-0279) stay in jsb-native.

## Alternatives Considered

- **Keep `jsb-native` as a fallback host.** Rejected because no entry would use it after the move and CI can never reach it.
- **Git LFS.** Rejected because the files sit far below GitHub's 50 MB warning and LFS adds a second fetch path to CI.
- **An env override that points at another path.** Rejected again, as in ADR-0278, because it could aim the arming check at any repo.

## Consequences

- Positive: CI can now run the Go pinned tests, because it has the captures. A missing capture fails `go test`, because `jsbtrace.SkipIfAbsent` is gone.
- Positive: CI and the local harness check a capture the same way.
- Negative: the capture files are public. The content scan above is the only review they get before the owner's capture PR.
- Negative: jsb-native `captures/` keeps its history and receives no new files.
- Negative: all seven evidence files that this change rewrites name a `test_blob` that no longer matches its test file, so `trace_pinned` cannot verify them again. Four of them pin older copies that the pinned tests never load. Those copies keep each recorded sha256 matching a committed file. `engine/testdata/jsb-captures/README.md` lists them.

## Lineage

This ADR replaces the hosting decision of ADR-0278. It also reverses ADR-0278's rejection of committing captures to IBL5. ADR-0278 gave two reasons: the capture is derived from the binary, and IBL5 is public. Both are still true, and the owner accepted that exposure on 2026-11-24. ADR-0278's evidence schema stands, apart from the retired `capture_host: jsb-native` value. Its other two rejections also stand: reading the jsb-native working tree, and pointing the check elsewhere through an env var.

## References

- `ibl5/docs/decisions/0266-trace-pinned-golden-clearance.md`
- `ibl5/docs/decisions/0278-jsb-native-hosted-trace-captures.md`
- `tools/postplan-harness/harness/trace_pinned.py`
- `tools/postplan-harness/tests/test_trace_pinned.py`
- `engine/internal/jsbtrace/jsbtrace.go`
- `engine/docs/jsb-trace-capture.md`
- `engine/testdata/jsb-captures/README.md`
