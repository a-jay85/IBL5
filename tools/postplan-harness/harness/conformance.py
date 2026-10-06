"""Phase 5.0 — plan→test and plan→file (Critical Files) conformance. Pure port of
_phase-5-final-verification.md's two check loops."""
from __future__ import annotations

import fnmatch
import functools
import os
import re
import subprocess
import sys
import tempfile
from pathlib import PurePosixPath
from typing import Callable

from .planfile import NO_DIFF_MIN_REASON
from .state import PhaseInfo, PlanInfo

_MATRIX_ASSERTIONS_SCRIPT = str(
    PurePosixPath(os.path.abspath(__file__)).parents[3] / "bin" / "lib" / "plan-matrix-assertions")
_HARNESS_REPO_ROOT = str(PurePosixPath(os.path.abspath(__file__)).parents[3])

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
# Token side only. The architect contract (.claude/skills/plan/_architect-contract.md
# "Number placeholders") tells a plan to write `ibl5/docs/decisions/NNNN-<slug>.md`
# because the real number is known only at implementation time, so a plan token may
# legitimately carry the literal `NNNN` where a changed file carries `\d{4}`. The
# alternation is the exact uppercase literal: `nnnn`, `NNNNN`, `\w{4}` and `[\dN]{4}`
# are all deliberately NOT accepted, and the changed-file side stays `_ADR_RENUMBER`
# (`\d{4}`) so a committed placeholder file never satisfies a numbered token.
_ADR_TOKEN = re.compile(r"^ibl5/docs/decisions/(?:\d{4}|NNNN)-(?P<suffix>.+)$")


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

    None unless `tok` is `ibl5/docs/decisions/NNNN-<suffix>` (four digits, or the
    literal placeholder `NNNN`) AND exactly one changed path is
    `ibl5/docs/decisions/MMMM-<suffix>` (four digits) with the identical suffix. Two such
    paths is ambiguous and returns None, so a MISSING-FILE still fires; the caller
    has already established that no exact, suffix, or basename match exists. Kept
    as a sibling of `_renumbered_migration` rather than a shared helper so the
    migration tier's body stays byte-identical (backlog#1169 scope).
    """
    m = _ADR_TOKEN.match(tok)
    if not m:
        return None
    suffix = m.group("suffix")
    hits: list[str] = []
    for f in changed_files:
        n = _ADR_RENUMBER.match(f)
        if n and n.group("suffix") == suffix:
            hits.append(f)
    return hits[0] if len(hits) == 1 else None


# Trailing `:NNN`, `:NNN-MMM`, or a comma list such as `:41,47` that a plan writes after a
# matrix path to point at lines. Colon-and-digits only: `::test_name` is handled first by
# the pytest node-id split, and a word after the colon is left alone so `bin/foo:bar`
# never resolves to `bin/foo`. planfile._LINE_SUFFIX_RE is the phase-evidence sibling; it
# has no comma-list arm and accepts `#L12`, so the two stay separate on purpose.
_LINE_SUFFIX_LIST_RE = re.compile(r":\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$")


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
    `ibl5/docs/decisions/MMMM-<suffix>` (the token's number may be four digits or
    the literal placeholder `NNNN`). Both are the shape a plan-authorized
    renumber produces when `bin/next-migration` or `bin/next-adr` prints a
    different number than the plan quoted. Migration is checked first, then ADR;
    the prefixes are disjoint so order never changes the result. Zero or 2+
    same-suffix paths still return None.

    Before tier 1 the token drops a pytest `::name` suffix and a trailing `:NNN` /
    `:NNN-MMM` / `:41,47` line suffix. A token carrying `*`, `?`, or `[` is a glob and
    resolves only by `fnmatch` against the changed paths (1+ hits = the first sorted hit;
    `*` crosses `/`, which is fine for a presence check). An exact path match wins before
    suffix matching is tried, so a shorter sibling path elsewhere in the diff cannot make
    it ambiguous.
    """
    tok = tok.strip().strip("/")
    # pytest node-id form `path/to/file.py::test_name` — strip the test-name
    # suffix so the token resolves to the file path the diff actually contains.
    if "::" in tok:
        tok = tok.split("::", 1)[0].strip("/")
    tok = _LINE_SUFFIX_LIST_RE.sub("", tok).strip("/")
    if not tok:
        return None
    if any(ch in tok for ch in "*?["):
        # A glob token resolves by fnmatch alone: one or more changed paths match it, or
        # it is missing. No fall-through to the basename or renumber tiers, because a
        # glob basename names nothing and tier 3 must not gain glob tolerance.
        globbed = sorted(f for f in changed_files if fnmatch.fnmatchcase(f, tok))
        return globbed[0] if globbed else None
    if tok in changed_files:
        # Exact match wins outright. `bin/README.md` beside `ibl5/bin/README.md` was two
        # tier-1 hits and therefore ambiguous (PR #2786); the exact path is the answer.
        return tok
    hits = [f for f in changed_files if f.endswith("/" + tok)]
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


