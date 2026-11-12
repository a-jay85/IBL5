"""Plain-words text for each auto-merge hold, for the sticky verdict comment only.

`armable.evaluate` writes terse machine reasons (`state=ONE-WAY`, `fidelity
verdict='NOT READY' (source: ...)`). result.json, the fleet DM, holdrepeat and the fix
round read those, so they stay as they are. This module turns one held
`ConditionResult` into a single sentence a person can act on. It keeps the useful
detail (paths, PR numbers, check names) and drops the codes.

Display-only: nothing here feeds an arming decision. A condition number this module
does not know falls back to the old `name — reason` text, so a new condition still
shows up in the comment.
"""
from __future__ import annotations

import re


def _state(reason: str) -> str:
    m = re.match(r"state=(\S+)", reason or "")
    return m.group(1) if m else ""


def _manual_testing(reason: str) -> str:
    if _state(reason) == "UNKNOWN":
        return ("The PR body has no Manual Testing section, so nobody has signed off "
                "on manual tests.")
    return "Some manual tests in the PR body still need a person to run and tick them."


def _unique(items):
    return list(dict.fromkeys(i for i in items if i))


def _review_findings(reason: str) -> str:
    spots = _unique(re.sub(r"\s+score=\d+$", "", s.strip()) for s in (reason or "").split(";"))
    if len(spots) > 1:
        return "Code review found likely bugs that are not fixed yet: " + ", ".join(spots)
    text = "Code review found a likely bug that is not fixed yet"
    return text + (": " + spots[0] if spots else ".")


def _missing_item(item: str) -> str:
    item = item.strip()
    m = re.match(r"MISSING-FILE:\s*(\S+)", item)
    if m:
        return f"file {m.group(1)}"
    m = re.match(r"MISSING-PHASE:\s*\d+\s*—\s*(.*?)(?:\s*\(phase cites .*)?$", item)
    if m:
        return m.group(1)
    m = re.match(r"UNREALISED-ASSERTION:\s*row\s*(\S+)", item)
    if m:
        return f"the test for matrix row {m.group(1)}"
    item = re.sub(r"\s*\((?:matrix planned a test|plan Critical File)[^)]*\)\s*$", "", item)
    return re.sub(r"^MISSING:\s*", "", item)


def _missing_items(reason: str) -> str:
    items = re.sub(r"\s*\[conformance-fix:.*\]\s*$", "", reason or "")
    parts = _unique(_missing_item(i) for i in items.split(";"))
    text = "The plan asks for things this PR does not have yet"
    return text + (": " + "; ".join(parts) if parts else ".")


def _phase5(reason: str) -> str:
    if "never recorded" in (reason or ""):
        return "The local test step never finished, so nobody knows if the tests pass."
    if "deterministic failure" in (reason or ""):
        return "The local tests failed before the PR opened."
    return "The local test step ended in a state the harness does not recognize."


def _depends_on(reason: str) -> str:
    def one(tok: str) -> str:
        m = re.match(r"#(\d+)\((\w+)\)", tok.strip())
        if not m:
            return tok.strip()
        state = m.group(2).lower()
        return f"#{m.group(1)}" + ("" if state == "unknown" else f" ({state})")
    deps = ", ".join(one(t) for t in (reason or "").split(",") if t.strip())
    return "This PR has to merge after other PRs that have not merged yet" + (
        f": {deps}" if deps else ".")


def _plan_auto_merge(reason: str) -> str:
    r = reason or ""
    if r.startswith("plan declares auto_merge: false"):
        return "The plan says a person must review and merge this PR."
    if "failed:" in r:
        body = re.sub(r"\s*\(verdict source:[^)]*\)\s*$", "", r.split("failed:", 1)[1])
        why = _unique(_docs_refresh_clause(t.strip())
                      for t in re.split(r",\s*(?=docs-refresh:)", body)
                      if t.strip().startswith("docs-refresh:"))
        return ("The plan lets this PR merge on its own only if it is a clean docs-only "
                "refresh, and this PR does not qualify" + (": " + "; ".join(why) if why else "."))
    return "The plan's auto-merge setting is not one the harness knows, so it waits for a person."


