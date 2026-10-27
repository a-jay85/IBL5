"""Phase 5.5 delta re-review.

When the branch diff moved since the last FULL plan-intent review, the pinned Opus
reviewer can read only the hunks that moved, the prior findings, and the plan sections
those hunks touch. This module owns every decision on that path: the anchor record, the
fail-closed eligibility predicate, the delta packet and prompt, and the bounded spawn.

Fail-closed is the invariant. Every exception, parse miss, stale record, moved tree or
threshold breach declines with a logged reason and the runner runs the unchanged full
reviewer. No branch skips review, and no delta verdict is cached or carried forward.

The module imports nothing from `runner.py`.
"""
from __future__ import annotations

import json
import os
import re
import types

from . import fidelity, reviewcache, statefile
from .state import HarnessError

SCHEMA_VERSION = 1
RECORD_SUFFIX = ".fidelity-delta.json"
MAX_DELTA_LINES = 200          # measured knee: 200 -> 300 adds 5 of 311 pairs
MAX_DELTA_FILES = 8
MAX_DELTA_RATIO = 0.5          # delta changed-lines / full changed-lines
MAX_CONSECUTIVE_DELTAS = 2     # third moved run after a full is always full
MAX_FINDINGS_BYTES = 20_000
GENERATED_EXEMPT = ("ibl5/phpstan-baseline.neon", "ibl5/bun.lock", "ibl5/composer.lock")
ANCHOR_VERDICTS = ("READY", "READY WITH NOTES", "NOT READY")
DECLINE_REASONS = (
    "not-live", "no-plan-hash", "no-diff-id", "no-state-dir", "no-record",
    "pr-mismatch", "version-changed", "plan-changed", "anchor-verdict-unusable",
    "anchor-ids-missing", "sticky-missing", "sticky-mismatch", "sticky-plan-mismatch",
    "delta-cap", "current-ids-missing", "no-delta", "scope-changed", "gate-surface",
    "unplanned-path", "too-many-files", "too-large", "delta-ratio", "no-plan-sections",
    "plan-unreadable", "packet-failed", "spawn-failed", "verdict-unparsed",
    "verdict-unwritable")

PURPOSE = "plan-fidelity-delta-review"
PACKET_NAME = "fidelity-packet-delta"
DELTA_ANCHOR_RE = re.compile(r"^DELTA-ANCHOR:[ \t]*(\S+)[ \t]*$", re.M)
ANCHOR_BLOCKING_RE = re.compile(r"^.*\[BLOCKING\]", re.M)
RESTATEMENT_RE = re.compile(r"\b(?:UN)?RESOLVED\b")

_HEX40 = re.compile(r"^[0-9a-f]{40}$")
_HEX64 = re.compile(r"^[0-9a-f]{64}$")
_TOP_KEYS = ("slug", "pr_number", "version", "plan_hash", "anchor", "deltas")
_ANCHOR_KEYS = ("run_id", "verdict", "diff_id", "per_file_ids", "reviewed_tree",
                "findings", "full_lines")
_SECTION_RE = re.compile(r"^## (.*)$", re.M)
CRITICAL_FILES_TITLE = "Critical Files"


# --- record schema ------------------------------------------------------------------

def _is_int(v) -> bool:
    return isinstance(v, int) and not isinstance(v, bool)


def _is_hex(v, pattern) -> bool:
    return isinstance(v, str) and bool(pattern.match(v))


def _valid_anchor(a) -> bool:
    if not isinstance(a, dict) or any(k not in a for k in _ANCHOR_KEYS):
        return False
    ids = a["per_file_ids"]
    return (isinstance(a["run_id"], str) and bool(a["run_id"])
            and isinstance(a["verdict"], str)
            and _is_hex(a["diff_id"], _HEX40)
            and isinstance(ids, dict) and bool(ids)
            and all(isinstance(p, str) and _is_hex(i, _HEX40) for p, i in ids.items())
            and _is_hex(a["reviewed_tree"], _HEX40)
            and isinstance(a["findings"], str)
            and len(a["findings"].encode("utf-8")) <= MAX_FINDINGS_BYTES
            and _is_int(a["full_lines"]))


