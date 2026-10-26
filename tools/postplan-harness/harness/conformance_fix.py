"""Phase 5.0-pre fixer: one bounded round that writes plan-promised tests and files.

A pre-verify `conformance.check` can report items an implementation dropped: a matrix
test path (`MISSING:`), a Critical File (`MISSING-FILE:`), a Required Test Method
(`MISSING-METHOD:`) or a matrix assertion (`UNREALISED-ASSERTION:`). This module gives
one Sonnet round, with no Bash and only plan-cited paths writable, the chance to write
them. Mechanical post-checks revert anything that fails; the harness commits what
survives.

The fixer decides nothing. Arming condition (3) is still computed by the final
`conformance.check` after Phase 5 verify, and the done-marker is written only there.
The fixer's own report never clears a condition (ADR: conformance fixer clears only via
the second check).

Kill switch: `POSTPLAN_CONFORMANCE_FIX`. Unset and empty enable; only the exact value
`0` disables, and a disabled fixer returns before any git or LLM call.
"""
from __future__ import annotations

import difflib
import os
import re
import sys
import time
from dataclasses import dataclass, field
from typing import Mapping

from . import fidelity, gatefix
from .conformance import _required_name_present
from .state import HarnessError

ENV_FLAG = "POSTPLAN_CONFORMANCE_FIX"
PURPOSE = "conformance-fix"
COMMIT_MSG = "test: write plan-promised tests reported MISSING by Phase 5.0"

NEVER_FIXABLE_PREFIXES = ("MISSING-PHASE:", "UNMET-CONTRACT:")
TEST_PATH_RE = re.compile(
    r"(?:^|/)(?:tests?/.*(?:Test\.php|\.spec\.ts)|tests?/test_[^/]*\.py)$")
MAX_ITEMS = 6
MAX_FILES = 8
MAX_ADDED_LINES_PER_FILE = 150
MAX_ADDED_LINES_TOTAL = 400
CONFIX_DENIED_PREFIXES = fidelity.LOCAL_GATE_PATH_PREFIXES + (
    "ibl5/migrations/", ".github/", "ibl5/phpstan-baseline.neon")
CONFIX_DENIED_SUBSTRINGS = ("-snapshots/",)

# Vacuous-test scan, per language, over the ADDED lines of a test path only.
# (test-declaration regex, assertion regex, forbidden-marker regex)
_VACUOUS_RULES = {
    ".php": (re.compile(r"(public\s+)?function\s+test\w*\s*\(|#\[Test\]"),
             re.compile(r"(->|::)assert\w+\(|expectException\w*\("),
             re.compile(r"markTestSkipped|markTestIncomplete"
                        r"|assertTrue\(\s*true\s*\)|assertFalse\(\s*false\s*\)")),
    ".py": (re.compile(r"^\s*def test_", re.MULTILINE),
            re.compile(r"^\s*assert\b|pytest\.raises\(", re.MULTILINE),
            re.compile(r"pytest\.skip|mark\.skip|xfail")),
    ".spec.ts": (re.compile(r"\b(test|it)\("),
                 re.compile(r"\bexpect\("),
                 re.compile(r"\.skip\(|\.only\(|fixme")),
}

PROMPT_RULES = (
    "Rules (a mechanical check enforces each one; a violation reverts your whole round):\n"
    "- Write ONLY inside the allowed paths listed below. Any other write is reverted.\n"
    "- In an existing test file, append. Never change or delete an existing line.\n"
    "- Every new test method asserts on the production behavior the plan row names.\n"
    "- Never use markTestSkipped, markTestIncomplete, pytest.skip, xfail, test.skip,\n"
    "  .only, fixme, assertTrue(true) or assertFalse(false).\n"
    f"- Stay under {MAX_ADDED_LINES_PER_FILE} added lines per file, "
    f"{MAX_ADDED_LINES_TOTAL} in total, and {MAX_FILES} files.\n"
    "- You have no Bash. Phase 5 runs the tests after you finish; do not try to run them.\n"
    "- Name each promised method exactly as the item names it.\n"
    f"- {fidelity.GATE_EDIT_DENY_TEXT}\n"
)


