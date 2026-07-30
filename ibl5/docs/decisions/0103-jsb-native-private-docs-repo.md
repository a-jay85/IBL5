---
description: jsb-native/ RE docs get their own private git repo, initialized in place inside IBL5's tree and excluded from IBL5 via .gitignore, with binary-free CI covering doc frontmatter freshness, doc-to-symbol resolution, and a tracked-payload guard; the commercial jumpshot.exe stays untracked and its byte anchors move to a local script.
last_verified: 2026-08-15
---

# ADR-0103: `jsb-native/` is a private git repo, initialized in place

**Status:** Accepted
**Date:** 2026-07-29

## Context

`jsb-native/` holds the reverse-engineering corpus for JSB 5.6.0 — the decompiled C
sources, the annotated `00_MASTER_REFERENCE.md`, ~50 dated RE artifacts, and the
`jumpshot.exe` binary every one of those documents describes. It has never been under
version control. Three problems compound off that.

**No version history for the RE docs.** The directory is excluded from IBL5 via
`.git/info/exclude`, so edits land on disk with no diff, no attribution, and no way to
ask what a document said last week. The concrete cost was measured on 2026-07-29: PR
#1740's stat showed only `jsb-native-backlog.md | 181 +++-`, while concurrent edits to
`00_MASTER_REFERENCE.md` — invisible to that diff — shifted four `path:line` citations in
`~/claude-plans/jsb-j7-param-recovery.md` by 18–38 lines each. An invisible edit surface
silently rots every anchor pointed into it.

**Fresh-clone safety gap.** The exclusion lives in `.git/info/exclude`, which is
machine-local and not cloned. Any fresh IBL5 clone that acquires a `jsb-native/`
directory has no guard at all between `git add -A` and the 1.19 GB `IBL5.log` sitting
inside it.

**No CI coverage.** `bin/check-docs` enforces the frontmatter and dead-reference rules
(`.claude/rules/doc-freshness.md`) over IBL5's in-scope globs. Those globs are
IBL5-specific and none of them reach `jsb-native/`, so the RE corpus — the documentation
most likely to drift, because its subject is a binary nobody can re-read casually — is
the only substantial doc tree in the workspace with no freshness gate.

## Decision

Initialize a private git repo **in place** at `jsb-native/`, nested inside IBL5's
working tree and invisible to IBL5's git via a tracked `.gitignore` entry.

### Payload

The repo uses a **whitelist-form** `.gitignore` (`*` at the top, then `!` re-includes).

| Path | Tracked | Size | Rationale |
|------|---------|------|-----------|
| `jsb_560/decompiled/**` | yes | ~11 MB | 68 decompiled `.c` sources + `00_MASTER_REFERENCE.md` — the RE corpus itself |
| `re-artifacts/*.md`, `re-artifacts/*.txt` | yes | ~572 KB | Dated RE adjudications and leverage reports |
| `.github/**`, `.gitignore`, `*.md`, `tools/*.py` | yes | small | Repo infrastructure and the local anchor-verification script |
| `jsb_560/app/jumpshot.exe` | **no** | 3.5 MB | Third-party commercial executable — see § Commercial binary |
| `jsb_560/app/IBL5.log` | **no** | 1.19 GB | Runtime log; the single largest disclosure/bloat hazard |
| `jsb_560/app/IBL5.bxs` | **no** | 71 MB | Box-score binary; regenerable |
| `jsb_560/app/*.car`, `*.sco`, `*.sal`, `*.rcb`, `*.plr` | **no** | — | League data files; regenerable, and league-private |
| `jsb_560/app/historical.csv` | **no** | — | Regenerable |
| `releases/` | **no** | 123 MB | Build outputs |
| `corpus-matched-98/` | **no** | 0 B on disk | 100 symlinks into `ibl5/backups/*.zip`; committing them would encode absolute host paths |

`jsb_560/app/` is re-included by nothing, so the whole directory is untracked. Tracked
total lands near 12 MB against a 1.5 GB tree.

### Why whitelist, not blacklist

A blacklist false negative means data **is** committed — irreversible once pushed. A
whitelist false negative means data is **not** committed — recoverable with one more `!`
line. At a 100:1 excluded-to-included ratio, only the recoverable failure mode is
acceptable.

### CI