def _valid_delta(d) -> bool:
    return (isinstance(d, dict)
            and isinstance(d.get("run_id"), str) and bool(d["run_id"])
            and isinstance(d.get("verdict"), str)
            and _is_hex(d.get("diff_id"), _HEX40)
            and isinstance(d.get("delta_paths"), list)
            and all(isinstance(p, str) for p in d["delta_paths"])
            and _is_hex(d.get("reviewed_tree"), _HEX40))


def _valid_doc(doc) -> bool:
    return (isinstance(doc, dict) and doc.get("schema_version") == SCHEMA_VERSION
            and all(k in doc for k in _TOP_KEYS)
            and isinstance(doc["slug"], str)
            and _is_int(doc["pr_number"])
            and isinstance(doc["version"], str) and bool(doc["version"])
            and _is_hex(doc["plan_hash"], _HEX64)
            and _valid_anchor(doc["anchor"])
            and isinstance(doc["deltas"], list)
            and all(_valid_delta(d) for d in doc["deltas"]))


def record_path(state_dir: str, slug: str) -> str:
    return os.path.join(state_dir, statefile.safe_slug(slug) + RECORD_SUFFIX)


def load_record(state_dir, slug):
    """The validated record, or None. Garbled is None; there is no partial load."""
    try:
        with open(record_path(state_dir, slug)) as fh:
            doc = json.load(fh)
    except (OSError, ValueError):
        return None
    return doc if _valid_doc(doc) else None


def save_record(state_dir, slug, doc) -> bool:
    """Atomic write (tmp + os.replace). False on any OSError; never raises."""
    path = record_path(state_dir, slug)
    tmp = path + ".tmp"
    try:
        os.makedirs(state_dir, exist_ok=True)
        with open(tmp, "w") as fh:
            json.dump(doc, fh, indent=1, sort_keys=True)
        os.replace(tmp, path)
    except OSError:
        try:
            os.remove(tmp)
        except OSError:
            pass
        return False
    return True


def clear_record(state_dir, slug) -> None:
    try:
        os.remove(record_path(state_dir, slug))
    except OSError:
        pass


def build_anchor_record(*, slug, pr_number, run_id, version, plan_hash, verdict, diff_id,
                        per_file_ids, reviewed_tree, findings, full_lines):
    """A fresh anchor record, or None when any field fails `load_record`'s validation."""
    doc = {"schema_version": SCHEMA_VERSION, "slug": slug, "pr_number": pr_number,
           "version": version, "plan_hash": plan_hash,
           "anchor": {"run_id": run_id, "verdict": verdict, "diff_id": diff_id,
                      "per_file_ids": dict(per_file_ids or {}),
                      "reviewed_tree": reviewed_tree, "findings": findings,
                      "full_lines": full_lines},
           "deltas": []}
    return doc if _valid_doc(doc) else None


def append_delta(record, *, run_id, verdict, diff_id, delta_paths, reviewed_tree) -> dict:
    """A new record with one more delta entry; the anchor is untouched."""
    entry = {"run_id": run_id, "verdict": verdict, "diff_id": diff_id,
             "delta_paths": list(delta_paths), "reviewed_tree": reviewed_tree}
    return {**record, "deltas": list(record["deltas"]) + [entry]}


# --- pure helpers -------------------------------------------------------------------

def changed_lines(diff) -> int:
    """Count `+`/`-` lines inside hunks. File headers (`--- a/x`, `+++ b/x`) only appear
    between a `diff ` line and the file's first `@@`, so a removed `-- foo` or `---` content
    line inside a hunk still counts."""
    n = 0
    in_hunk = False
    for line in (diff or "").splitlines():
        if line.startswith("diff "):
            in_hunk = False
        elif line.startswith("@@"):
            in_hunk = True
        elif in_hunk and line.startswith(("+", "-")):
            n += 1
    return n


def plan_sections(plan_text: str) -> list[tuple[str, str]]:
    """[(title, text)] split on `^## `. The preamble keeps title ""; each section's text
    starts at its `## ` heading line."""
    plan_text = plan_text or ""
    marks = list(_SECTION_RE.finditer(plan_text))
    if not marks:
        return [("", plan_text)]
    out = [("", plan_text[:marks[0].start()])]
    for i, m in enumerate(marks):
        end = marks[i + 1].start() if i + 1 < len(marks) else len(plan_text)
        out.append((m.group(1).strip(), plan_text[m.start():end]))
    return out