def _docs_refresh_clause(tok: str) -> str:
    kind, _, val = tok[len("docs-refresh:"):].partition(":")
    if kind == "paths":
        return "it changes no files" if val == "empty" else f"{val} is not a docs file"
    if kind == "branch":
        return (f"branch {val} is not a docs-refresh branch" if val != "<empty>"
                else "the branch name is missing")
    if kind == "merge-danger":
        return "its Merge danger line does not say it is easy to undo"
    if kind == "verdict":
        return (f"the plan check said {val}" if val != "<none>"
                else "the plan check gave no verdict")
    return tok


def _safety(reason: str) -> str:
    parts = []
    for p in (reason or "").split(";"):
        p = p.strip()
        if not p:
            continue
        if p.startswith("review unavailable:"):
            parts.append("some review agents did not run (" + p.split(":", 1)[1].strip() + ")")
        elif p.startswith("destructive/schema-tightening migration"):
            parts.append("a migration deletes data or tightens the schema")
        else:
            parts.append(p)
    return "The safety review flagged a risk" + (": " + "; ".join(parts) if parts else ".")


def _threads(reason: str) -> str:
    r = reason or ""
    if "not consulted" in r:
        return "The PR's review comments were never checked, so it waits to be safe."
    if "GitHub API error" in r:
        return "GitHub would not return the PR's review comments, so it waits to be safe."
    if "page cap" in r:
        return "The PR has more than 100 review threads, too many to check."
    m = re.match(r"(\d+) unresolved", r)
    n = int(m.group(1)) if m else 0
    if n == 1:
        return "1 review comment thread on the PR is still open."
    return f"{n or 'Some'} review comment threads on the PR are still open."


def _fidelity(reason: str) -> str:
    m = re.match(r"fidelity verdict=(None|'([^']*)')", reason or "")
    word = m.group(2) if m and m.group(2) is not None else ""
    if not word:
        return "The check that compares the PR to its plan never gave a verdict."
    return f"The check that compares the PR to its plan did not pass (it said {word})."


def _slug_drift(reason: str) -> str:
    m = re.match(r"plan '([^']*)'", reason or "")
    name = f" ({m.group(1)})" if m else ""
    return (f"The harness guessed which plan file goes with this branch{name}. "
            "Confirm it is the right plan.")


def _conflict(reason: str) -> str:
    if "not consulted" in (reason or ""):
        return "The harness could not check whether Claude resolved a merge conflict here."
    return ("Claude resolved a merge conflict on this branch, and no review has confirmed "
            "the fix yet.")


def _red_ci(reason: str) -> str:
    return "These CI checks failed: " + (reason or "unknown") + "."


def _meta_checks(reason: str) -> str:
    return "The repo's own tooling checks did not pass."


def _gate_backtest(reason: str) -> str:
    detail = (reason or "").split(";", 1)[1].strip() if ";" in (reason or "") else ""
    return ("This PR changes a safety check, and testing it against past PRs did not "
            "come out clean" + (f": {detail}" if detail else "."))


def _merge_danger(reason: str) -> str:
    s = _state(reason)
    if s == "ONE-WAY":
        return "This change is hard to undo, so a person should merge it."
    if s == "SELF-GOVERNING":
        return "This PR changes the auto-merge machinery itself, so a person should merge it."
    if s == "MISSING":
        return "The PR body has no Merge danger line saying whether this is easy to undo."
    return "The PR body's Merge danger line could not be read."


# Plain words for each row status bin/lib/plan-exec-mutations prints. classify.py's
# PR-body block says the same things at full length.
_XM_WORDS = {
    "NOT-APPLIED": "the bug could not be planted",
    "BASELINE-FAILED": "the test failed before any bug was planted",
    "HELD": "the test still passed with the bug planted",
    "UNDECIDED": "the test could not run with the bug planted",
    "REVERT-FAILED": "the planted bug could not be undone",
}


_PLAN_CHECKS_MARKS = ("plan-checks", "Plan checks")


def _exec_mutations(reason: str) -> str:
    r = reason or ""
    segs = [s.strip() for s in r.split("; ") if s.strip()]
    pc = [s for s in segs if any(m in s for m in _PLAN_CHECKS_MARKS)]
    if not pc:
        return _local_mutations(r)
    pc_text = ("The Plan checks CI job has not passed, or post-plan could not confirm its "
               "block: " + "; ".join(pc) + ".")
    rest = "; ".join(s for s in segs if s not in pc)
    return (_local_mutations(rest) + " " + pc_text) if rest else pc_text