`jsb-native/.github/workflows/ci.yml`, `ubuntu-latest` only, no `upload-artifact` steps.
All three jobs are **binary-free** — every property CI asserts is checkable from tracked
text. `actions/checkout` is SHA-pinned to the same commit IBL5 pins (ADR-0079); jsb-native
has no `pinact` drift-guard of its own, but a floating tag in a repo whose confidentiality
is load-bearing is the wrong default, and there is exactly one external action to pin.

- **`doc-freshness`** — a self-contained python3 checker requiring `description:` and
  `last_verified:` frontmatter, failing on either missing or on `last_verified` older
  than 60 days. Scoped to **living** documents: `*.md` at the root, `jsb_560/*.md`, and
  `jsb_560/decompiled/*.md`. `re-artifacts/` is **exempt** — those are dated, immutable
  point-in-time adjudications, and a staleness clock on a historical record is
  semantically wrong; they would go permanently and uninformatively red.
- **`symbol-resolution`** — every `FUN_00xxxxxx` symbol cited in a living document must
  exist in a tracked `jsb_560/decompiled/*.c` source. This is the dead-reference rule of
  `.claude/rules/doc-freshness.md` applied to RE symbols: it catches a doc citing a
  function that was renamed or never existed. Measured at adoption: 162 citations across
  the two living docs, 0 unresolved, against 3,540 corpus symbols. The job fails if the
  corpus glob yields no symbols at all, so a broken glob cannot make it pass vacuously.
- **`payload-guard`** — reads `git ls-files` and fails if the tracked set contains any
  forbidden path prefix (`jsb_560/app/`, `releases/`, `corpus-matched-98/`), substring
  (`jumpshot`, `IBL5.log`, `historical.csv`), extension (`.exe`, `.dll`, `.log`, `.zip`,
  the JSB league-data extensions, `.DS_Store`), or symlink. Like the other two it fails
  on an empty file list rather than passing vacuously.

  This is the one gate whose failure mode is **irreversible**, so it does not get to live
  only as a sentence in this ADR. The whitelist's `!jsb_560/decompiled/**` re-include is
  recursive: a future RE session that drops a binary, a log, or a copy of `jumpshot.exe`
  under that directory would have it tracked silently. `payload-guard` re-runs the
  pre-push verification on every push and every PR, so the guarantee does not rest on a
  human remembering to re-run a shell block.

### The RE anchor check is local, not CI

Because `jumpshot.exe` is untracked, CI cannot verify it — and a committed byte fixture
would be theater, since hardcoded expected bytes compared against a fixture also in the
repo prove only that two committed constants agree. The check therefore ships as
`tools/verify-anchors.py`, run by hand against a local copy of the binary:

```bash
python3 tools/verify-anchors.py jsb_560/app/jumpshot.exe
```

It asserts SHA256 `ec3f305c…57c5288` and size 3,694,592 bytes, then three byte-level
file-offset assertions covering the instructions the master reference's central claims
cite (`fldl leagueSTL48` at `0xd7a43`, `fmull 5.0` at `0xd7a49`, `calll 0x4e2fc0` at
`0xd7a5d`). File offsets are VA − 0x400000; `.text` has VirtualRVA == FileOffset ==
0x1000, verified from the PE header.

What the SHA buys is detection of a swap, patch, or wrong build. The three byte
assertions are redundant while the SHA matches; their real job is **localization** — when
the SHA is deliberately bumped to a new build, they name which RE claims broke instead of
reporting only "binary changed."

**Byte-level, not disassembler-driven:** Apple LLVM `objdump` emits wrong x87 mnemonics
for this binary, so a mnemonic-matching check would encode the disassembler's bug. Byte
assertions are simpler, portable, and strictly stronger — they cannot be confused by a
decoding table.

**Self-contained checker, not an extension of `bin/check-docs`:** `bin/check-docs` has
hardcoded IBL5-specific `IN_SCOPE_GLOBS` and no `--root` flag. A genuine root override
would also have to make the globs configurable — a rewrite of its scoping model, not a
minimal extension. The `meta-tooling-bar` "no host to extend" condition applies. The cost
is two frontmatter implementations that can drift; it is bounded by keeping the
jsb-native one to ~50 lines of frontmatter-only checking, and by the fact that coupling
jsb-native's CI to IBL5's default branch would be the worse dependency.

### Commercial binary