def enabled(env: Mapping[str, str] | None = None) -> bool:
    env = os.environ if env is None else env
    return env.get(ENV_FLAG, "") != "0"


@dataclass
class Target:
    item: str
    kind: str
    allowed_paths: list[str]
    method: str | None = None


@dataclass
class Classification:
    fixable: list[Target] = field(default_factory=list)
    unfixable: dict[str, str] = field(default_factory=dict)


def _denied(path: str) -> bool:
    return (any(path.startswith(p) for p in CONFIX_DENIED_PREFIXES)
            or any(s in path for s in CONFIX_DENIED_SUBSTRINGS)
            or bool(fidelity.denied_local_gate_edits([path])))


def _item_subject(item: str, prefix: str) -> str:
    """The path or name an item carries: the text after `prefix` up to ` (`."""
    rest = item[len(prefix):].strip()
    return rest.split(" (", 1)[0].strip()


def plan_test_paths(plan) -> list[str]:
    """Plan-cited test paths: matrix tokens plus Critical Files entries that are test paths."""
    out: list[str] = []
    for p in list(getattr(plan, "planned_test_paths", None) or []) + [
            cf[0] for cf in (getattr(plan, "critical_files", None) or [])]:
        if p and TEST_PATH_RE.search(p) and p not in out:
            out.append(p)
    return out


def plan_critical_paths(plan) -> list[str]:
    return [cf[0] for cf in (getattr(plan, "critical_files", None) or []) if cf and cf[0]]


def classify(items: list[str], plan_test_paths: list[str],
             plan_critical_paths: list[str], exists=None) -> Classification:
    """Split conformance items into fixer targets and refusals (item -> reason). Pure.

    `exists(path)` reports whether a repo path is already on disk. A non-test Critical File
    that exists is refused: the plan wants it changed, and a trivial append would clear the gate.
    """
    del plan_critical_paths  # MISSING-FILE carries its own path; kept for the call shape
    out = Classification()
    test_targets = [p for p in plan_test_paths if not _denied(p)]
    for item in items:
        if item.startswith(NEVER_FIXABLE_PREFIXES):
            out.unfixable[item] = "never-fixable"
        elif item.startswith("MISSING-FILE:"):
            path = _item_subject(item, "MISSING-FILE:")
            if _denied(path):
                out.unfixable[item] = "denied-path"
            elif exists is not None and not TEST_PATH_RE.search(path) and exists(path):
                out.unfixable[item] = "existing-non-test-file"
            else:
                out.fixable.append(Target(item, "promised-file", [path]))
        elif item.startswith("MISSING-METHOD:"):
            name = _item_subject(item, "MISSING-METHOD:")
            if test_targets:
                out.fixable.append(Target(item, "method", list(test_targets), method=name))
            else:
                out.unfixable[item] = "no-target"
        elif item.startswith("UNREALISED-ASSERTION:"):
            if "matrix-assertion check unavailable" in item:
                out.unfixable[item] = "check-unavailable"
            elif test_targets:
                out.fixable.append(Target(item, "assertion", list(test_targets)))
            else:
                out.unfixable[item] = "no-target"
        elif item.startswith("MISSING:"):
            path = _item_subject(item, "MISSING:")
            if _denied(path):
                out.unfixable[item] = "denied-path"
            elif not TEST_PATH_RE.search(path):
                out.unfixable[item] = "non-test-path"
            else:
                out.fixable.append(Target(item, "test-path", [path]))
        else:
            out.unfixable[item] = "unknown-class"
    return out


