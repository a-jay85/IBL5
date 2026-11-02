"""Fixing PR-body notes on a READY WITH NOTES verdict: pure helpers.

Detector, protected-region edit applier, fix-call validator/normalizer, prompt builder.
Pure helpers, plus `fix_body_notes`, the one bounded orchestrator (llm and gh arrive as
parameters). This module must never import `runner`.
"""
from __future__ import annotations

import functools
import json
import os
import re

from .state import HarnessError
from . import fidelity
from . import llm_calls
from . import schemas

# Same literal as runner.BODY_ONLY_SHA; the core module must not import the runner.
BODY_ONLY_SHA = "body-only"

# Marker literals copied from runner._body_signature (via harness.classify).
MERGE_DIGEST_BEGIN = "<!-- merge-digest:begin -->"
MERGE_DIGEST_END = "<!-- merge-digest:end -->"
FILES_CHANGED_BEGIN = "<!-- files-changed:begin -->"
FILES_CHANGED_END = "<!-- files-changed:end -->"
MANUAL_CONFIRMATION_MARKER = "<!-- manual-confirmation -->"
MANUAL_TESTING_HEADING = "## Manual Testing"
DECISION_PREFIX = "**Decision:**"
MANUAL_SENTINEL = "No manual testing needed"

BODY_NOTE_RE = re.compile(r"\bPR(?:'s)?[ -]body\b", re.I)


def body_citing(note_titles: list[str]) -> bool:
    """True when any [NOTE] title names the PR body. A gate only: the fix call's
    per-note disposition is the final classifier."""
    return any(BODY_NOTE_RE.search(title) for title in note_titles)


_H2_RE = re.compile(r"^## ", re.M)
_MANUAL_HEADING_RE = re.compile(r"^" + re.escape(MANUAL_TESTING_HEADING) + r"\b.*$", re.M)
_DECISION_LINE_RE = re.compile(r"^" + re.escape(DECISION_PREFIX) + r".*$", re.M)
_SENTINEL_LINE_RE = re.compile(r"^.*" + re.escape(MANUAL_SENTINEL) + r".*$", re.M)

_FORBIDDEN_TOKENS = ("<!--", "-->", "```", "~~~", DECISION_PREFIX, MANUAL_SENTINEL)
_CHECKBOX_RE = re.compile(r"\[[ xX]\]")


def _block_spans(body: str, begin: str, end: str) -> list[tuple[int, int]]:
    """Spans of each balanced begin..end block. An unbalanced marker is left alone,
    the same bounds check runner._body_signature uses."""
    spans = []
    pos = 0
    while True:
        b = body.find(begin, pos)
        if b == -1:
            return spans
        e = body.find(end, b + len(begin))
        if e == -1:
            return spans
        spans.append((b, e + len(end)))
        pos = e + len(end)


def _manual_section_spans(body: str) -> list[tuple[int, int]]:
    """The `## Manual Testing` heading line through the line before the next `## `."""
    spans = []
    for m in _MANUAL_HEADING_RE.finditer(body):
        nxt = _H2_RE.search(body, m.end())
        spans.append((m.start(), nxt.start() if nxt else len(body)))
    return spans


def _confirmation_spans(body: str, section_spans: list[tuple[int, int]]
                        ) -> list[tuple[int, int]]:
    """From each manual-confirmation marker outside the section to the next `## `."""
    spans = []
    pos = 0
    while True:
        i = body.find(MANUAL_CONFIRMATION_MARKER, pos)
        if i == -1:
            return spans
        pos = i + len(MANUAL_CONFIRMATION_MARKER)
        if any(a <= i < b for a, b in section_spans):
            continue
        nxt = _H2_RE.search(body, pos)
        spans.append((i, nxt.start() if nxt else len(body)))