def sections_touching(plan_text: str, paths) -> list[str]:
    """Titles of sections naming any path (full string or basename). `## Critical Files`
    is always appended when present: the reviewer reads scope from it."""
    paths = list(paths)
    needles = {n for p in paths for n in (p, os.path.basename(p)) if n}
    titles = []
    for title, text in plan_sections(plan_text):
        if title and any(n in text for n in needles):
            titles.append(title)
    for title, _ in plan_sections(plan_text):
        if title == CRITICAL_FILES_TITLE and title not in titles:
            titles.append(title)
    return titles


def unplanned_paths(plan_text: str, paths) -> list[str]:
    """Paths the plan never names (full path AND basename absent). Generated files exempt."""
    plan_text = plan_text or ""
    return [p for p in paths
            if p not in GENERATED_EXEMPT
            and p not in plan_text and os.path.basename(p) not in plan_text]


def gate_paths(paths) -> list[str]:
    return list(fidelity.denied_local_gate_edits(paths))


# --- eligibility --------------------------------------------------------------------

def eligibility(*, live, state_dir, record, pr_number, version, plan_hash, diff_id,
                current_per_file_ids, sticky_body, plan_text, current_diff):
    """(ctx, None) when a delta review is allowed, else (None, reason).

    Arms run in `DECLINE_REASONS` order through `no-plan-sections`.
    """
    if not live:
        return None, "not-live"
    if not _is_hex(plan_hash, _HEX64):
        return None, "no-plan-hash"
    if not _is_hex(diff_id, _HEX40):
        return None, "no-diff-id"
    if not state_dir:
        return None, "no-state-dir"
    if record is None:
        return None, "no-record"
    anchor = record["anchor"]
    try:
        pr_ok = int(record["pr_number"]) == int(pr_number)
    except (TypeError, ValueError):
        pr_ok = False
    if not pr_ok:
        return None, "pr-mismatch"
    if not version or record["version"] != version:
        return None, "version-changed"
    if record["plan_hash"] != plan_hash:
        return None, "plan-changed"
    if anchor["verdict"] not in ANCHOR_VERDICTS:
        return None, "anchor-verdict-unusable"
    if not anchor["per_file_ids"]:
        return None, "anchor-ids-missing"
    if not sticky_body:
        return None, "sticky-missing"
    last_diff = record["deltas"][-1]["diff_id"] if record["deltas"] else anchor["diff_id"]
    m = fidelity.STICKY_REVIEWED_DIFF_RE.search(sticky_body)
    if m is None or m.group(1) != last_diff:
        return None, "sticky-mismatch"
    m = fidelity.STICKY_PLAN_HASH_RE.search(sticky_body)
    if m is None or m.group(1) != plan_hash:
        return None, "sticky-plan-mismatch"
    if len(record["deltas"]) >= MAX_CONSECUTIVE_DELTAS:
        return None, "delta-cap"
    if not current_per_file_ids:
        return None, "current-ids-missing"
    delta = reviewcache.file_delta(anchor["per_file_ids"], current_per_file_ids)
    if not delta:
        return None, "no-delta"
    if any(p not in anchor["per_file_ids"] or p not in current_per_file_ids for p in delta):
        return None, "scope-changed"
    if gate_paths(delta):
        return None, "gate-surface"
    if unplanned_paths(plan_text, delta):
        return None, "unplanned-path"
    if len(delta) > MAX_DELTA_FILES:
        return None, "too-many-files"
    delta_diff = reviewcache.subset_diff(current_diff, delta)
    delta_lines = changed_lines(delta_diff)
    full_lines = changed_lines(current_diff)
    if delta_lines > MAX_DELTA_LINES:
        return None, "too-large"
    if full_lines == 0 or delta_lines > MAX_DELTA_RATIO * full_lines:
        return None, "delta-ratio"
    titles = sections_touching(plan_text, delta)
    if not titles:
        return None, "no-plan-sections"
    return {"anchor": anchor, "delta_paths": sorted(delta), "delta_diff": delta_diff,
            "delta_lines": delta_lines, "full_lines": full_lines, "sections": titles,
            "deltas_so_far": len(record["deltas"])}, None


# --- packet, prompt, spawn ----------------------------------------------------------

