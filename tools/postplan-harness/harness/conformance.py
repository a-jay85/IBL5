"""Phase 5.0 — plan→test and plan→file (Critical Files) conformance. Pure port of
_phase-5-final-verification.md's two check loops."""
from __future__ import annotations

import os
import re
import subprocess
import sys
from pathlib import PurePosixPath

from .state import PlanInfo


def _contract_items(plan: PlanInfo, changed_files: list[str],
                    phase5_status: str | None) -> list[str]:
    """Returns unresolved `UNMET-CONTRACT:` items for a plan's autonomy contract.

    Empty for every plan that declares neither `stop_condition:` nor `evidence:`,
    which is every plan authored before the fields existed.
    """
    if not plan.stop_condition and not plan.evidence and not plan.contract_error:
        return []
    if plan.contract_error:
        # The parsed values are unusable; evaluating the other two rules against
        # them would emit misleading follow-on items.
        return [f"UNMET-CONTRACT: malformed autonomy contract — {plan.contract_error} "
                "(plan was hand-edited after bin/check-plan cleared it)"]
    items: list[str] = []
    # `!= "pass"` on purpose: unmet on "fail", on "skipped" AND on None. armable.py
    # condition (4) blocks only on "fail", so the other two arm today — narrowing
    # this to `== "fail"` removes the feature while leaving it looking present.
    if plan.stop_condition == "tests-green" and phase5_status != "pass":
        items.append("UNMET-CONTRACT: stop_condition tests-green declared but "
                     f"PHASE5_VERIFY_STATUS={phase5_status or 'none'}")
    # Exact match, not the substring test the Critical-Files loop below uses: an
    # evidence token is the machine-checked contract itself, so `bin/x` must not
    # be discharged by a diff that only touched `bin/xylophone`.
    for tok in plan.evidence:
        if not any(f == tok or f.endswith("/" + tok) for f in changed_files):
            items.append(f"UNMET-CONTRACT: evidence {tok} declared but never appeared in the diff")
    return items


def _resolve(tok: str, changed_files: list[str]) -> str | None:
    """The single changed path a plan token names, or None when 0 or 2+ candidates.

    Two ordered tiers. Tier 1 is exact-or-path-suffix, which covers a plan that named
    a repo-relative path while the diff carries a longer prefix (`tests/test_foo.py`
    vs `tools/postplan-harness/tests/test_foo.py`). Tier 2 is a basename match whose
    candidate set is the UNION of (a) matching file paths and (b) the DISTINCT ancestor
    DIRECTORY paths whose own basename matches, which covers a directory the diff moved
    under an extra component (`ibl5/tests/BulkImport/` vs `ibl5/tests/Unit/BulkImport/`).

    Counting distinct DIRECTORIES in (b), never the files inside them, is the whole
    point: three files under one matching directory is one candidate, so it resolves;
    one file under each of two same-named directories is two candidates, so it does not.
    The union needs no token-shape test, because a file path and a directory path are
    never the same string, and any total of 2+ is ambiguous either way.
    """
    tok = tok.strip().strip("/")
    if not tok:
        return None
    hits = [f for f in changed_files if f == tok or f.endswith("/" + tok)]
    if len(hits) == 1:
        return hits[0]
    if hits:
        return None
    base = PurePosixPath(tok).name
    cands: set[str] = set()
    for f in changed_files:
        p = PurePosixPath(f)
        if p.name == base:
            cands.add(str(p))
        for parent in p.parents:
            if parent.name == base:
                cands.add(str(parent))
    return cands.pop() if len(cands) == 1 else None


def check(plan: PlanInfo, changed_files: list[str], diff_body: str = "",
          phase5_status: str | None = None,
          resolutions: dict[str, str] | None = None) -> list[str]:
    """Returns unresolved `MISSING:` / `MISSING-FILE:` / `MISSING-METHOD:` /
    `UNMET-CONTRACT:` items (empty = clean).

    `UNMET-CONTRACT:` items are produced even when the plan has no Verification
    Matrix — a matrix-less doc/tooling plan is exactly what `evidence-present`
    exists for.

    Resolution (authoring the test / making the change / PR-comment noting the
    cut) is a downstream action; this function only detects.

    diff_body defaults to "" (fail-open): a missed caller silently no-ops the
    MISSING-METHOD loop instead of raising TypeError mid-run and aborting a live
    /post-plan. See plan Architectural trade-offs § conformance.check fail-open.

    resolutions, when a dict is passed, is filled with token -> actual path for
    each token that matched by suffix or basename rather than exactly.
    """
    if not plan.found:
        return []
    items: list[str] = _contract_items(plan, changed_files, phase5_status)
    if not plan.has_matrix:
        return items
    for t in plan.planned_test_paths:
        hit = _resolve(t, changed_files)
        if hit is None:
            items.append(f"MISSING: {t} (matrix planned a test the diff never wrote)")
        elif resolutions is not None and hit != t:
            resolutions[t] = hit
    for path, _annotation, exempt in plan.critical_files:
        if exempt:
            continue
        hit = _resolve(path, changed_files)
        if hit is None:
            items.append(f"MISSING-FILE: {path} (plan Critical File never appeared in the diff)")
        elif resolutions is not None and hit != path:
            resolutions[path] = hit
    if diff_body:
        for m in plan.required_test_methods:
            if not re.search(rf"(function|def)\s+{re.escape(m)}\b", diff_body):
                items.append(f"MISSING-METHOD: {m} (plan required a test method the diff never wrote)")
    return items


