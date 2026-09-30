"""Phase 5.0 — plan→test and plan→file (Critical Files) conformance. Pure port of
_phase-5-final-verification.md's two check loops."""
from __future__ import annotations

import os
import re
import subprocess
import sys
import tempfile
from pathlib import PurePosixPath

from .state import PhaseInfo, PlanInfo

_MATRIX_ASSERTIONS_SCRIPT = str(
    PurePosixPath(os.path.abspath(__file__)).parents[3] / "bin" / "lib" / "plan-matrix-assertions")

# Tier 3 of _resolve: a plan-named migration or ADR whose number shifted at
# implementation time because a parallel branch claimed the same number first
# (backlog#937 for migrations, backlog#1169 for ADRs). The directory prefix is part
# of each pattern on purpose — the tolerance is for `ibl5/migrations/NNN_<suffix>`
# and `ibl5/docs/decisions/NNNN-<suffix>` only, never for any other numbered
# filename. The two prefixes are disjoint, so a token matches at most one pattern.
_MIGRATION_RENUMBER = re.compile(r"^ibl5/migrations/\d+_(?P<suffix>.+)$")
# Exactly four digits: `bin/next-adr` emits `printf "%04d"` and `bin/check-numbering`
# greps `^[0-9]{4}-`. A 3- or 5-digit prefix is not an ADR filename and gets no tolerance.
_ADR_RENUMBER = re.compile(r"^ibl5/docs/decisions/\d{4}-(?P<suffix>.+)$")


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


def _renumbered_migration(tok: str, changed_files: list[str]) -> str | None:
    """The single changed migration sharing `tok`'s suffix under a different number.

    None unless `tok` is `ibl5/migrations/NNN_<suffix>` AND exactly one changed path
    is `ibl5/migrations/MMM_<suffix>` with the identical suffix. Two such paths is
    ambiguous and returns None, so a MISSING-FILE still fires; the caller has already
    established that no exact, suffix, or basename match exists.
    """
    m = _MIGRATION_RENUMBER.match(tok)
    if not m:
        return None
    suffix = m.group("suffix")
    hits: list[str] = []
    for f in changed_files:
        n = _MIGRATION_RENUMBER.match(f)
        if n and n.group("suffix") == suffix:
            hits.append(f)
    return hits[0] if len(hits) == 1 else None


def _renumbered_adr(tok: str, changed_files: list[str]) -> str | None:
    """The single changed ADR sharing `tok`'s suffix under a different number.

    None unless `tok` is `ibl5/docs/decisions/NNNN-<suffix>` AND exactly one changed
    path is `ibl5/docs/decisions/MMMM-<suffix>` with the identical suffix. Two such
    paths is ambiguous and returns None, so a MISSING-FILE still fires; the caller
    has already established that no exact, suffix, or basename match exists. Kept
    as a sibling of `_renumbered_migration` rather than a shared helper so the
    migration tier's body stays byte-identical (backlog#1169 scope).
    """
    m = _ADR_RENUMBER.match(tok)
    if not m:
        return None
    suffix = m.group("suffix")
    hits: list[str] = []
    for f in changed_files:
        n = _ADR_RENUMBER.match(f)
        if n and n.group("suffix") == suffix:
            hits.append(f)
    return hits[0] if len(hits) == 1 else None


def _resolve(tok: str, changed_files: list[str]) -> str | None:
    """The single changed path a plan token names, or None when 0 or 2+ candidates.

    Three ordered tiers. Tier 1 is exact-or-path-suffix, which covers a plan that named
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

    Tier 3 runs only when Tiers 1 and 2 found nothing: a token of the form
    `ibl5/migrations/NNN_<suffix>` resolves to the one changed path
    `ibl5/migrations/MMM_<suffix>` with the identical suffix, and a token of the
    form `ibl5/docs/decisions/NNNN-<suffix>` resolves to the one changed path
    `ibl5/docs/decisions/MMMM-<suffix>`. Both are the shape a plan-authorized
    renumber produces when `bin/next-migration` or `bin/next-adr` prints a
    different number than the plan quoted. Migration is checked first, then ADR;
    the prefixes are disjoint so order never changes the result. Zero or 2+
    same-suffix paths still return None.
    """
    tok = tok.strip().strip("/")
    # pytest node-id form `path/to/file.py::test_name` — strip the test-name
    # suffix so the token resolves to the file path the diff actually contains.
    if "::" in tok:
        tok = tok.split("::", 1)[0].strip("/")
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
    if len(cands) == 1:
        return cands.pop()
    if cands:
        return None
    return _renumbered_migration(tok, changed_files) or _renumbered_adr(tok, changed_files)


def _touched(tok: str, changed_files: list[str]) -> bool:
    """True when ANY changed path matches `tok` exactly, by path suffix, or by basename.

    Deliberately looser than _resolve: an ambiguous 2+ candidate set means the diff DID
    touch something the phase named, which is evidence the phase shipped. Precision over
    recall for a hold-only check: a false "shipped" costs nothing new, while a false
    "missing" holds a PR a human then has to clear.
    """
    tok = tok.strip().strip("/")
    if not tok:
        return False
    if any(f == tok or f.endswith("/" + tok) for f in changed_files):
        return True
    base = PurePosixPath(tok).name
    for f in changed_files:
        p = PurePosixPath(f)
        if p.name == base or any(parent.name == base for parent in p.parents):
            return True
    return False