def _local_mutations(r: str) -> str:
    if r.startswith("executable-mutation step did not run"):
        return ("The step that plants bugs on purpose, to prove the tests catch them, "
                "did not run.")
    parts = []
    if "tree not verified clean after mutation" in r:
        parts.append("the code may not be back to normal after the planted bugs")
    rows = re.findall(r"row (\S+) ([A-Z]+(?:-[A-Z]+)*)\b", r)
    parts += _unique(f"row {n} ({_XM_WORDS.get(k, k.lower())})" for n, k in rows)
    if not parts:
        parts = [r]
    return ("Some planned bug-planting checks did not work: " + "; ".join(parts)
            + ". The PR body's mutation section has the detail.")


# Plain names for the guard kinds in bin/lib/deleted-guards GUARD_KINDS.
_GUARD_WORDS = {
    "try": "a try block",
    "catch": "an error catch",
    "except": "an error catch",
    "finally": "a cleanup block",
    "exists-check": "an exists check",
    "lock": "a lock",
    "null-return": "a null check",
    "none-return": "a None check",
    "type-guard": "a type check",
    "command-guard": "a command-exists check",
    "or-true": "an ignore-errors fallback",
    "trap": "a cleanup trap",
    "strict-mode": "strict mode",
    "early-exit-test": "an early-exit check",
}


def _removed_guards(reason: str) -> str:
    r = reason or ""
    if _state(r) == "UNCOMPUTED":
        return "The harness could not check whether this PR removes a safety check."
    if "removed-guards:MALFORMED" in r:
        return "The PR body's list of removed safety checks could not be read."
    found = re.findall(r"removed-guards:(UNLISTED|UNJUSTIFIED|UNFILLED):([^@;\s]+)@([^#;\s]+)", r)
    unfilled = _unique(f"{_GUARD_WORDS.get(k, k)} in {p}" for t, k, p in found
                       if t == "UNFILLED")
    unlisted = _unique(f"{_GUARD_WORDS.get(k, k)} in {p}" for t, k, p in found
                       if t == "UNLISTED")
    thin = _unique(f"{_GUARD_WORDS.get(k, k)} in {p}" for t, k, p in found
                   if t == "UNJUSTIFIED")
    parts = []
    if unfilled:
        parts.append("the PR body has no reason filled in for these: " + ", ".join(unfilled))
    if thin:
        parts.append("the PR body explains these too briefly: " + ", ".join(thin))
    if unlisted:
        parts.append("the PR body does not explain " + ", ".join(unlisted))
    if not parts:
        parts = [r]
    return "This PR removes safety checks, and " + "; and ".join(parts) + "."


_BY_NUMBER = {
    1: _manual_testing,
    2: _review_findings,
    3: _missing_items,
    4: _phase5,
    5: lambda r: ("The game simulation output changed (golden.json), and no person was "
                  "watching to confirm it was on purpose."),
    6: _depends_on,
    7: _plan_auto_merge,
    8: lambda r: ("This PR adds something league GMs will notice, so it needs your OK. "
                  "Add the `human-approved` label after you check it."),
    9: _safety,
    10: lambda r: "An automated pipeline wrote this PR, so a person has to merge it.",
    11: _threads,
    12: _fidelity,
    13: _slug_drift,
    14: _conflict,
    15: _red_ci,
    16: _meta_checks,
    17: _gate_backtest,
    18: _merge_danger,
    19: _exec_mutations,
    20: _removed_guards,
}


def plain_hold(cond) -> str:
    """One line for the sticky's `Auto-merge held:` block, without the `- (N) ` prefix.

    The text never contains a newline: `bin/lib/held-prs.sh` ends the block at the
    first line that does not start with `- `.
    """
    reason = getattr(cond, "reason", "") or ""
    fn = _BY_NUMBER.get(getattr(cond, "number", None))
    text = fn(reason) if fn else f"{getattr(cond, 'name', '')} — {reason}"
    return " ".join(text.split())