def hold_note(record: dict) -> str:
    """One short clause for the condition-(3) hold line; "" when the fixer did not run."""
    if not record:
        return ""
    r = record.get("reason", "")
    if r == "committed":
        return (f"wrote {len(record.get('written_paths', []))} file(s) in "
                f"{str(record.get('sha', ''))[:12]}; "
                f"still missing {len(record.get('still_missing', []))}")
    if r in ("rejected", "crashed"):
        return f"{r} ({record.get('detail', '')})"
    return r  # disabled, no-items, no-fixable-items, dirty-worktree, cap-items, no-edits


def _read_bytes(worktree: str, path: str) -> bytes | None:
    try:
        with open(os.path.join(worktree, path), "rb") as fh:
            return fh.read()
    except OSError:
        return None


def _lines(data: bytes | None) -> list[str]:
    return (data or b"").decode("utf-8", errors="replace").splitlines()


def _added_and_removed(before: bytes | None, after: bytes | None) -> tuple[list[str], int]:
    """(added lines, removed-line count) between two file versions."""
    if before is None:
        return _lines(after), 0
    added: list[str] = []
    removed = 0
    for ln in difflib.unified_diff(_lines(before), _lines(after), lineterm="", n=0):
        if ln.startswith("+++") or ln.startswith("---") or ln.startswith("@@"):
            continue
        if ln.startswith("+"):
            added.append(ln[1:])
        elif ln.startswith("-"):
            removed += 1
    return added, removed


def _vacuous(path: str, added_text: str) -> bool:
    for suffix, (decl, asserts, forbidden) in _VACUOUS_RULES.items():
        if path.endswith(suffix):
            if forbidden.search(added_text):
                return True
            return len(decl.findall(added_text)) > len(asserts.findall(added_text))
    return False


def _matrix_rows(plan, items: list[str]) -> list[str]:
    """Plan lines that are matrix rows naming any item's path or method."""
    path = getattr(plan, "path", "") or ""
    if not path:
        return []
    try:
        with open(path) as fh:
            lines = fh.read().splitlines()
    except OSError:
        return []
    subjects = set()
    for item in items:
        for prefix in ("MISSING-FILE:", "MISSING-METHOD:", "MISSING:"):
            if item.startswith(prefix):
                subjects.add(_item_subject(item, prefix))
                break
        m = re.match(r"UNREALISED-ASSERTION:\s*row\s+(\S+)", item)
        if m:
            subjects.add(f"| {m.group(1)} |")
    return [ln for ln in lines if ln.lstrip().startswith("|")
            and any(s and s in ln for s in subjects)]


def _packet(plan, targets: list[Target], allowed: list[str], diff_text: str) -> str:
    diff_note = ""
    if len(diff_text) > fidelity.REMEDIATION_DIFF_INLINE_CAP:
        diff_text = diff_text[:fidelity.REMEDIATION_DIFF_INLINE_CAP]
        diff_note = f"\n[diff truncated at {fidelity.REMEDIATION_DIFF_INLINE_CAP} bytes]\n"
    methods = list(getattr(plan, "required_test_methods", None) or [])
    rows = _matrix_rows(plan, [t.item for t in targets])
    return (
        "=== ITEMS TO WRITE ===\n"
        + "".join(f"- [{t.kind}] {t.item}\n" for t in targets)
        + "=== ALLOWED PATHS (write nowhere else) ===\n"
        + "".join(f"- {p}\n" for p in allowed)
        + "=== PLAN REQUIRED TEST METHODS ===\n"
        + ("".join(f"- {m}\n" for m in methods) or "(none)\n")
        + "=== PLAN MATRIX ROWS NAMING THESE ITEMS ===\n"
        + ("".join(f"{r}\n" for r in rows) or "(none found)\n")
        + "=== DIFF UNDER TEST (the production code your tests exercise) ===\n"
        + f"{diff_text}{diff_note}"
        + "=== END DIFF ===\n"
    )


def _noop_log(_msg: str) -> None:
    return


