"""Bounded fixer for arming condition (20): draft a reason for each placeholder, filler or too-short
`## Removed guards` bullet, and write it only when an Opus checker answers `safe` for that key.

Pure apart from the LLM calls: it takes the body, the detector rows and the diff, and returns a
result. It never calls `gh`, never commits, and never writes a file; the runner applies the body.

Fail-closed everywhere. A writer that declines, a checker that answers `unsafe`/`unsure` or for a
different key, an LLM error or timeout, and an off-schema reply (the validators never unwrap an
envelope) all keep the placeholder, so condition (20) keeps holding. A written reason carries
`removed_guards.HARNESS_MARKER`, so the reviewer sees which reasons the harness wrote, and a
substantive reason is never rewritten (`removed_guards.set_reason`).
"""
from __future__ import annotations

import time

from . import llm_calls, removed_guards

ENV_FLAG = "POSTPLAN_REMOVED_GUARD_FIX"
PURPOSE_WRITE = "removed-guard-reason-write"
PURPOSE_CHECK = "removed-guard-reason-check"
WRITER_MODEL = "sonnet"
CHECKER_MODEL = "opus"
MAX_ITEMS = 6
DEADLINE_S = 600
DIFF_CHARS = 12000
WRITE_KEYS = frozenset({"key", "reason"})
CHECK_KEYS = frozenset({"key", "verdict", "why"})
CHECK_VERDICTS = ("safe", "unsafe", "unsure")
FILLABLE_PREFIXES = ("removed-guards:UNFILLED:", "removed-guards:UNJUSTIFIED:")


def enabled(env) -> bool:
    """On unless the env flag is the literal `0`; unset and empty both mean enabled."""
    return env.get("POSTPLAN_REMOVED_GUARD_FIX", "1") != "0"


def validate_write(obj) -> dict:
    """The writer reply: exactly `{"key": str, "reason": str}`. No wrapper is unwrapped."""
    if not isinstance(obj, dict) or set(obj) != WRITE_KEYS:
        raise ValueError(f"writer reply must have exactly the keys {sorted(WRITE_KEYS)}")
    if not isinstance(obj["key"], str) or not isinstance(obj["reason"], str):
        raise ValueError("writer reply key and reason must be strings")
    return obj


def validate_check(obj) -> dict:
    """The checker reply: exactly `{"key": str, "verdict": safe|unsafe|unsure, "why": str}`."""
    if not isinstance(obj, dict) or set(obj) != CHECK_KEYS:
        raise ValueError(f"checker reply must have exactly the keys {sorted(CHECK_KEYS)}")
    if obj["verdict"] not in CHECK_VERDICTS:
        raise ValueError(f"checker verdict must be one of {CHECK_VERDICTS}")
    if not isinstance(obj["key"], str) or not isinstance(obj["why"], str):
        raise ValueError("checker reply key and why must be strings")
    return obj


def checker_approves(check: dict, key: str) -> bool:
    return check.get("verdict") == "safe" and check.get("key") == key


def _diff_section(diff: str, path: str) -> str:
    """The `diff --git` section whose a/ or b/ path is `path`, or "" when there is none."""
    diff = diff or ""
    starts = [m.start() for m in llm_calls._DIFF_SECTION_RE.finditer(diff)]
    for k, start in enumerate(starts):
        end = starts[k + 1] if k + 1 < len(starts) else len(diff)
        section = diff[start:end]
        m = llm_calls._DIFF_PATHS_RE.match(section)
        if m and path in (m.group(1), m.group(2)):
            return section
    return ""


def run(*, body, rows, diff, llm, log, clock=time.monotonic) -> dict:
    """Fill each fillable bullet whose reason both models agree on. Returns
    `{"status": "changed"|"no-change", "body", "filled": [key], "kept": [(key, why)]}`."""
    holds = removed_guards.classify_body(body, [r[0] for r in rows])
    targets = [h.split(":", 2)[2] for h in holds if h.startswith(FILLABLE_PREFIXES)]
    filled: list[str] = []
    kept: list[tuple[str, str]] = []
    if not targets:
        return {"status": "no-change", "body": body, "filled": filled, "kept": kept}
    by_key: dict[str, tuple] = {}
    for r in rows:
        by_key.setdefault(r[0], tuple(r))
    start = clock()
    capped = targets[:MAX_ITEMS]
    for i, key in enumerate(capped):
        if clock() - start > DEADLINE_S:
            kept.extend((k, "deadline") for k in capped[i:])
            break
        row = by_key.get(key)
        if row is None:
            kept.append((key, "no-row"))
            continue
        _, path, kind, snippet = row[:4]
        hunk = _diff_section(diff, path)
        if not hunk:
            kept.append((key, "no-diff"))
            continue
        if len(hunk) > DIFF_CHARS:
            kept.append((key, "diff-too-large"))
            continue
        try:
            proposal = llm.call(PURPOSE_WRITE, WRITER_MODEL,
                                llm_calls.removed_guard_writer_prompt(key, kind, path, snippet, hunk),
                                validate_write)
            if proposal["key"] != key:
                kept.append((key, "writer-declined"))
                continue
            if not removed_guards.is_substantive(proposal["reason"]):
                kept.append((key, "writer-declined"))
                continue
            check = llm.call(PURPOSE_CHECK, CHECKER_MODEL,
                             llm_calls.removed_guard_checker_prompt(key, kind, path, snippet, hunk,
                                                                    reason=proposal["reason"]),
                             validate_check)
            if not checker_approves(check, key):
                kept.append((key, "checker-" + str(check.get("verdict", "invalid"))))
                continue
            marked = removed_guards.mark_reason(proposal["reason"])
        except Exception as exc:  # fail closed: keep placeholder
            kept.append((key, f"llm-error: {exc}"))
            continue
        new_body = removed_guards.set_reason(body, key, marked)
        if new_body == body:
            kept.append((key, "not-rewritable"))
            continue
        body = new_body
        filled.append(key)
    kept.extend((k, "over-cap") for k in targets[MAX_ITEMS:])
    for key, why in kept:
        log(f"removed-guard-fix: kept {key} ({why})")
    for key in filled:
        log(f"removed-guard-fix: filled {key}")
    return {"status": "changed" if filled else "no-change", "body": body,
            "filled": filled, "kept": kept}