`jumpshot.exe` is a **third-party commercial executable and is not tracked.** Uploading
it — even to a private repo — would be redistributing someone else's commercial product
onto a third party's servers, and the repo does not need it: every RE claim is anchored
by offset and symbol, both of which are text.

Private visibility remains **load-bearing, not incidental**, because the tracked content
is still derived from that binary. `jsb_560/decompiled/*.c` is decompiler output — a
derivative work of the commercial executable, and substantially a reconstruction of it.
Excluding the binary lowers the exposure; it does not remove it. **No downstream decision
may change this repository to public visibility, and no downstream decision may add the
binary — or any other build artifact from `jsb_560/app/` — to the tracked payload.**

Creation uses `gh repo create --private`, private from the first push, and a human
authorizes that push after reading a pre-push verification gate that asserts
`jsb_560/app/` contributes zero tracked files. That one-time human reading is backed
thereafter by the `payload-guard` CI job, which re-asserts the same property on every
push — see § CI.

## Alternatives Considered

- **Sibling clone at `~/GitHub/jsb-native/`** — a separate directory outside IBL5's tree.
  Rejected because: every existing citation in `engine/docs/backlog/jsb-native-backlog.md`,
  `engine/.claude/rules/jsb-engine-post-work.md`, `engine/Makefile`, and the accumulated
  `~/claude-plans/*.md` is written as `jsb-native/...` relative to the IBL5 root. A move
  breaks all of them at once for no gain in isolation — git already treats an ignored
  nested directory as fully external, so in-place buys the same separation with zero
  citation churn.
- **Track `jsb-native/` inside IBL5** — no second repo. Rejected because: the tree is
  1.5 GB, contains a commercial binary, and IBL5's own visibility posture would then have
  to account for it.
- **Track `jumpshot.exe`** so CI can run the anchor canary against it directly. Rejected:
  the canary is worth less than the exposure. A private repo is an access-control setting
  on a third party's infrastructure, not a legal posture, and the property the canary
  buys — "the binary has not changed" — is verifiable locally for free by the person who
  holds the binary. Uploading a commercial executable to buy a check that the holder can
  already run is the wrong trade.
- **Commit byte fixtures instead of the binary** — a few hundred bytes around each anchor,
  so CI can still assert something. Rejected as theater: the expected bytes and the
  fixture would both be committed constants, so the job would only prove the repo agrees
  with itself. Nothing links either to the real binary.
- **Blacklist `.gitignore`** — enumerate the excluded extensions. Rejected: see § Why
  whitelist.
- **Extend `bin/check-docs` with `--root`** — one checker for both repos. Rejected: see
  § CI.
- **Backfill frontmatter into all 52 whitelisted `.md` files** so the freshness job can
  cover everything. Rejected because: 48 have no frontmatter at all and the 50
  `re-artifacts/` entries are dated snapshots that would breach the 60-day threshold on a
  rolling basis regardless — the scope carve-out is the correct fix, not a backfill that
  buys a red CI later.

## Consequences

- Positive: RE doc edits become reviewable diffs with authorship and history, which is
  the precondition for trusting any citation into them.
- Positive: the guard against committing 1.19 GB is now a tracked `.gitignore` line in
  IBL5, cloned with the repo, rather than a machine-local `.git/info/exclude` entry. The
  exclude line stays as a harmless duplicate.
- Positive: `symbol-resolution` turns "every function the docs cite actually exists in the
  corpus" from an assumption into a CI assertion, and it needs no binary to do it.
- Positive: the tracked payload contains no third-party executable, so the repo's
  disclosure surface is decompiled text the RE work authored against, nothing shipped.
- Positive: `payload-guard` makes the one irreversible failure mode — a binary or a 1.19 GB
  log entering permanent history — a standing CI assertion rather than a one-time human
  reading of a pre-push checklist.
- Negative: two frontmatter checkers can drift. Bounded by scope (frontmatter only) and
  size (~50 lines).
- Negative: **binary integrity is no longer machine-enforced.** `tools/verify-anchors.py`
  only runs when someone runs it, so a swapped or patched local `jumpshot.exe` can go
  unnoticed until then. Accepted: the alternative is uploading the binary, and the check
  is cheap for whoever holds it. Run it before any RE session that re-derives an offset.
- Negative: `re-artifacts/` gets no freshness coverage. Accepted — it is the correct
  semantics for immutable dated records, not a gap to close later.