def remediate_missing(llm, gitad, plan, worktree: str, out_dir: str, items: list[str], *,
                      commit, push, log=None, model: str = "sonnet",
                      env: Mapping[str, str] | None = None) -> dict:
    """Run at most one fixer round. Returns the record stored on RunResult.conformance_fix.

    Fail-closed: every outcome except `committed` leaves the tree as found. Only
    `llm-usage-limit` / `llm-auth-expired` propagate from the spawn, and a post-commit
    gate-path hit raises `gate-path-edit` like the Phase 5.5 remediation.
    """
    log = log or _noop_log
    t0 = time.monotonic()
    rec: dict = {"enabled": True, "reason": "", "detail": "", "model": model,
                 "items": list(items), "fixable": [], "unfixable": {},
                 "allowed_paths": [], "written_paths": [], "sha": "",
                 "resolved": [], "still_missing": [], "elapsed_s": 0.0}

    def _done(reason: str, detail: str = "") -> dict:
        rec["reason"] = reason
        rec["detail"] = detail
        rec["elapsed_s"] = round(time.monotonic() - t0, 2)
        log(f"phase5.0-fix: {reason}" + (f" {detail}" if detail else ""))
        return rec

    if not enabled(env):
        rec["enabled"] = False
        return _done("disabled")
    if not items:
        return _done("no-items")
    cls = classify(items, plan_test_paths(plan), plan_critical_paths(plan),
                   exists=lambda p: bool(worktree) and os.path.exists(os.path.join(worktree, p)))
    rec["fixable"] = [t.item for t in cls.fixable]
    rec["unfixable"] = dict(cls.unfixable)
    if not cls.fixable:
        return _done("no-fixable-items")
    if len(cls.fixable) > MAX_ITEMS:
        return _done("cap-items", f"{len(cls.fixable)} > {MAX_ITEMS}")
    if not worktree:
        # Replay runs carry no tree; never let a snapshot or revert touch the cwd.
        return _done("no-worktree")
    if gitad.is_dirty():
        return _done("dirty-worktree")

    allowed: list[str] = []
    for t in cls.fixable:
        for p in t.allowed_paths:
            if p not in allowed:
                allowed.append(p)
    rec["allowed_paths"] = list(allowed)
    before = {p: _read_bytes(worktree, p) for p in allowed}
    pre_dirty = set(gitad.dirty_paths())

    def _revert() -> None:
        for p, data in before.items():
            full = os.path.join(worktree, p)
            try:
                if data is None:
                    if os.path.exists(full):
                        os.remove(full)
                else:
                    os.makedirs(os.path.dirname(full) or ".", exist_ok=True)
                    with open(full, "wb") as fh:
                        fh.write(data)
            except OSError:
                pass
        stray = sorted(set(gitad.dirty_paths()) - pre_dirty - set(before))
        if stray:
            gitad.restore_paths(stray)

    packet_dir = os.path.join(out_dir, PURPOSE)
    os.makedirs(packet_dir, exist_ok=True)
    try:
        diff_text = gitad.diff_vs_base()
    except Exception:
        diff_text = "(diff unavailable)"
    packet = _packet(plan, cls.fixable, allowed, diff_text)
    with open(os.path.join(packet_dir, "packet.md"), "w") as fh:
        fh.write(packet)
    prompt = (
        "Write the tests and files a post-plan conformance check reports as missing.\n"
        "The plan promised them and the implementation never wrote them.\n\n"
        "Every input you need is INLINE below. Edit the worktree you are running in.\n"
        "The harness commits your edits after a mechanical check; you never commit.\n"
        "No Agent tool: do not delegate.\n\n"
        f"{PROMPT_RULES}\n"
        f"{packet}"
    )
    try:
        llm.call_tooled(PURPOSE, model, prompt, cwd=worktree,
                        allowed_tools=gatefix.GATEFIX_ALLOWED,
                        denied_tools=gatefix.GATEFIX_DENIED,
                        add_dirs=(packet_dir,))
    except HarnessError as e:
        _revert()
        if e.kind in ("llm-usage-limit", "llm-auth-expired"):
            log(f"phase5.0-fix: crashed {e.kind} - tree reverted, re-raising")
            raise
        return _done("crashed", e.kind)
    except Exception as e:  # noqa: BLE001 - any fixer failure must leave the tree as found
        _revert()
        return _done("crashed", type(e).__name__)

    changed = sorted(set(gitad.dirty_paths()) - pre_dirty)
    out_of_scope = [p for p in changed if p not in before]
    if out_of_scope:
        _revert()
        return _done("rejected", "out-of-scope-write")
    if any(_denied(p) for p in changed):
        _revert()
        return _done("rejected", "denied-path")
    if not changed:
        return _done("no-edits")

    added_by_path: dict[str, list[str]] = {}
    for p in changed:
        added, removed = _added_and_removed(before.get(p), _read_bytes(worktree, p))
        if removed:
            _revert()
            return _done("rejected", "edited-existing-lines")
        added_by_path[p] = added
    total = sum(len(a) for a in added_by_path.values())
    if (len(changed) > MAX_FILES or total > MAX_ADDED_LINES_TOTAL
            or any(len(a) > MAX_ADDED_LINES_PER_FILE for a in added_by_path.values())):
        _revert()
        return _done("rejected", "cap-exceeded")
    for p, added in added_by_path.items():
        if TEST_PATH_RE.search(p) and _vacuous(p, "\n".join(added)):
            _revert()
            return _done("rejected", "vacuous-test")
    for t in cls.fixable:
        if t.kind != "method":
            continue
        if not any(p in added_by_path
                   and _required_name_present(t.method, "\n".join(added_by_path[p]))
                   for p in t.allowed_paths):
            _revert()
            return _done("rejected", "method-not-written")

    sha = commit(COMMIT_MSG)
    if not sha:
        return _done("no-edits")
    hits = fidelity.denied_local_gate_edits(gitad.changed_files(f"{sha}^"))
    if hits:
        log(f"phase5.0-fix: fix commit touched gate paths ({', '.join(hits)}) "
            "- commit left local, run ends")
        rec["sha"] = str(sha)
        raise HarnessError("gate-path-edit", ", ".join(hits))
    pushed = push()
    rec["sha"] = str(pushed or sha)
    rec["written_paths"] = list(changed)
    return _done("committed", rec["sha"][:12])