def plan_excerpt(plan_text: str, titles) -> str:
    """Preamble, the named sections in document order, then an omitted-sections list."""
    wanted = set(titles)
    parts, omitted = [], []
    for title, text in plan_sections(plan_text):
        if not title:
            parts.append(text)
        elif title in wanted:
            parts.append(text if text.endswith("\n") else text + "\n")
        else:
            omitted.append(title)
    block = "## Omitted sections (delta review)\n\n" + (
        "".join(f"- {t}\n" for t in omitted) if omitted else "(none)\n")
    pre = "".join(parts)
    if pre and not pre.endswith("\n"):
        pre += "\n"
    return pre + block


def _pointer_prompt(packet_dir: str, full_packet_dir: str, pr_number, anchor_run_id: str) -> str:
    return (
        f"Perform a DELTA plan-intent fidelity review (sections 6b-6e of procedure.md) "
        f"for PR #{pr_number}.\n\n"
        "Every input is already on disk. Read these files:\n"
        f"  - {os.path.join(packet_dir, 'procedure.md')}  — the 6b-6e procedure; follow it exactly\n"
        f"  - {os.path.join(packet_dir, 'plan.md')}       — an excerpt of the plan sections the delta touches\n"
        f"  - {os.path.join(packet_dir, 'plan-index.txt')} — `bin/plan-index` output for plan.md\n"
        f"  - {os.path.join(packet_dir, 'diff.patch')}    — ONLY the hunks changed since the last full review\n"
        f"  - {os.path.join(packet_dir, 'pr-body.md')}    — the PR body\n"
        f"  - {os.path.join(packet_dir, 'context.md')}    — run context\n"
        f"  - {os.path.join(packet_dir, 'anchor-findings.md')} — the last full review's findings\n"
        f"  - {os.path.join(packet_dir, 'delta-paths.txt')} — the files in the delta\n"
        f"Full diff for context only: {os.path.join(full_packet_dir, 'diff.patch')}\n\n"
        "Scope contract:\n"
        f"  1. diff.patch holds ONLY the hunks changed since the last full review (run "
        f"{anchor_run_id}); plan.md is an excerpt of the plan sections those hunks touch; "
        "apply checks 6b-6e to the delta hunks against those sections.\n"
        "  2. For every [BLOCKING] bullet in anchor-findings.md, write RESOLVED with the "
        "hunk that resolves it, or UNRESOLVED. Any UNRESOLVED => NOT READY.\n"
        "  3. Any hunk that removes or weakens a planned behavior, un-realises a "
        "Verification Matrix row, contradicts pr-body.md, or introduces a defect => "
        "NOT READY. A delta verdict must never be weaker than the full review would give "
        "for the same hunks; when unsure, write NOT READY.\n"
        f"  4. Write the line `DELTA-ANCHOR: {anchor_run_id}` on its own line inside "
        "section 6c, and end the file with the bare verdict word exactly as the "
        "procedure requires.\n\n"
        "You may also Read/Grep/Glob the worktree you are running in to ground a claim.\n"
    )


def build_delta_packet(out_dir, master_sha, reviewed_tree, plan_text, ctx, pr_body, pr_number,
                       phase4b_ran, *, worktree, review_findings) -> str:
    """Write the delta packet and return its directory."""
    os.makedirs(out_dir, exist_ok=True)
    excerpt_path = os.path.join(out_dir, PACKET_NAME + "-plan.md")
    with open(excerpt_path, "w") as fh:
        fh.write(plan_excerpt(plan_text, ctx["sections"]))
    anchor = ctx["anchor"]
    extra = (f"REVIEW_KIND: delta\n"
             f"DELTA_ANCHOR_RUN: {anchor['run_id']}\n"
             f"ANCHOR_VERDICT: {anchor['verdict']}\n"
             f"ANCHOR_REVIEWED_TREE: {anchor['reviewed_tree']}\n"
             f"DELTA_FILES: {len(ctx['delta_paths'])}\n"
             f"DELTA_LINES: {ctx['delta_lines']} of {ctx['full_lines']}\n"
             f"PRIOR_DELTAS_SINCE_ANCHOR: {ctx['deltas_so_far']}\n"
             f"PLAN_SECTIONS: {' | '.join(ctx['sections'])}\n")
    packet = fidelity.build_packet(
        out_dir, master_sha, reviewed_tree,
        types.SimpleNamespace(found=True, path=excerpt_path), ctx["delta_diff"], pr_body,
        pr_number, phase4b_ran, worktree=worktree, packet_name=PACKET_NAME,
        review_findings=review_findings, extra_context=extra)
    with open(os.path.join(packet, "anchor-findings.md"), "w") as fh:
        fh.write(anchor["findings"])
    with open(os.path.join(packet, "delta-paths.txt"), "w") as fh:
        fh.write("".join(f"{p}\n" for p in ctx["delta_paths"]))
    return packet