- Negative: a nested git repo inside another repo's working tree surprises tooling that
  walks the filesystem rather than asking git. Mitigated by the `.gitignore` entry, which
  makes IBL5's own tooling skip it.

### Interaction with ADR-0046 (worktrees live outside the repo)

Worktrees live at `IBL5-worktrees/<slug>/ibl5/`, outside IBL5's tree. `jsb-native/` is a
gitignored directory in the **main checkout only**, so **no worktree ever contains it** —
`bin/wt-new` cannot copy an ignored path, and there is no 1.5 GB duplication per worktree.
The practical consequence: work on jsb-native content happens in the main checkout, and a
worktree session cannot see or cite `jsb-native/` files on its own filesystem.

### Explicit exception to ADR-0062 (all work happens in a worktree)

**RE editing of `jsb-native/` docs is direct-on-main-branch inside the jsb-native repo.**
ADR-0062 rests on two rationale pillars, and neither reaches here:

1. *The main checkout is the base for `bin/wt-new` and runs main-stack Docker/DB tooling,
   so a dirty main checkout breaks worktree creation and local testing.* jsb-native has no
   Docker stack, no database, and no worktree tooling of its own.
2. *Concurrent Claude instances on one branch bypass CI and collide.* jsb-native is a
   single-author docs repo with no deploy pipeline; its CI runs on push to `main`, which
   is exactly where the work lands.

Adding a worktree ceremony to a single-author docs repo would buy nothing and would break
the in-place citation stability that motivated the in-place decision. This exception is
scoped strictly to the `jsb-native` repository — ADR-0062 continues to govern all IBL5
work without change.

### What CI checks

- Every living `.md` (root, `jsb_560/`, `jsb_560/decompiled/`) has `description:` and
  `last_verified:` frontmatter.
- No living `.md` has a `last_verified` older than 60 days, or an unparseable date.
- Every `FUN_00xxxxxx` symbol cited in a living `.md` resolves to a tracked
  `jsb_560/decompiled/*.c` source.
- That the decompiled corpus is present at all — an empty symbol set fails the job.
- That the tracked set contains no forbidden path, extension, or symlink — in particular
  that `jsb_560/app/` and anything named `jumpshot` contribute zero tracked files.

### What CI does not check

- **The binary.** `jumpshot.exe` is untracked, so SHA256 integrity and the three byte
  anchors are unverifiable in CI by construction. That is `tools/verify-anchors.py`, run
  locally — see § The RE anchor check is local, not CI.
- **Struct-offset citations** (`+0xDD0`, `CEngine +0x68A8`) — 441 distinct ones appear in
  the master reference and nothing in tracked text can confirm them; they are properties
  of the binary's memory layout. Only `FUN_` symbols are corpus-resolvable.
- **Anything in `re-artifacts/`** — deliberately exempt (dated immutable records).
- **IBL5's `RETIRED_FIGURES` patterns** — that table lives in `bin/check-docs` on IBL5's
  default branch; replicating it would recreate the cross-repo coupling this ADR rejects.
- **IBL5's dead-reference rule** — the repo-path tokens `bin/check-docs` resolves are
  IBL5 paths, meaningless from jsb-native's root.
- **Whether an RE claim is true** — `symbol-resolution` proves a cited function exists,
  and the local anchor script proves the cited bytes are where the docs say. Neither can
  verify the interpretation of those bytes.
- **`~/claude-plans/*.md` content** — plans live outside every repo and are not
  version-controlled anywhere.
- **The excluded 1.5 GB itself** — CI only ever sees what was committed, so it cannot
  audit the untracked tree. What `payload-guard` does check is the complement: that
  nothing from the hazardous set *entered* the tracked payload. Keeping the 1.5 GB out in
  the first place is still the whitelist `.gitignore`'s job.

## References

- `ibl5/docs/decisions/0046-worktrees-outside-repo.md` — worktree placement
- `ibl5/docs/decisions/0062-all-work-in-worktrees.md` — the rule this ADR excepts
- `.claude/rules/doc-freshness.md` — the IBL5 frontmatter rule this mirrors
- `engine/.claude/rules/jsb-engine-post-work.md` — content-anchored citation norm
- `engine/docs/backlog/jsb-native-backlog.md` — the JSB RE backlog