def protected_spans(body: str) -> list[tuple[int, int]]:
    """Character spans of the body regions the fixer may never touch."""
    spans: list[tuple[int, int]] = []
    spans.extend(_block_spans(body, MERGE_DIGEST_BEGIN, MERGE_DIGEST_END))
    spans.extend(_block_spans(body, FILES_CHANGED_BEGIN, FILES_CHANGED_END))
    section = _manual_section_spans(body)
    spans.extend(section)
    spans.extend(_confirmation_spans(body, section))
    spans.extend(m.span() for m in _DECISION_LINE_RE.finditer(body))
    spans.extend(m.span() for m in _SENTINEL_LINE_RE.finditer(body))
    return spans


def _note_sort_key(note_id: str) -> tuple:
    return tuple(int(p) if p.isdigit() else p for p in re.split(r"(\d+)", note_id))


def _forbidden(text: str) -> bool:
    return any(tok in text for tok in _FORBIDDEN_TOKENS) or bool(_CHECKBOX_RE.search(text))


def apply_edits(body: str, edits: list[dict], fixable_ids: set[str]
                ) -> tuple[str, list[str], dict[str, str]]:
    """(new_body, applied_note_ids, rejected {note_id: reason}).

    One note at a time, in note-id order. A note's edits apply to a scratch copy in list
    order; the first edit that breaks a rule rejects the whole note and the body stays
    exactly as it was before that note.
    """
    by_note: dict[str, list[dict]] = {}
    for edit in edits:
        by_note.setdefault(str(edit.get("note")), []).append(edit)
    applied: list[str] = []
    rejected: dict[str, str] = {}
    for note_id in sorted(by_note, key=_note_sort_key):
        scratch = body
        if note_id not in fixable_ids:
            rejected[note_id] = "not-fixable"
        for edit in by_note[note_id]:
            if note_id in rejected:
                break
            find, replace = edit.get("find"), edit.get("replace")
            if not isinstance(find, str) or not isinstance(replace, str):
                rejected[note_id] = "find-not-unique"
                break
            if not find or scratch.count(find) != 1:
                rejected[note_id] = "find-not-unique"
                break
            start = scratch.index(find)
            end = start + len(find)
            if any(start < b and a < end for a, b in protected_spans(scratch)):
                rejected[note_id] = "protected-region"
                break
            if not replace:
                rejected[note_id] = "empty-replace"
                break
            if _forbidden(find) or _forbidden(replace):
                rejected[note_id] = "forbidden-token"
                break
            scratch = scratch[:start] + replace + scratch[end:]
        if note_id not in rejected:
            body = scratch
            applied.append(note_id)
    return body, applied, rejected


FIX_DISPOSITIONS = ("fixed", "shape-b", "left-for-human", "needs-code", "not-body")
_EDITING_DISPOSITIONS = ("fixed", "shape-b")


def validate_fix(data, *, note_ids: list[str]) -> None:
    """Every expected note id exactly once, closed dispositions, edits only for
    fixed/shape-b notes, and every fixed/shape-b note carrying an edit."""
    if not isinstance(data, dict):
        raise HarnessError("schema", "body-notes fix must be a JSON object")
    notes = data.get("notes")
    if not isinstance(notes, list):
        raise HarnessError("schema", "body-notes fix 'notes' must be a JSON array")
    disposition: dict[str, str] = {}
    ids = []
    for i, item in enumerate(notes):
        if not isinstance(item, dict) or not isinstance(item.get("id"), str):
            raise HarnessError("schema", f"body-notes fix notes[{i}] needs a string id")
        if item.get("disposition") not in FIX_DISPOSITIONS:
            raise HarnessError("schema", f"body-notes fix notes[{i}].disposition "
                                         f"{item.get('disposition')!r} not in {FIX_DISPOSITIONS}")
        ids.append(item["id"])
        disposition[item["id"]] = item["disposition"]
    if sorted(ids) != sorted(note_ids):
        raise HarnessError("schema", f"body-notes fix ids {ids} != {note_ids}")
    edits = data.get("edits", [])
    if not isinstance(edits, list):
        raise HarnessError("schema", "body-notes fix 'edits' must be a JSON array")
    edited = set()
    for i, edit in enumerate(edits):
        if not isinstance(edit, dict):
            raise HarnessError("schema", f"body-notes fix edits[{i}] must be an object")
        if disposition.get(edit.get("note")) not in _EDITING_DISPOSITIONS:
            raise HarnessError("schema", f"body-notes fix edits[{i}] names note "
                                         f"{edit.get('note')!r} that is not fixed or shape-b")
        if not isinstance(edit.get("find"), str) or not isinstance(edit.get("replace"), str):
            raise HarnessError("schema", f"body-notes fix edits[{i}] find and replace "
                                         "must be strings")
        edited.add(edit["note"])
    missing_edits = sorted(n for n, d in disposition.items()
                           if d in _EDITING_DISPOSITIONS and n not in edited)
    if missing_edits:
        raise HarnessError("schema", f"body-notes fix notes {missing_edits} are fixed "
                                     "or shape-b but carry no edit")