def phase_omission_items(plan: PlanInfo, changed_files: list[str]) -> list[str]:
    """`MISSING-PHASE:` items for plan phases with evidence paths none of which the diff touched.

    Exempt, in order: a phase whose heading carries an all-S `[phases: S]` marker
    (bookkeeping), a phase number named in `## Out of Scope` (declared deferred), and a
    phase whose body cites no path at all (no evidence = cannot verify = skip). Empty when
    the plan was not found or has no parsed phases, so a plan-blind run and every
    pre-existing PlanInfo literal produce nothing. Hold-only: the items flow into arming
    condition (3) via check(); fidelity.build_work_list excludes them from the fixer loop.
    """
    if not plan.found or not plan.phases:
        return []
    deferred = set(plan.deferred_phase_numbers)
    items: list[str] = []
    for ph in plan.phases:
        if ph.bookkeeping or ph.number in deferred or not ph.evidence_paths:
            continue
        if any(_touched(p, changed_files) for p in ph.evidence_paths):
            continue
        sample = ", ".join(ph.evidence_paths[:3])
        more = f" (+{len(ph.evidence_paths) - 3} more)" if len(ph.evidence_paths) > 3 else ""
        items.append(f"MISSING-PHASE: {ph.number} — {ph.heading[:80]} "
                     f"(phase cites {sample}{more}; none appeared in the diff)")
    return items


def _matrix_assertion_argv(script: str, plan_path: str, diff_path: str,
                           body_path: str) -> list[str]:
    return [script, plan_path, diff_path, body_path]


def _matrix_assertion_items(plan: PlanInfo, diff_body: str, pr_body: str,
                            script: str | None = None) -> list[str]:
    """`UNREALISED-ASSERTION:` items from bin/lib/plan-matrix-assertions.

    Fail-CLOSED on an unrunnable script (OSError, exit >= 2): a gate that reports
    clean when it could not run is the silent pass this check exists to remove.

    An empty `plan.path` means the plan came from `content_override` (replay
    fixtures, hand-built PlanInfo in tests): there is no file for the script to
    read, so the check does not apply. A live plan always carries its disk path.
    """
    if not plan.path:
        return []
    script = script or _MATRIX_ASSERTIONS_SCRIPT
    with tempfile.TemporaryDirectory(prefix="matrix-assert-") as td:
        diff_path = os.path.join(td, "diff.patch")
        body_path = os.path.join(td, "pr-body.md")
        with open(diff_path, "w") as fh:
            fh.write(diff_body)
        with open(body_path, "w") as fh:
            fh.write(pr_body or "")
        try:
            proc = subprocess.run(
                _matrix_assertion_argv(script, plan.path, diff_path, body_path),
                capture_output=True, text=True, check=False)
        except OSError as e:
            return [f"UNREALISED-ASSERTION: matrix-assertion check unavailable ({e.__class__.__name__})"]
    if proc.returncode >= 2 or proc.returncode < 0:
        first = (proc.stderr.strip().splitlines() or ["no stderr"])[0][:120]
        return [f"UNREALISED-ASSERTION: matrix-assertion check unavailable (exit {proc.returncode}: {first})"]
    return [ln.strip() for ln in proc.stdout.splitlines()
            if ln.strip().startswith("UNREALISED-ASSERTION:")]


def check(plan: PlanInfo, changed_files: list[str], diff_body: str = "",
          phase5_status: str | None = None,
          resolutions: dict[str, str] | None = None,
          pr_body: str = "") -> list[str]:
    """Returns unresolved `MISSING:` / `MISSING-FILE:` / `MISSING-METHOD:` /
    `UNMET-CONTRACT:` / `MISSING-PHASE:` / `UNREALISED-ASSERTION:` items (empty = clean).

    A planned token listed in `plan.no_change_test_paths` (every planning row is a
    Visual-regression row marked `(no-change)`) never yields `MISSING:`.

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
    # Runs before the has_matrix gate on purpose: a matrix-less doc/tooling plan still
    # has phases, and a phase that shipped nothing is the same defect either way.
    items.extend(phase_omission_items(plan, changed_files))
    if not plan.has_matrix:
        return items
    for t in plan.planned_test_paths:
        if t in plan.no_change_test_paths:
            # Every row planning this token is a Visual-regression row marked
            # `(no-change)`: the planned outcome IS an untouched baseline, so an
            # absent diff entry is the pass condition, not a missing test
            # (backlog#1222). parse_no_change_test_paths already refused the
            # exemption when any unmarked row shares the token.
            continue
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
            # Match PHP/Python-style declarations ('function name' / 'def name') and
            # bash-style bare declarations ('name() {') that appear without a keyword.
            # The second branch anchors to start-of-line (with optional diff '+' prefix
            # and indentation) so call-sites like '$this->name()' are not mistaken for
            # declarations (backlog#1133 — present-method false-positive fix).
            _found = re.search(
                rf"(function|def)\s+{re.escape(m)}\b"
                rf"|^[+\s]*{re.escape(m)}\s*\(\s*\)",
                diff_body,
                re.MULTILINE,
            )
            if not _found:
                items.append(f"MISSING-METHOD: {m} (plan required a test method the diff never wrote)")
        items.extend(_matrix_assertion_items(plan, diff_body, pr_body))
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