def _rooted_match(tok: str, files: list[str]) -> bool:
    """Exact, path-suffix, or directory-prefix match only. Used for a citation that was
    written with a leading `/`: `/ibl5/modules.php` and `/modules.php` match tracked
    `ibl5/modules.php`; `/post-plan` does NOT match `.claude/skills/post-plan/SKILL.md`
    (a parent-dir-name hit is what _touched accepts and what made a slash command look
    like a repo path)."""
    tok = tok.strip().lstrip("/").rstrip("/")
    if not tok:
        return False
    return any(f == tok or f.endswith("/" + tok) or f.startswith(tok + "/") for f in files)


@functools.lru_cache(maxsize=4)
def _tracked_files(repo_root: str = _HARNESS_REPO_ROOT) -> tuple[str, ...] | None:
    """`git ls-files` of repo_root, or None when git is unavailable (fail closed upstream)."""
    try:
        lines = _git_lines(["ls-files"], repo_root)
    except Exception:
        return None
    return tuple(lines) or None


def _repo_path_candidates(evidence: list[str], changed_files: list[str],
                          tracked: tuple[str, ...] | list[str] | None,
                          dropped: list[str] | None = None) -> list[str]:
    """Citations that could name a repo path. tracked=None keeps every citation (fail closed)."""
    if tracked is None:
        return list(evidence)
    tracked_list = list(tracked)
    out: list[str] = []
    for p in evidence:
        if p.startswith("~/"):
            if dropped is not None:
                dropped.append(p)
            continue
        if p.startswith("/"):
            if _rooted_match(p, changed_files) or _rooted_match(p, tracked_list):
                out.append(p)
            elif dropped is not None:
                dropped.append(p)
            continue
        if _touched(p, changed_files) or _touched(p, tracked_list):
            out.append(p)
    return out


def _note(notes: list[str] | None, line: str) -> None:
    if notes is not None:
        notes.append(line)


def phase_omission_items(plan: PlanInfo, changed_files: list[str],
                         tracked_files: list[str] | tuple[str, ...] | None = None,
                         notes: list[str] | None = None) -> list[str]:
    """`MISSING-PHASE:` items for plan phases with evidence paths none of which the diff touched.

    Exempt, in order: a phase whose heading carries an all-S `[phases: S]` marker
    (bookkeeping), a phase number named in `## Out of Scope` (declared deferred), a phase
    carrying an honoured `**No diff:**` marker, a phase whose heading and body cite no
    path at all (no evidence = cannot verify = skip), and a phase none of whose citations
    can name a repo path (uncheckable). A citation is a repo-path candidate when `_touched`
    matches it against the diff or against `git ls-files`; a mixed phase is checked on its
    candidates alone. `tracked_files=None` reads `_tracked_files()` and, when that is
    unavailable, keeps every citation a candidate (fail closed, today's behaviour); an
    explicit empty list means nothing is tracked. Empty when the plan was not found or has
    no parsed phases, so a plan-blind run and every pre-existing PlanInfo literal produce
    nothing. Hold-only: the items flow into arming condition (3) via check();
    fidelity.build_work_list excludes them from the fixer loop.

    `notes` is an out-param like `resolutions` on `check`. Each exemption appends one
    `NO-DIFF-PHASE:` / `UNCHECKABLE-PHASE:` / `NON-REPO-CITATION:` line, and a rejected marker appends
    `NO-DIFF-IGNORED:`. Notes are never returned as items, so they never hold a PR.
    """
    if not plan.found or not plan.phases:
        return []
    deferred = set(plan.deferred_phase_numbers)
    items: list[str] = []
    for ph in plan.phases:
        if ph.bookkeeping or ph.number in deferred:
            continue
        if ph.no_diff_rejected and not ph.no_diff_reason:
            _note(notes, f"NO-DIFF-IGNORED: phase {ph.number} — **No diff:** reason under "
                         f"{NO_DIFF_MIN_REASON} chars; phase checked as unmarked")
        if ph.no_diff_reason:
            _note(notes, f"NO-DIFF-PHASE: {ph.number} — {ph.heading[:80]} "
                         f"(exempt: {ph.no_diff_reason[:80]})")
            continue
        if not ph.evidence_paths:
            continue
        tracked = tracked_files if tracked_files is not None else _tracked_files()
        dropped: list[str] = []
        cands = _repo_path_candidates(ph.evidence_paths, changed_files, tracked, dropped)
        for tok in dropped:
            _note(notes, f"NON-REPO-CITATION: {ph.number} — {tok[:80]} "
                         f"(leading / or ~/ and no exact, suffix, or directory match)")
        if not cands:
            sample = ", ".join(ph.evidence_paths[:3])
            more = f" (+{len(ph.evidence_paths) - 3} more)" if len(ph.evidence_paths) > 3 else ""
            _note(notes, f"UNCHECKABLE-PHASE: {ph.number} — {ph.heading[:80]} "
                         f"(no repo-path citation among {sample}{more})")
            continue
        if any(_touched(p, changed_files) for p in cands):
            continue
        sample = ", ".join(cands[:3])
        more = f" (+{len(cands) - 3} more)" if len(cands) > 3 else ""
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


