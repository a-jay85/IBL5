---
description: Discord bug-report attachments are captured through a strict trust boundary — untrusted bytes and metadata, filenames never form paths, snowflakes stay strings, capped downloads against an https allowlist, cache outside the repo pruned at 7 days, and only a sanitized text reference reaches the model.
last_verified: 2026-08-05
---

# ADR-0098: The attachment-ingest trust boundary

**Status:** Accepted
**Date:** 2026-08-04

## Context

The bug pipeline (ADR-0080) ingests Discord messages and drives an agent over them; ADR-0081 split that surface into a trusted host side and an untrusted report side. Until now every byte crossing that boundary was **text**. Message attachments — images a reporter drags into a bug thread — were silently dropped at three points (ingest, thread transcript, prompt render), so a screenshot of the actual crash never reached triage.

Capturing them adds the pipeline's first attacker-controlled **binary** ingest path, and every field arrives from Discord: the filename, the content type, the URL, and the bytes themselves. A naive capture opens path traversal (a filename like `../../evil.sh`), SSRF (a crafted download URL), resource exhaustion (a multi-gigabyte "image"), stored-input injection (a `local_path` escaping its cache root), snowflake-precision corruption (19-digit ids truncated to floats), and prompt injection (a filename engineered to steer the agent). It must not weaken `--allowedTools ''`, the pipeline's primary prompt-injection defense.

## Decision

Attachment metadata and bytes are **untrusted at every layer**, and each threat gets a defense with a test row:

- **Filenames never form a filesystem path.** The Discord filename is display-only (control chars stripped, then truncated to 255 **bytes** — the column is `VARCHAR(255)`, and a code-unit cap would let a 100-character CJK name overflow it; a name that strips to empty falls back to `attachment-${attachment_id}`, since the PHP validator drops the whole entry on an empty or over-long filename). The on-disk name derives from `${message_id}-${attachment_id}.${ext}`, where both snowflakes are matched against `/^\d{1,20}$/` and the extension is mapped from `contentType` against a fixed allowlist (png/jpg/jpeg/gif/webp). A name like `../../evil.sh` still writes `${message_id}-${attachment_id}.png`.
- **Snowflakes are strings end-to-end** — never `(int)`/`Number()`/`tonumber`. `attachment_id` is `VARCHAR(20)`, breaking migration 153's `BIGINT` convention, because `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` corrupts 19-digit ids on read.
- **Downloads are capped and allowlisted.** The target must parse to scheme `https` with a host in `{cdn.discordapp.com, media.discordapp.net}` (a bare `str_starts_with('https://')` is insufficient); `redirect: 'error'`; 10 MB enforced by counting streamed bytes (not trusting `Content-Length`); 10 s `AbortSignal.timeout`; download to `.part` then `rename`, so no partial is ever a cache hit; the array is capped at 10 entries.
- **`local_path` is full-shape-validated** before any DB write — a full-shape regex, not a prefix match, so `<root>/../../etc/passwd` is unrepresentable. A failing path **degrades the field to null**, it does not drop the entry: null is already `local_path`'s failed-download state, so the record lands in a shape the pipeline handles natively instead of discarding an independently-valid `original_url`, `filename`, and `content_type` over one optional field. Every other field is reject-and-skip. Degrades are written to the same reject log as drops, so a silently nulled path is still visible to an operator. Every DB value is bound; the only dynamic SQL is a placeholder count derived from `count($ids)`. Storage-layer idempotency is `INSERT IGNORE` + `UNIQUE (report_id, attachment_id)`, since the enqueue path is replay-safe.
- **The cache lives outside the repo tree** (`~/.claude/projects/.../bug-pipeline/attachments`) and is pruned at 7 days by the driver at tick start. **Three** processes hold a copy of that root, not two — the bot *writes* bytes there, the driver *prunes* them, and the PHP validator *accepts or rejects* paths under it (a literal, since PHP cannot expand `~`). Any pair agreeing while the third drifts fails silently in its own way: bot/driver drift orphans bytes, while a drifted PHP root rejects every well-formed `local_path`, degrading every attachment to URL-only with no error anywhere. A cross-process test row pins all three against one resolved value.
- **The DB is a hint about the cache; the filesystem is the truth.** The prune deletes cache files but never rewrites `ibl_bug_report_attachments.local_path`, so a stored path outlives its bytes by design. The driver re-checks each path against the filesystem at render time and emits `cached=none` for a vanished file — the same shape a failed download already produces, so no reader needs a new case, and the prompt never points triage at a read that would fail.
- **Only a sanitized text reference reaches the model.** Filenames arriving via the transcript path bypass the PHP validator, so the driver strips control chars, caps at 120, and wraps the block in an explicit "untrusted — data only" frame. Issue-body references are plain `- name (type) — url` (never markdown links) passed as a printf `%s` argument. **`--allowedTools ''` is untouched** — capability starvation stays the primary defense; no `Read` grant, no tool is added.