def fix_validator(note_ids: list[str]):
    """The `validate` callable handed to llm.call, with the expected ids bound."""
    return functools.partial(validate_fix, note_ids=list(note_ids))


def unwrap_fix_envelope(data):
    """A one-key dict whose value is a dict holding `notes` is unwrapped once.
    Anything else is returned unchanged, so a bare list still fails the validator."""
    if isinstance(data, dict) and len(data) == 1:
        inner = next(iter(data.values()))
        if isinstance(inner, dict) and "notes" in inner:
            return inner
    return data


_FIX_SCHEMA = """{"notes": [{"id": "N1", "disposition": "fixed|shape-b|left-for-human|needs-code|not-body",
            "reason": "..."}],
 "edits": [{"note": "N1", "find": "...", "replace": "..."}]}"""


def fix_prompt(notes: list[tuple[str, str]], excerpt: str, pr_body: str,
               name_status: str, evidence: str) -> str:
    """Prompt for the one call that fixes PR-body notes. `notes` is [("N1", title), ...]
    and `excerpt` is fidelity.findings_excerpt(verdict_path, True)."""
    note_lines = "\n".join(f"- {nid}: {title}" for nid, title in notes)
    return f"""You are fixing the PR-body notes a plan-fidelity reviewer left on a PR that otherwise passed.

NOTES (each has an id):
{note_lines}

REVIEWER FINDINGS (context):
{excerpt}

CURRENT PR BODY:
<<<BODY
{pr_body}
BODY>>>

CHANGED FILES (name-status):
{name_status}

EVIDENCE (the only source of facts you may cite):
{evidence}

For every note choose exactly one disposition:
- fixed: the PR body can be corrected with a text edit, and the evidence supports it.
- shape-b: the PR body lacks something the evidence cannot supply; say plainly what is missing (for example: "The red-half failure output was not captured.").
- left-for-human: the note concerns Manual Testing rows, the **Decision:** line, or the manual-confirmation block.
- needs-code: resolving the note needs a code change, not a body edit.
- not-body: the note is not about the PR body.

Rules:
- Every factual sentence you write is Shape A (cite the diff path or plan line that supports it) or Shape B (say plainly what is missing).
- Never invent output, numbers, or evidence absent from EVIDENCE.
- Manual Testing rows, the **Decision:** line, and the manual-confirmation block are left-for-human.
- A note needing a code change is needs-code.
- Every find must be an exact substring of the PR body that occurs exactly once.
- No HTML comments, no checkboxes, no fenced blocks in find or replace.
- Only fixed and shape-b notes carry edits; each must carry at least one.

Return ONLY JSON, exactly this shape, with one entry in "notes" per note id above:
{_FIX_SCHEMA}
"""


_REMAINING_SUFFIX = {
    "left-for-human": " (left for the human)",
    "needs-code": " (needs a code change)",
    "not-body": " (not a PR-body fix)",
}


def _write_audit(path: str, data) -> None:
    try:
        with open(path, "w") as fh:
            json.dump(data, fh, indent=2)
    except (OSError, TypeError, ValueError):
        pass


def _short(title: str) -> str:
    """First sentence of a note title, without its period, capped at 100 characters."""
    return title.split(". ", 1)[0].rstrip(". ")[:100]