def _method_declared(name: str, text: str) -> bool:
    """True when `text` declares `name`: a PHP/Python `function name` / `def name`, or a
    bash-style bare `name() {` anchored at start of line (optional diff `+` and indent)
    so a call site like `$this->name()` never counts (backlog#1133)."""
    return re.search(
        rf"(function|def)\s+{re.escape(name)}\b"
        rf"|^[+\s]*{re.escape(name)}\s*\(\s*\)",
        text,
        re.MULTILINE,
    ) is not None


def _method_in_changed_tree(name: str, changed_files: list[str],
                            read_file: Callable[[str], str | None],
                            cache: dict[str, str | None]) -> str | None:
    """The first changed path whose PR-tree text declares `name`, else None.

    Reads ONLY paths from `changed_files`: a method that exists untouched elsewhere in
    the repo must still be MISSING-METHOD, or "write new test X" passes when X was never
    written. Any reader error or None is treated as "not here" (fail closed: the item
    stays). `cache` memoises one read per path across the required-method loop.
    """
    for path in changed_files:
        if path not in cache:
            try:
                cache[path] = read_file(path)
            except Exception:
                cache[path] = None
        text = cache[path]
        if text and _method_declared(name, text):
            return path
    return None


def check(plan: PlanInfo, changed_files: list[str], diff_body: str = "",
          phase5_status: str | None = None,
          resolutions: dict[str, str] | None = None,
          pr_body: str = "",
          tracked_files: list[str] | tuple[str, ...] | None = None,
          notes: list[str] | None = None,
          read_file: Callable[[str], str | None] | None = None) -> list[str]:
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

    tracked_files and notes are forwarded to phase_omission_items (see there); notes,
    when a list is passed, collects the phase exemption lines (including one
    NON-REPO-CITATION: line per dropped citation).

    read_file, when given, is called with each path in changed_files to fetch that file's
    text from the PR tree; a required method the diff hunks never show but a changed file
    declares is then present (PRs #2708, #2772, #2707). None (the default) disables the
    fallback. Only changed files are read, never the whole repo.
    """
    if not plan.found:
        return []
    items: list[str] = _contract_items(plan, changed_files, phase5_status)
    # Runs before the has_matrix gate on purpose: a matrix-less doc/tooling plan still
    # has phases, and a phase that shipped nothing is the same defect either way.
    items.extend(phase_omission_items(plan, changed_files, tracked_files, notes))
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
        tree_cache: dict[str, str | None] = {}
        for m in plan.required_test_methods:
            if _method_declared(m, diff_body):
                continue
            if read_file is not None:
                where = _method_in_changed_tree(m, changed_files, read_file, tree_cache)
                if where is not None:
                    _note(notes, f"METHOD-IN-TREE: {m} — declared in {where} "
                                 f"(changed file; the diff hunks omitted the declaration line)")
                    continue
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
    never enter the set. `--no-renames` lists a rename as its old path plus its new
    path, matching `LiveGit.conformance_files` so this seam and post-plan Phase 5.0
    agree on a renamed Critical File.
    """
    seen: list[str] = []
    for args in (["diff", "--no-renames", "--name-only", "origin/master...HEAD"],
                 ["diff", "--no-renames", "--name-only", "HEAD"],
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