def _cli_classify(plan_path: str, stdin) -> int:
    from .planfile import locate_plan  # local import keeps module-level cycle-free
    if not os.path.isfile(plan_path):
        print(f"conformance_fix: plan not found: {plan_path}", file=sys.stderr)
        return 1
    plan = locate_plan(slug=None, plans_dir=None, explicit_path=plan_path)
    if not plan.found:
        print(f"conformance_fix: plan not readable: {plan_path}", file=sys.stderr)
        return 1
    items = [ln.strip() for ln in stdin.read().splitlines() if ln.strip()]
    cls = classify(items, plan_test_paths(plan), plan_critical_paths(plan))
    for t in cls.fixable:
        print(f"FIXABLE {t.kind} {t.item}")
    for item, reason in cls.unfixable.items():
        print(f"UNFIXABLE {reason} {item}")
    return 0


def main(argv: list[str] | None = None, stdin=None) -> int:
    """usage: python3 -m harness.conformance_fix classify --plan <plan.md> < items.txt

    Prints `FIXABLE <kind> <item>` / `UNFIXABLE <reason> <item>`; never touches git or an LLM.
    Exit 0 on success, 1 on a missing plan, 2 on bad usage.
    """
    import argparse
    parser = argparse.ArgumentParser(prog="python3 -m harness.conformance_fix")
    sub = parser.add_subparsers(dest="cmd", required=True)
    c = sub.add_parser("classify")
    c.add_argument("--plan", required=True)
    args = parser.parse_args(argv)
    return _cli_classify(args.plan, stdin if stdin is not None else sys.stdin)


if __name__ == "__main__":
    sys.exit(main())