def attempt(llm, out_dir, worktree, gh, plan, diff, pr_body, pr_number, master_sha,
            reviewed_tree, live, log, *, state_dir, slug, plan_hash, diff_id, cache_version,
            phase4b_ran, review_findings):
    """Run the delta review, or return None so the runner runs the full one.

    Returns (verdict, "", info) on success. Every decline logs
    `phase5.5 fidelity: delta declined (<reason>)`.
    """
    def _decline(reason):
        log(f"phase5.5 fidelity: delta declined ({reason})")
        return None

    record = load_record(state_dir, slug) if state_dir and slug else None
    try:
        sticky = gh.pr_sticky_body(pr_number)
    except Exception:  # noqa: BLE001 - an unreadable sticky only declines the delta
        sticky = None
    current_ids = reviewcache.per_file_patch_ids(diff)
    plan_text = None
    if getattr(plan, "found", False) and getattr(plan, "path", ""):
        try:
            with open(plan.path, "rb") as fh:
                plan_text = fh.read().decode("utf-8", "replace")
        except OSError:
            plan_text = None
    if plan_text is None:
        return _decline("plan-unreadable")
    ctx, reason = eligibility(
        live=live, state_dir=state_dir, record=record, pr_number=pr_number,
        version=cache_version, plan_hash=plan_hash, diff_id=diff_id,
        current_per_file_ids=current_ids, sticky_body=sticky,
        plan_text=plan_text, current_diff=diff)
    if reason:
        return _decline(reason)

    try:
        packet = build_delta_packet(
            out_dir, master_sha, reviewed_tree, plan_text, ctx, pr_body, pr_number,
            phase4b_ran, worktree=worktree, review_findings=review_findings)
    except (HarnessError, OSError):
        return _decline("packet-failed")

    anchor = ctx["anchor"]
    try:
        text = llm.call_tooled(
            PURPOSE, "opus",
            _pointer_prompt(packet, os.path.join(out_dir, "fidelity-packet"), pr_number,
                            anchor["run_id"]),
            cwd=worktree, agent="pr-ready-phase6",
            allowed_tools=fidelity.REVIEW_ALLOWED_TOOLS,
            denied_tools=fidelity.REVIEW_DENIED_TOOLS,
            add_dirs=(packet, os.path.join(out_dir, "fidelity-packet")),
            append_system_prompt=fidelity.OVERRIDE, advisor=False)
    except HarnessError as e:
        return _decline(f"spawn-failed:{e.kind}")

    candidate = os.path.join(packet, "verdict-candidate.md")
    body = text if text.endswith("\n") else text + "\n"
    try:
        with open(candidate, "w") as fh:
            fh.write(body)
    except OSError:
        return _decline("verdict-unwritable")
    verdict = fidelity.parse_verdict(candidate)
    reviewed_part = text.split(fidelity.DIGEST_CUT, 1)[0]
    echo = DELTA_ANCHOR_RE.search(reviewed_part)
    if verdict is None or echo is None or echo.group(1) != anchor["run_id"]:
        return _decline("verdict-unparsed")
    # Every anchor [BLOCKING] bullet needs a RESOLVED/UNRESOLVED restatement, else the
    # reviewer may have ignored the old blocker and the delta cannot stand in for a full review.
    blocking = len(ANCHOR_BLOCKING_RE.findall(anchor.get("findings") or ""))
    # A NOT READY anchor with no countable blocker is a parse miss, so fail closed.
    if anchor.get("verdict") == "NOT READY" and blocking == 0:
        return _decline("verdict-unparsed")
    if len(RESTATEMENT_RE.findall(reviewed_part)) < blocking:
        return _decline("verdict-unparsed")
    path = fidelity.verdict_path(pr_number)
    try:
        with open(path, "w") as fh:
            fh.write(body)
        fidelity.record_reviewed_tree(path, reviewed_tree)
    except OSError:
        return _decline("verdict-unwritable")

    k = ctx["deltas_so_far"]
    log(f"phase5.5 fidelity: review: delta (anchor {anchor['run_id']}, "
        f"{len(ctx['delta_paths'])} file(s), {ctx['delta_lines']}/{ctx['full_lines']} lines, "
        f"prior deltas {k})")
    return verdict, "", {"review_kind": "delta", "delta_anchor": anchor["run_id"],
                         "delta_paths": ctx["delta_paths"], "delta_lines": ctx["delta_lines"],
                         "delta_full_lines": ctx["full_lines"], "delta_index": k + 1}