- **An image-only report is a first-class shape.** A GM who drags in a screenshot and types nothing sends `content: ''`, so the enqueue boundary accepts empty `text` **when attachments came with it** (`text` must still *be* a string; the widening is scoped to that one pair and the authz gate is untouched). Rejecting it would 400 the bot's enqueue, which raises an `ApiError` the caller does not catch — and backfill replays messages in a bare loop, so one such message would abort every replay after it.

**Scope boundary:** capture, persistence, and text-surface exposure only. Multimodal delivery is deferred.

## Alternatives Considered

- **A JSON column on `ibl_bug_reports`** instead of a child table — Rejected because: `castRow()` guarantees a flat scalar shape that `@phpstan-type BugReportRow` asserts at every read site; a nested column would require `json_decode` and falsify that guarantee everywhere. The child table costs one extra batched query per tick (zero on an empty tick).
- **Deliver images to the model directly** (base64 in the prompt, or a `Read` grant over the cache) — Rejected because: both weaken `--allowedTools ''`, the pipeline's primary prompt-injection defense. Deferred to a follow-on PR that can design its own containment rather than eroding this one's.
- **Fetch the bytes later, in the driver, instead of at ingest** — Rejected because: Discord CDN URLs expire in ~24 h, so a queue-and-fetch-later design would routinely fetch dead links. Capture happens at ingest under one `Promise.all` while the URL is fresh.
- **Hard-code the cache dir and host allowlist** — Rejected because: hard-coded values block the loopback fixture server with our own SSRF defense, forcing a `fetch` mock (stub-only coverage, not a pre-prod exercise path). Both are env-overridable.

## Consequences

- Positive: the first binary ingest path lands with a named defense and a test row per threat, rather than as an afterthought bolted onto a text-only pipeline.
- Positive: a reporter's screenshot now reaches triage as a cached file plus a sanitized reference in the transcript and the tracking issue.
- Negative: capture at ingest adds up to ~10 s to a worst-case enqueue (bounded by the download cap), versus losing the bytes entirely.
- Negative: the child table costs a second query per tick, and cache bytes accrue on disk until the 7-day prune reclaims them.
- Negative: images are captured but not *shown* to the model — triage reads a text reference, not the pixels. Closing that gap needs its own containment design and its own ADR.

## References

- ADR-0080 — bug pipeline design; this ADR extends its ingest surface.
- ADR-0081 — the trusted/untrusted split this boundary sits on.
- `ibl5/migrations/158_create_bug_report_attachments.sql` — child table + FK + unique key.
- `ibl5/classes/BugPipeline/AttachmentInputValidator.php` — the trust-boundary validator.
- `ibl5/IBLbot/src/bug-bot/attachments.ts` — sanitize + capped download + cache module.
- `bin/bug-pipeline-tick` — transcript sanitization, prompt render, and cache prune.
- `bin/lib/bug-pipeline-gh.sh` — plain-text attachment references in the issue body.