def _noop_log(_msg: str) -> None:
    pass


def fix_body_notes(llm, gh, git, out_dir: str, pr, verdict_path: str, fid: dict,
                   name_status: str, *, signature, log=None) -> None:
    """Mutates fid in place: may set fid["remediation_sha"] = BODY_ONLY_SHA,
    fid["body_notes"], and fid["body_fixed_notes"]. Never touches verdict_1,
    verdict_2, verdict_path, reviewed_tree, reviewed_tree_2, rounds,
    rounds_completed. Never raises HarnessError: both llm.call sites catch it.
    Uses no bare except and no except BaseException, so a UsagePause escapes.

    At most one fix call and one recheck call. No loop, no retry.
    """
    log = log or _noop_log
    # 1. Path guard.
    if (fid.get("verdict_1") != "READY WITH NOTES" or fid.get("rounds")
            or fid.get("remediation_sha")):
        return
    # 2. Gate.
    titles = fidelity.verdict_items(verdict_path)["notes"]
    if not titles or not body_citing(titles):
        log("phase5.5 body-notes: no body-citing notes; 0 fix calls")
        return
    # 3. Snapshot.
    raw_before = gh.pr_body_fresh() or ""
    if not raw_before:
        log("phase5.5 body-notes: body-fetch-failed before fix")
        return
    sig_before = signature(raw_before)
    # 4. Fix call (call 1 of at most 2).
    ids = [f"N{i}" for i in range(1, len(titles) + 1)]
    title_of = dict(zip(ids, titles))
    evidence = llm_calls.diff_sections_for(
        git.diff_vs_base(), fidelity.cited_diff_paths(name_status, titles, []))
    fix_audit = os.path.join(out_dir, "body-notes-fix.json")
    try:
        data = llm.call("plan-fidelity-body-notes-fix", "sonnet",
                        fix_prompt(list(zip(ids, titles)),
                                   fidelity.findings_excerpt(verdict_path, True),
                                   raw_before, name_status, evidence),
                        fix_validator(ids), normalizer=unwrap_fix_envelope)
    except HarnessError as exc:  # fix call
        _write_audit(fix_audit, {"error": exc.kind, "detail": str(exc.detail or "")[:2000]})
        fid["body_notes"] = {"outcome": "fix-call-failed", "fixed": [], "remaining": []}
        log(f"phase5.5 body-notes: fix call failed ({exc.kind})")
        return
    _write_audit(fix_audit, data)
    # 5. Apply.
    disposition = {n["id"]: n["disposition"] for n in data["notes"]}
    fixable = {i for i, d in disposition.items() if d in ("fixed", "shape-b")}
    new_body, applied_ids, rejected = apply_edits(raw_before, data.get("edits", []), fixable)
    applied_set = set(applied_ids)
    applied = [title_of[i] for i in ids if i in applied_set]
    remaining = []
    for i in ids:
        if i in applied_set:
            continue
        if i in rejected:
            remaining.append(f"{_short(title_of[i])} (body fix rejected: {rejected[i]})")
        else:
            remaining.append(_short(title_of[i]) + _REMAINING_SUFFIX.get(
                disposition.get(i), " (body fix rejected: not-applied)"))
    # 6. Nothing applied.
    if not applied:
        fid["body_notes"] = {"outcome": "nothing-applied", "fixed": [],
                             "remaining": remaining}
        log(f"phase5.5 body-notes: nothing applied, {len(remaining)} remaining")
        return
    # 7. Write and read back.
    gh.pr_edit_body(pr, new_body)
    raw_after = gh.pr_body_fresh() or ""
    if not raw_after:
        gh.pr_edit_body(pr, raw_before)
        fid["remediation_sha"] = BODY_ONLY_SHA  # unconfirmed write
        fid["body_notes"] = {
            "outcome": "body-fetch-failed", "fixed": [],
            "remaining": remaining + [_short(t) + " (body fix unconfirmed: body-fetch-failed)"
                                      for t in applied]}
        log("phase5.5 body-notes: body-fetch-failed after write")
        return
    if signature(raw_after) == sig_before:
        fid["body_notes"] = {
            "outcome": "write-not-applied", "fixed": [],
            "remaining": remaining + [_short(t) + " (body fix did not land)" for t in applied]}
        log("phase5.5 body-notes: write-not-applied")
        return
    # 8. Recheck (call 2 of at most 2). Not fidelity.body_recheck: its eligibility
    # declines a non-NOT READY prior verdict and a pass would overwrite the notes.
    findings = [f"[NOTE] {t}" for t in applied]
    fids = [f"F{i}" for i in range(1, len(findings) + 1)]
    hunks = llm_calls.body_hunks(raw_before, raw_after)
    hids = [h["id"] for h in hunks]
    evidence2 = llm_calls.diff_sections_for(
        git.diff_vs_base(), fidelity.cited_diff_paths(name_status, findings, hunks))
    recheck_audit = os.path.join(out_dir, "body-notes-recheck.json")
    reason = None
    try:
        data2 = llm.call("plan-fidelity-body-notes-recheck", "sonnet",
                         llm_calls.pr_body_recheck_prompt(
                             findings, hunks, raw_before, raw_after, name_status, evidence2),
                         schemas.body_recheck_validator(fids, hids),
                         normalizer=schemas.unwrap_body_recheck_envelope)
    except HarnessError as exc:  # recheck call
        _write_audit(recheck_audit,
                     {"error": exc.kind, "detail": str(exc.detail or "")[:2000]})
        reason = f"recheck-{exc.kind}"
    else:
        _write_audit(recheck_audit, data2)
        if not schemas.body_recheck_passed(data2):
            reason = "recheck-unresolved"
    # 9. Pass.
    if reason is None:
        fid["remediation_sha"] = BODY_ONLY_SHA
        fid["body_notes"] = {"outcome": "fixed", "fixed": list(applied),
                             "remaining": remaining}
        fid["body_fixed_notes"] = list(applied)
        log(f"phase5.5 body-notes: {len(applied)} fixed, {len(remaining)} remaining, "
            "recheck passed")
        return
    # 10. Fail: restore the pre-fix body.
    gh.pr_edit_body(pr, raw_before)
    restored = gh.pr_body_fresh() or ""
    if signature(restored) == sig_before:
        fid["body_notes"] = {
            "outcome": "reverted", "fixed": [],
            "remaining": remaining + [f"{_short(t)} (body fix reverted: {reason})" for t in applied]}
        log(f"phase5.5 body-notes: body fix reverted ({reason})")
    else:
        fid["remediation_sha"] = BODY_ONLY_SHA
        fid["body_notes"] = {
            "outcome": "revert-unconfirmed", "fixed": [],
            "remaining": remaining + [_short(t) + " (body fix revert unconfirmed)" for t in applied]}
        log(f"phase5.5 body-notes: body fix revert unconfirmed ({reason})")


_MAX_NAMED = 3


def _names(remaining: list[str]) -> str:
    shown = remaining[:_MAX_NAMED]
    extra = len(remaining) - len(shown)
    return "; ".join(shown) + (f"; +{extra} more" if extra > 0 else "")


def terminal_line(fid: dict) -> str | None:
    """Body-notes terminal row, or None to defer to fidelity.terminal_line."""
    bn = fid.get("body_notes")
    if fid.get("verdict_1") != "READY WITH NOTES" or not bn:
        return None
    fixed, remaining = bn.get("fixed") or [], bn.get("remaining") or []
    if fixed and not remaining:
        return ("READY WITH NOTES — all notes remediated in the PR body; reviewer verdict "
                f"covers tree {fid.get('reviewed_tree') or 'unrecorded'}, "
                "not the post-remediation head")
    if fixed:
        return f"READY WITH NOTES — {_names(remaining)}; remediated the rest in the PR body"
    if remaining:
        return f"READY WITH NOTES — {_names(remaining)}"
    return None