# --- recording ----------------------------------------------------------------------

def record_outcome(state_dir, slug, fid, *, pr_number, run_id, version, diff, final_tree, log):
    """Write, extend or clear the anchor record at the end of `_run_fidelity`.

    Never raises: any error clears the record, so a stale anchor cannot outlive a fault.
    """
    try:
        _record_outcome(state_dir, slug, fid, pr_number=pr_number, run_id=run_id,
                        version=version, diff=diff, final_tree=final_tree, log=log)
    except Exception as exc:  # noqa: BLE001 - a record write never fails a run
        clear_record(state_dir, slug)
        log(f"phase5.5 fidelity: delta record cleared (error: {exc!r})")


def _record_outcome(state_dir, slug, fid, *, pr_number, run_id, version, diff, final_tree, log):
    if not state_dir or not slug:
        log("phase5.5 fidelity: delta record: skipped (no state dir)")
        return
    if fid.get("carried_forward"):
        log("phase5.5 fidelity: delta record: kept (carried forward)")
        return

    def _clear(why):
        clear_record(state_dir, slug)
        log(f"phase5.5 fidelity: delta record: cleared ({why})")

    checks = (
        ("verdict-unusable", fid.get("verdict_1") in ANCHOR_VERDICTS),
        ("review-error", fid.get("error_kind") is None),
        ("remediated", fid.get("remediation_sha") is None),
        ("rounds-completed", fid.get("rounds_completed", 0) == 0),
        ("round-remediated", not any(r.get("remediation_sha")
                                     for r in (fid.get("rounds") or []))),
        ("tree-moved", bool(final_tree) and final_tree == fid.get("reviewed_tree")),
        ("bad-version", isinstance(version, str) and bool(version)),
        ("bad-plan-hash", _is_hex(fid.get("plan_hash"), _HEX64)),
        ("bad-diff-id", _is_hex(fid.get("diff_id"), _HEX40)),
    )
    for name, ok in checks:
        if not ok:
            return _clear(name)
    per_file_ids = reviewcache.per_file_patch_ids(diff)
    if not per_file_ids:
        return _clear("ids-missing")
    if fid.get("review_kind") == "delta":
        record = load_record(state_dir, slug)
        if record is None:
            return _clear("record-vanished")
        doc = append_delta(record, run_id=run_id, verdict=fid["verdict_1"],
                           diff_id=fid["diff_id"], delta_paths=fid.get("delta_paths") or [],
                           reviewed_tree=fid["reviewed_tree"])
        if not _valid_doc(doc) or not save_record(state_dir, slug, doc):
            return _clear("delta-unwritable")
        log(f"phase5.5 fidelity: delta record: wrote delta {len(doc['deltas'])} "
            f"(anchor {doc['anchor']['run_id']})")
        return
    findings = fidelity.findings_excerpt(fidelity.verdict_path(pr_number), True)
    doc = build_anchor_record(
        slug=slug, pr_number=pr_number, run_id=run_id, version=version,
        plan_hash=fid["plan_hash"], verdict=fid["verdict_1"], diff_id=fid["diff_id"],
        per_file_ids=per_file_ids, reviewed_tree=fid["reviewed_tree"], findings=findings,
        full_lines=changed_lines(diff))
    if doc is None:
        return _clear("anchor-invalid")
    if not save_record(state_dir, slug, doc):
        return _clear("anchor-unwritable")
    log(f"phase5.5 fidelity: delta record: wrote anchor {run_id} ({fid['verdict_1']})")