def _git_lines(args: list[str], cwd: str) -> list[str]:
    """Non-empty stdout lines from a read-only git command, [] on any failure.

    Fail-open on purpose: a repo with no `origin/master` ref, or no commits at all,
    must still let the seam report on the ranges that DO resolve rather than abort.
    An empty union degrades to "nothing changed", which surfaces as MISSING items the
    impl agent can see — never as a silent exit 0.
    """
    try:
        proc = subprocess.run(["git", *args], cwd=cwd, capture_output=True,
                              text=True, check=False)
    except OSError:
        return []
    if proc.returncode != 0:
        return []
    return [ln.strip() for ln in proc.stdout.splitlines() if ln.strip()]


def _changed_files(repo_root: str) -> list[str]:
    """Order-preserving three-way union of the paths this branch has touched.

    Committed (merge-base range) + uncommitted tracked + untracked-not-ignored.
    The third arm is why a brand-new test file counts as PRESENT before its first
    commit; `--exclude-standard` honors every gitignore source so build droppings
    never enter the set.
    """
    seen: list[str] = []
    for args in (["diff", "--name-only", "origin/master...HEAD"],
                 ["diff", "--name-only", "HEAD"],
                 ["ls-files", "--others", "--exclude-standard"]):
        for path in _git_lines(args, repo_root):
            if path not in seen:
                seen.append(path)
    return seen


def main(argv: list[str] | None = None) -> int:
    """One-shot Phase 5.0 conformance seam for impl-time pre-handoff checking.

    usage: python3 -m harness.conformance <abs-plan-path> [repo-root]

    Reports only `MISSING:` (a matrix-declared test path the diff never wrote) and
    `MISSING-FILE:` (a non-exempt plan Critical File the diff never touched). Those
    are the two items an implementation agent can still act on before it writes its
    handoff. `MISSING-METHOD:` is skipped structurally by passing `diff_body=""`, and
    `UNMET-CONTRACT:` items are filtered out because `phase5_status` is unknowable at
    impl time and contract checking belongs to post-plan Phase 5.0.

    Exit codes:
      0  clean — no MISSING:/MISSING-FILE: items
      1  items found — each printed to stdout, one per line
      2  could not evaluate — bad usage, non-absolute or missing plan path,
         unresolvable repo root, or `plan.found == False`
    """
    from .planfile import locate_plan  # local import keeps module-level cycle-free

    args = argv if argv is not None else sys.argv[1:]
    if not args or len(args) > 2:
        print("usage: python3 -m harness.conformance <abs-plan-path> [repo-root]",
              file=sys.stderr)
        return 2
    plan_path = args[0]
    if not os.path.isabs(plan_path):
        print(f"conformance: plan path must be absolute, got {plan_path!r}",
              file=sys.stderr)
        return 2
    if not os.path.isfile(plan_path):
        print(f"conformance: plan path does not exist: {plan_path}", file=sys.stderr)
        return 2
    if len(args) == 2 and args[1]:
        repo_root = args[1]
    else:
        roots = _git_lines(["rev-parse", "--show-toplevel"], os.getcwd())
        if not roots:
            print("conformance: could not resolve repo root from cwd; "
                  "pass it as the second argument", file=sys.stderr)
            return 2
        repo_root = roots[0]
    if not os.path.isdir(repo_root):
        print(f"conformance: repo root is not a directory: {repo_root}",
              file=sys.stderr)
        return 2
    plan = locate_plan(slug=None, plans_dir=None, explicit_path=plan_path)
    if not plan.found:
        # The vacuous-pass guard. check() returns [] for an unfound plan, so
        # falling through here would print nothing and exit 0 on an unreadable
        # plan — a clean verdict nobody earned.
        print(f"conformance: plan not readable as a plan file: {plan_path}",
              file=sys.stderr)
        return 2
    raw = check(plan, _changed_files(repo_root), diff_body="", phase5_status=None)
    items = [i for i in raw
             if i.startswith("MISSING:") or i.startswith("MISSING-FILE:")]
    for item in items:
        print(item)
    return 1 if items else 0


if __name__ == "__main__":
    sys.exit(main())
