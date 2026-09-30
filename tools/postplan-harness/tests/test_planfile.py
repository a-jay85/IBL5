import contextlib
import io
import itertools
import os
import pathlib
import pytest
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.planfile import (EXEMPT_RE, _ABS_PREFIX_RE, _normalise_cf_path,
                              _strip_fenced,
                              count_executable_matrix_rows,
                              frontmatter_auto_merge_false,
                              frontmatter_autonomy_contract, locate_plan,
                              parse_critical_files, parse_matrix,
                              parse_deferred_phase_numbers, parse_phases,
                              parse_required_test_methods)
from harness.state import PhaseInfo, PlanInfo, RunResult

PLAN = """---
status: ready
auto_merge: false
---

# My Plan

## Security
CSRF token on the new POST endpoint via `CsrfGuard::validateSubmittedToken`.

## Verification Matrix

| Behavior | Test type | Test |
|---|---|---|
| saves row | PHPUnit | `ibl5/tests/Unit/EventLoggerTest.php` |
| page loads | E2E | `ibl5/tests/e2e/admin/events.spec.ts` |
| looks right | Truly-manual | eyeball the dashboard |

## Critical Files

- `ibl5/classes/EventLogger.php` — new logger class
- `ibl5/schema.sql` (read-only reference) — do not edit
"""


def test_frontmatter_gate():
    assert frontmatter_auto_merge_false(PLAN)
    assert not frontmatter_auto_merge_false(PLAN.replace("auto_merge: false", "auto_merge: true"))
    # body mention without line-1 frontmatter must NOT self-select
    assert not frontmatter_auto_merge_false("# doc\nuse `auto_merge: false` in plans\n")


def test_matrix_and_critical_files():
    planned, manual = parse_matrix(PLAN)
    assert planned == ["ibl5/tests/Unit/EventLoggerTest.php", "ibl5/tests/e2e/admin/events.spec.ts"]
    assert len(manual) == 1 and "eyeball" in manual[0].raw
    cf = parse_critical_files(PLAN)
    assert cf[0][0] == "ibl5/classes/EventLogger.php" and not cf[0][2]
    assert cf[1][0] == "ibl5/schema.sql" and cf[1][2]  # "(read-only reference)" -> exempt


def test_matrix_rejects_package_name_and_url_phantoms():
    """Two live rows from the plan corpus. e2e-phase-matrix's row named the npm
    package `@playwright/test` and its `lint:e2e` command tripped the E2E type match,
    so conformance reported the package missing from every diff. The other row's
    Behavior cell held a URL ahead of the real test path. Only the first backticked
    candidate is ever considered, so that row now plans nothing instead of a phantom."""
    content = (
        "## Verification Matrix\n\n"
        "| # | Behavior | Test type | When | Test |\n"
        "|---|---|---|---|---|\n"
        "| 8 | `fixtures/phase.ts` uses no value import of `@playwright/test` "
        "| CLI-executable | post-impl | `cd ibl5 && bun run lint:e2e` |\n"
        "| 13 | **NEG** `original_url` `https://evil.test/x` rejected | PHPUnit | post-impl "
        "| `ibl5/tests/BugPipeline/AttachmentInputValidatorTest.php` — NEW |\n"
    )
    planned, _ = parse_matrix(content)
    assert planned == []


def test_matrix_ignores_fenced_rows():
    """A matrix row inside a fenced block is illustration, not a declaration.

    Live regression: ~/claude-plans/critical-files-parser-unification.md carried a
    `tests/X.php` scaffold row inside a ```bash fixture block. parse_matrix read it
    as a planned test, Phase 5.0 emitted `MISSING: tests/X.php`, and arm condition
    (3) held the PR on a path no diff could ever contain. Condition (1) has the same
    exposure through truly_manual_rows, so both are pinned here.
    """
    fenced = PLAN + """
## Appendix — how to write a matrix

````markdown
| Behavior | Test type | Test |
|---|---|---|
| thing works | PHPUnit | `tests/X.php` |
| looks right | Truly-manual | eyeball it |
````
"""
    planned, manual = parse_matrix(fenced)
    # identical to the unfenced plan: the appendix contributes nothing
    assert planned == parse_matrix(PLAN)[0]
    assert len(manual) == len(parse_matrix(PLAN)[1])
    assert "tests/X.php" not in planned
    assert not any("eyeball it" in row.raw for row in manual)


_MATRIX_HEAD = (
    "| # | What to verify | Test type | Timing | Test file / location |\n"
    "|---|----------------|-----------|--------|----------------------|\n"
)

_DOCS_ONLY_MATRIX = (
    "# Docs only\n\n## Verification Matrix\n\n" + _MATRIX_HEAD
    + "| 1 | Wording reads correctly | Doc review | post-impl | `docs/x.md` |\n"
    "| 2 | Links resolve | Static | post-impl | `bin/check-links` |\n"
)

_MIXED_MATRIX = (
    "# Mixed\n\n## Verification Matrix\n\n" + _MATRIX_HEAD
    + "| 1 | Service returns the row | PHPUnit | pre-impl "
    "| `ibl5/tests/Foo/FooServiceTest.php` |\n"
    "| 2 | Page renders the row | E2E | post-impl "
    "| `ibl5/tests/e2e/tests/foo.spec.ts` |\n"
    "| 3 | Lint stays clean | CLI-executable | post-impl | `grep -c x f \\| wc -l` |\n"
    "| 4 | Layout feels right | Truly-manual | post-impl | open the page and look |\n"
    "| 5 | Wording reads correctly | Doc review | post-impl | `docs/x.md` |\n"
    "\n## Notes\n\n"
    "| Item | Detail |\n|------|--------|\n"
    "| Coverage | E2E is deferred to a later plan |\n"
)

_NO_MATRIX = (
    "# No matrix\n\n## Critical Files\n\n- `ibl5/classes/Foo/FooService.php`\n\n"
    "## Notes\n\n| Item | Detail |\n|------|--------|\n| Scope | One service |\n"
)


def test_executable_count_docs_only_matrix_is_zero():
    # A real matrix with only non-taxonomy types is 0, never None.
    count = count_executable_matrix_rows(_DOCS_ONLY_MATRIX)
    assert count == 0
    assert count is not None


def test_executable_count_ignores_critical_files_table():
    content = (
        _DOCS_ONLY_MATRIX
        + "\n## Critical Files\n\n"
        "| File | Why |\n|------|-----|\n"
        "| `ibl5/tests/Foo/FooTest.php` | PHPUnit coverage for Foo |\n"
    )
    assert count_executable_matrix_rows(content) == 0


def test_executable_count_ignores_fenced_matrix():
    fenced_block = (
        "\n## Appendix\n\n```markdown\n" + _MATRIX_HEAD
        + "| 1 | Thing works | PHPUnit | pre-impl | `ibl5/tests/Foo/FooTest.php` |\n```\n"
    )
    assert count_executable_matrix_rows(_DOCS_ONLY_MATRIX + fenced_block) == 0
    # Only header is inside a fence: no real matrix at all.
    fenced_only = "# Plan\n" + fenced_block
    assert count_executable_matrix_rows(fenced_only) is None


def test_executable_count_mixed_matrix():
    # PHPUnit + E2E + CLI-executable (escaped-pipe location) = 3; Truly-manual and
    # Doc review add nothing, and the later Notes table naming E2E is out of scope.
    assert count_executable_matrix_rows(_MIXED_MATRIX) == 3


def test_executable_count_visual_regression_counts():
    content = (
        "# Visual\n\n## Verification Matrix\n\n" + _MATRIX_HEAD
        + "| 1 | Roster page pixels unchanged | Visual-regression | post-impl "
        "| `ibl5/tests/e2e/visual/roster.spec.ts` |\n"
    )
    assert count_executable_matrix_rows(content) == 1


def test_executable_count_none_without_matrix():
    assert count_executable_matrix_rows(_NO_MATRIX) is None


# Committed corpus: tests/fixtures/matrix_count/ (one .plan.txt per counter shape).
_FIXTURE_DIR = pathlib.Path(__file__).parent / "fixtures" / "matrix_count"


@pytest.mark.parametrize("name,expected", [
    ("docs_only.plan.txt", 0),
    ("critical_files_phpunit.plan.txt", 0),
    ("fenced_matrix.plan.txt", 0),
    ("fenced_only.plan.txt", None),
    ("mixed.plan.txt", 3),
    ("visual_regression_only.plan.txt", 1),
    ("misaligned_columns.plan.txt", 1),
    ("no_matrix.plan.txt", None),
])
def test_executable_count_fixture_corpus(name, expected):
    content = (_FIXTURE_DIR / name).read_text(encoding="utf-8")
    assert count_executable_matrix_rows(content) == expected


def test_locate_plan_sets_executable_row_count():
    info = locate_plan("x", content_override=_MIXED_MATRIX)
    assert info.has_matrix is True
    assert info.executable_row_count == 3
    blind = locate_plan("x", content_override=_NO_MATRIX)
    assert blind.has_matrix is False
    assert blind.executable_row_count is None


def test_matrix_fence_width_awareness():
    """A 4-backtick block wrapping 3-backtick inner ones must not invert parity.

    Width-blind `in_fence = !in_fence` closes on the inner ``` and re-opens on the
    outer closer, leaving the REAL matrix below it swallowed. Per CommonMark a
    closing fence must be at least as long as its opener.
    """
    nested = """## Verification Matrix

````markdown
```bash
echo hi
```
| Behavior | Test type | Test |
|---|---|---|
| fake | PHPUnit | `tests/Phantom.php` |
````

| Behavior | Test type | Test |
|---|---|---|
| real | PHPUnit | `ibl5/tests/Unit/RealTest.php` |
"""
    planned, _ = parse_matrix(nested)
    assert planned == ["ibl5/tests/Unit/RealTest.php"]


def test_locate_plan_missing_and_override():
    assert not locate_plan("no-such-slug", plans_dir="/nonexistent").found
    info = locate_plan("x", content_override=PLAN)
    assert info.found and info.auto_merge_false and info.has_matrix and info.has_security


def test_conformance():
    plan = locate_plan("x", content_override=PLAN)
    clean = conformance.check(plan, ["ibl5/tests/Unit/EventLoggerTest.php",
                                     "ibl5/tests/e2e/admin/events.spec.ts",
                                     "ibl5/classes/EventLogger.php"])
    assert clean == []
    missing = conformance.check(plan, ["ibl5/classes/EventLogger.php"])
    assert len(missing) == 2 and all(m.startswith("MISSING:") for m in missing)
    # exempt critical file (schema.sql) never demands a diff appearance
    assert not any("schema.sql" in m for m in missing)


REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
LIB = os.path.join(REPO_ROOT, "bin", "lib", "critical-files.sh")

# Shared cross-parser fixture. Every shape here is drawn from the real
# ~/claude-plans corpus; a01-a16 must be EXEMPT, b01-b12 must be MUST_APPEAR
# except b08. The 4-backtick outer fence (before the real heading) is the
# width-aware fence regression: a naive parity toggle would swallow the whole
# section. The same fixture drives the shell-side agreement test in
# bin/test-postplan-arm-conditions, so a divergence fails on both sides.
AGREEMENT_PLAN = """````bash
# Outer 4-backtick fence: contains an inner 3-backtick block and a decoy
# ## Critical Files heading. Pre-fix, the naive parity toggle (in_fence =
# !in_fence on any 3+-backtick fence) toggled once on this outer fence and
# never recovered, swallowing the real section entirely (11 entries → 0).
```text
inner 3-backtick block — fence_len=3 < 4, so does NOT close the outer fence
```
## Critical Files

- `decoy01` (reference)
- `decoy02` — decoy must-appear inside fence, must be ignored

````

## Critical Files

- `a01` (reference)
- `a02` (read-only)
- `a03` (read-only reference)
- `a04` (Reference)
- `a05` (verify)
- `a06` (verification)
- `a07` (template)
- `a08` (no-edit)
- `a09` (no-change)
- `a10` (unchanged)
- `a11` (context)
- `a12` (conditional)
- `a13` (reference - pattern to mirror; unchanged)
- `a14` (cat reference)
- `a15` (out-of-repo - verify in-place, not in git diff)
- `a16` (conditional — only if judged)
- `b01` - add the context menu helper
- `b02` - update so we can verify the arming path
- `b03` - reference only, do not edit
- `b04` - template rendering for the new page
- `b05` (new) - the loader that reads context from disk
- `b06` - only if the skill op changes it
- `b07` (only if the skill op changes it)
  - `b08` (reference)
  - `b09` - indented change target
- `b10` (conditional Phase 4 only)
- `b11` (filename references the affected class)
- `b12` (the referenced file is deleted)
"""


def _classify(plan_text):
    return [("EXEMPT:" if ex else "MUST_APPEAR:") + p
            for p, _ann, ex in parse_critical_files(plan_text)]


def test_false_exempt_regression():
    """#923 failure mode: a canonical keyword in annotation PROSE must not exempt."""
    plan = ("## Critical Files\n\n"
            "- `ibl5/a.php` - provides context for the migration\n"
            "- `ibl5/b.php` - reference only, do not edit\n"
            "- `ibl5/c.php` - update so we can verify the arming path\n")
    cf = parse_critical_files(plan)
    assert [p for p, _a, _e in cf] == ["ibl5/a.php", "ibl5/b.php", "ibl5/c.php"]
    assert not any(ex for _p, _a, ex in cf), "prose keyword must never exempt"


def test_conditional_marker_exempt():
    """The 2026-07-26 false MISSING-FILE: the marked form exempts, the prose form does not."""
    plan = ("## Critical Files\n\n"
            "- `ibl5/docs/README.md` (conditional) - only if the op changes it\n"
            "- `ibl5/docs/other.md` - only if the op changes it\n")
    assert _classify(plan) == ["EXEMPT:ibl5/docs/README.md",
                               "MUST_APPEAR:ibl5/docs/other.md"]


def test_read_only_reference_exempt():
    """Multi-word and explanatory-tail markers stay exempt; a bare `(new)` does not."""
    plan = ("## Critical Files\n\n"
            "- `x1` (read-only reference)\n"
            "- `x2` (read-only reference - do not edit)\n"
            "- `x3` (new) - the loader that reads context from disk\n")
    assert _classify(plan) == ["EXEMPT:x1", "EXEMPT:x2", "MUST_APPEAR:x3"]


def test_lib_sync(tmp_path):
    """Python<->shell agreement: both parsers must classify the fixture identically."""
    assert os.path.isfile(LIB), "canonical lib missing: " + LIB
    f = tmp_path / "agreement.md"
    f.write_text(AGREEMENT_PLAN)
    proc = subprocess.run(
        ["bash", "-c", 'source "$1" && cf_parse_section "$2"', "_", LIB, str(f)],
        capture_output=True, text=True, check=True)
    shell = [ln for ln in proc.stdout.splitlines() if ln.strip()]
    assert _classify(AGREEMENT_PLAN) == shell, (
        "parser divergence\n python: %s\n shell:  %s" % (_classify(AGREEMENT_PLAN), shell))
    assert sum(1 for ln in shell if ln.startswith("EXEMPT:")) == 17
    assert sum(1 for ln in shell if ln.startswith("MUST_APPEAR:")) == 11


# backlog#1211: absolute-path parity. Each entry is (path-as-written, expected
# repo-relative form). The shell lib and _normalise_cf_path must agree on every
# one. Relative paths are covered by AGREEMENT_PLAN / test_lib_sync.
ABS_PARITY_CASES = [
    ("/Users/x/GitHub/IBL5/ibl5/classes/Foo.php", "ibl5/classes/Foo.php"),
    ("/Users/x/GitHub/IBL5-worktrees/my-slug/bin/check-plan", "bin/check-plan"),
    ("/a/IBL5/b/IBL5/c", "c"),                      # greedy: LAST root wins
    ("/a/IBL5-worktrees/slug/IBL5/d", "d"),         # worktree then plain root
    ("/a/IBL5/IBL5-worktrees/slug/e", "e"),         # plain root then worktree
    ("/a/IBL5-worktrees/x", "/a/IBL5-worktrees/x"),  # no slash after slug
    ("/a/IBL5", "/a/IBL5"),                         # no trailing slash
    ("/a/ibl5/y", "/a/ibl5/y"),                     # case-sensitive
    ("/Users/x/claude-plans/z.md", "/Users/x/claude-plans/z.md"),  # out-of-repo
    ("docs/IBL5/rel.md", "docs/IBL5/rel.md"),       # relative: untouched
]


def _abs_plan(cases):
    body = "".join("- `%s`%s\n" % (p, " (reference)" if i % 2 else "")
                   for i, (p, _e) in enumerate(cases))
    return "## Critical Files\n\n" + body


def _shell_parse(planfile_path, fn="cf_parse_section"):
    proc = subprocess.run(
        ["bash", "-c", 'source "$1" && %s "$2"' % fn, "_", LIB, str(planfile_path)],
        capture_output=True, text=True, check=True)
    return [ln for ln in proc.stdout.splitlines() if ln.strip()]


def test_lib_sync_absolute_paths(tmp_path):
    """backlog#1211: shell cf_parse_section strips the same IBL5 prefix _normalise_cf_path does."""
    plan = _abs_plan(ABS_PARITY_CASES)
    f = tmp_path / "abs.md"
    f.write_text(plan)
    shell = _shell_parse(f)
    python = _classify(plan)
    assert python == shell, "parser divergence\n python: %s\n shell:  %s" % (python, shell)
    # Agreement on the RIGHT answer, not merely mutual agreement.
    want = [("EXEMPT:" if i % 2 else "MUST_APPEAR:") + e
            for i, (_p, e) in enumerate(ABS_PARITY_CASES)]
    assert shell == want
    for p, e in ABS_PARITY_CASES:
        assert _normalise_cf_path(p) == e
    # The raw entry point is the pre-#1211 parser: paths exactly as written.
    raw = _shell_parse(f, "cf_parse_section_raw")
    assert raw == [("EXEMPT:" if i % 2 else "MUST_APPEAR:") + p
                   for i, (p, _e) in enumerate(ABS_PARITY_CASES)]


def test_lib_empty_normalisation_divergence_pinned(tmp_path):
    """A bare root entry: Python yields "", the shell keeps the path as written.

    Deliberate and confined to a plan-authoring defect that gate [F] rejects at
    plan time. An empty MUST_APPEAR row would be skipped by Phase 5.0 (dropping a
    hold), so the shell fails closed by leaving the entry unchanged.
    """
    root = "/Users/x/GitHub/IBL5/"
    assert _normalise_cf_path(root) == ""
    f = tmp_path / "root.md"
    f.write_text("## Critical Files\n\n- `%s`\n" % root)
    assert _shell_parse(f) == ["MUST_APPEAR:" + root]


def test_lib_abs_prefix_pattern_sync():
    """Byte-level drift guard for the prefix regex, sibling of test_lib_pattern_sync."""
    m = re.search(r"^CF_ABS_PREFIX_ERE='([^']+)'", open(LIB).read(), re.M)
    assert m, "CF_ABS_PREFIX_ERE not found in " + LIB
    assert _ABS_PREFIX_RE.pattern.replace("(?:", "(") == m.group(1)


_CORPUS_SNAP = r'''
source "$1"
for f in "$HOME"/claude-plans/*.md; do
    cf_parse_section_raw "$f" | awk -v p="$(basename "$f")" '{ print p "\t" $0 }' >> "$2"
    cf_parse_section "$f" | awk -v p="$(basename "$f")" '{ print p "\t" $0 }' >> "$3"
done
'''


@pytest.mark.skipif(not os.path.isdir(os.path.expanduser("~/claude-plans")),
                    reason="needs the machine-local ~/claude-plans corpus")
def test_lib_corpus_normalisation_diff(tmp_path):
    """backlog#1211: over every real plan, only in-repo absolute rows move, and each
    moves to exactly what _normalise_cf_path yields.

    cf_parse_section_raw is byte-identical to the pre-#1211 parser, so it is the
    stable "before" side and the test stays valid after this change merges.
    """
    before_tsv = tmp_path / "before.tsv"
    after_tsv = tmp_path / "after.tsv"
    before_tsv.write_text("")
    after_tsv.write_text("")
    subprocess.run(["bash", "-c", _CORPUS_SNAP, "_", LIB, str(before_tsv), str(after_tsv)],
                   check=True, capture_output=True, text=True)
    before = before_tsv.read_text().splitlines()
    after = after_tsv.read_text().splitlines()
    # Row 1: the baseline really contains the legacy absolute rows under test.
    assert len(before) >= 5807, len(before)
    n_abs = sum(1 for ln in before
                if ln.split("\t", 1)[1].split(":", 1)[1].startswith("/"))
    assert n_abs >= 117, n_abs
    # Row 15: per-row comparison.
    assert len(after) == len(before)
    changed = out_of_repo = 0
    for old_row, new_row in zip(before, after):
        plan, old_entry = old_row.split("\t", 1)
        plan2, new_entry = new_row.split("\t", 1)
        old_kind, old = old_entry.split(":", 1)
        new_kind, new = new_entry.split(":", 1)
        assert (plan, old_kind) == (plan2, new_kind), (old_row, new_row)
        assert new != "", "empty path emitted: " + old_row
        if not old.startswith("/"):
            assert new == old, ("relative row changed", old_row, new_row)
            continue
        exp = _normalise_cf_path(old)
        if exp == "":
            assert new == old, ("bare-root row must pass through", old_row, new_row)
        elif exp == old:
            assert new == old, ("out-of-repo row changed", old_row, new_row)
            out_of_repo += 1
        else:
            assert new == exp, ("differs from _normalise_cf_path(old)", old_row, new_row)
            changed += 1
    assert changed >= 103 and out_of_repo >= 14, (changed, out_of_repo)


def test_lib_pattern_sync():
    """Byte-level drift guard: the two patterns are one string modulo regex dialect."""
    m = re.search(r"^CF_EXEMPT_PATTERN='([^']+)'", open(LIB).read(), re.M)
    assert m, "CF_EXEMPT_PATTERN not found in " + LIB
    ere = m.group(1).replace("[(]", r"\(").replace("[)]", r"\)")
    assert EXEMPT_RE.pattern.replace("(?:", "(") == ere


# ---------------------------------------------------------------------------
# Blockquote-fence parity tests
#
# These tests pin the bq_fence state machine added to bin/lib/critical-files.sh
# and planfile._strip_fenced for class consistency with bin/check-plan-staleness
# (PR #1809).  The blindspot is INERT BY CONSTRUCTION today — every downstream
# consumer is ^-anchored, so a >-prefixed line can never satisfy CF_LINE_PATTERN
# or the ^\s*-\s*` shape — but the fix must hold so a future unanchored consumer
# doesn't silently re-open it.
#
# Green/red summary:
#   test_bq_quoted_fence_stripped_python  — RED before Edit 3, GREEN after
#   test_bq_quoted_fence_stripped_shell   — RED before Edit 1, GREEN after
#   test_bq_prose_retained                — GREEN before and after (no fix needed)
#   test_bq_confinement_unclosed_does_not_swallow — GREEN before and after; guards
#       against a naive shared-state fix that merges bq_fence into in_fence
#   test_bq_fence_unbalanced_unaffected   — GREEN before and after; a >` line
#       yielded n=0 before the fix so in_fence was never touched
#   test_bq_shell_python_section_agreement:
#       (a) py == shell — GREEN before and after (same blindspot, then same fix)
#       (b) absolute value == expected — RED before Edits 1/3, GREEN after
# ---------------------------------------------------------------------------

# Fixture: section with a >-blockquote fence wrapping a phantom bullet, plus a
# real entry outside the quote.  The fence open/close lines carry no language
# specifier so the backtick run is exactly 3 — the minimum to open a fence.
BQ_FENCE_PLAN = """\
## Critical Files

> ```
> - `ibl5/classes/PhantomInQuotedFence.php`
> ```
- `ibl5/classes/RealEntry.php` — CHANGE.
"""

# Fixture: an unclosed quoted fence precedes the section, separated by a blank
# line (which ends the CommonMark blockquote).  A naive shared-state fix that
# merges bq_fence into in_fence without a blank-line reset would leave in_fence=1
# at the heading, swallowing the entire section.  Confinement means any non->
# line resets bq state before the heading is reached.
BQ_CONFINEMENT_PLAN = """\
> ```bash
> snippet

## Critical Files
- `ibl5/classes/RealEntry.php` — CHANGE.
"""

# Fixture: a >-prefixed prose line (no fence markers) inside a section.
# A blockquote is not an exempt position; the line must survive fence stripping.
BQ_PROSE_PLAN = """\
## Critical Files

> This is a blockquote prose note about the change.
- `ibl5/classes/RealEntry.php` — CHANGE.
"""


def _py_section(text: str, want: str = "Critical Files") -> list[str]:
    """Lines inside the named section as seen by _strip_fenced, blanks included."""
    out, in_sec = [], False
    for ln in _strip_fenced(text):
        if re.match(r"^##\s*" + re.escape(want), ln):
            in_sec = True
            continue
        if re.match(r"^## ", ln):
            in_sec = False
            continue
        if in_sec:
            out.append(ln)
    return out


def test_bq_quoted_fence_stripped_python():
    """_strip_fenced drops lines inside a >-blockquote fence.

    RED before Edit 3, GREEN after.  Before the fix lstrip() leaves > in place,
    so '> ```' counts 0 backticks and the fence never opens; the three quoted
    lines (open, phantom bullet, close) are appended unchanged.
    """
    lines = _strip_fenced(BQ_FENCE_PLAN)
    joined = "\n".join(lines)
    assert "PhantomInQuotedFence.php" not in joined, (
        "phantom path inside a quoted fence leaked into _strip_fenced output")
    assert "RealEntry.php" in joined, "real entry was stripped — overcorrection"


def test_bq_quoted_fence_stripped_shell(tmp_path):
    """cf_section_named drops lines inside a >-blockquote fence.

    RED before Edit 1, GREEN after.  Before the fix sub(/^[[:space:]]*/, ...)
    leaves > in place so '> ```' counts 0 backticks and the fence never opens.
    """
    f = tmp_path / "bq_fence.md"
    f.write_text(BQ_FENCE_PLAN)
    proc = subprocess.run(
        ["bash", "-c",
         'source "$1" && cf_section_named "$2" "Critical Files"',
         "_", LIB, str(f)],
        capture_output=True, text=True, check=True)
    assert "PhantomInQuotedFence.php" not in proc.stdout, (
        "phantom path inside a quoted fence leaked into cf_section_named output")
    assert "RealEntry.php" in proc.stdout, "real entry missing — overcorrection"


def test_bq_prose_retained(tmp_path):
    """>-prefixed prose (no fence markers) is retained by both parsers.

    A blockquote is not an exempt position.  GREEN before and after the fix.
    """
    f = tmp_path / "bq_prose.md"
    f.write_text(BQ_PROSE_PLAN)
    # Python
    joined = "\n".join(_strip_fenced(BQ_PROSE_PLAN))
    assert "blockquote prose note" in joined, (
        "_strip_fenced incorrectly stripped a blockquote prose line")
    # Shell
    proc = subprocess.run(
        ["bash", "-c",
         'source "$1" && cf_section_named "$2" "Critical Files"',
         "_", LIB, str(f)],
        capture_output=True, text=True, check=True)
    assert "blockquote prose note" in proc.stdout, (
        "cf_section_named incorrectly stripped a blockquote prose line")


def test_bq_confinement_unclosed_does_not_swallow(tmp_path):
    """An unclosed >-quoted fence must not swallow the section that follows.

    GREEN before AND after: the current code never opens a bq_fence so it
    never swallows anything; the fix must not regress this.  This guards against
    a naive shared-state fix that merges bq_fence into in_fence without the
    blank-line reset — that would fail open, leaving in_fence=1 through the
    heading and swallowing the real entry.
    """
    f = tmp_path / "bq_confinement.md"
    f.write_text(BQ_CONFINEMENT_PLAN)
    # Shell
    proc = subprocess.run(
        ["bash", "-c",
         'source "$1" && cf_section_named "$2" "Critical Files"',
         "_", LIB, str(f)],
        capture_output=True, text=True, check=True)
    assert "RealEntry.php" in proc.stdout, (
        "confinement failed: unclosed quoted fence swallowed Critical Files section (shell)")
    # Python
    lines = _strip_fenced(BQ_CONFINEMENT_PLAN)
    assert any("RealEntry.php" in ln for ln in lines), (
        "_strip_fenced confinement failed: unclosed quoted fence swallowed later content")


def test_bq_fence_unbalanced_unaffected(tmp_path):
    """cf_fence_unbalanced exit code is unchanged by the blockquote branch.

    Exit-code polarity: exit 0 = fence still OPEN at EOF (unbalanced);
    non-zero = fence CLOSED (balanced).  GREEN before and after: before the fix
    a '> ```' line yielded n=0 and never touched in_fence, so the exit was
    already correct — this test pins that invariant against future regressions.
    """
    # Fence only inside a quote — bq_fence=1, in_fence=0 → exit !0 = 1 = balanced
    f_quote = tmp_path / "bq_only.md"
    f_quote.write_text("> ```bash\n> snippet\n")
    proc = subprocess.run(
        ["bash", "-c", 'source "$1" && cf_fence_unbalanced "$2"', "_", LIB, str(f_quote)],
        capture_output=True, text=True)
    assert proc.returncode != 0, (
        "cf_fence_unbalanced: unclosed quoted fence incorrectly reported as unbalanced; "
        "bq_fence must not affect in_fence (exit should be non-zero = balanced)")
    # Genuinely unclosed document-level fence → in_fence=1 → exit !1 = 0 = unbalanced
    f_real = tmp_path / "real_open.md"
    f_real.write_text("```bash\nsome code\n")
    proc2 = subprocess.run(
        ["bash", "-c", 'source "$1" && cf_fence_unbalanced "$2"', "_", LIB, str(f_real)],
        capture_output=True, text=True)
    assert proc2.returncode == 0, (
        "cf_fence_unbalanced: real unclosed fence was not detected (exit should be 0 = unbalanced)")


def test_bq_shell_python_section_agreement(tmp_path):
    """Shell and Python agree on which section lines survive for BQ_FENCE_PLAN.

    Two assertions:
      (a) Agreement: non-blank lines from cf_section_named == non-blank lines
          from _py_section.  GREEN before and after — both have the same blindspot
          before the fix and the same correction after.
      (b) Absolute value: non-blank section lines == exactly the real entry.
          RED before Edits 1/3 (both include the three phantom > lines); GREEN
          after (both strip them).
    """
    f = tmp_path / "bq_agree.md"
    f.write_text(BQ_FENCE_PLAN)
    proc = subprocess.run(
        ["bash", "-c",
         'source "$1" && cf_section_named "$2" "Critical Files"',
         "_", LIB, str(f)],
        capture_output=True, text=True, check=True)
    shell_lines = [ln for ln in proc.stdout.splitlines() if ln.strip()]
    py_lines = [ln for ln in _py_section(BQ_FENCE_PLAN) if ln.strip()]
    # (a) agreement — mirrors test_lib_sync's spirit; GREEN before and after
    assert py_lines == shell_lines, (
        "parser divergence on BQ_FENCE_PLAN\n py:    %s\n shell: %s" % (py_lines, shell_lines))
    # (b) absolute value — RED before fix; GREEN after
    expected = ["- `ibl5/classes/RealEntry.php` — CHANGE."]
    assert py_lines == expected, (
        "unexpected section content\n got:      %s\n expected: %s" % (py_lines, expected))


# ---------------------------------------------------------------------------
# Variant-resolution specs (Phases 1, 2, 3, 4, 6)
# ---------------------------------------------------------------------------

def _mkplans(tmp_path, *names):
    """Create plan files; return the dir as str for plans_dir=."""
    for n in names:
        (tmp_path / n).write_text("---\nauto_merge: false\n---\n# " + n + "\n")
    return str(tmp_path)


def test_variant_single_plan_is_byte_identical(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.found
    assert info.path == str(tmp_path / "my-slug.md")
    assert getattr(info, "variant_selection", None) is None
    assert getattr(info, "rejected", []) == []


def test_variant_highest_of_three(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-2.md", "my-slug-3.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.path.endswith("my-slug-3.md")


def test_variant_numeric_not_lexical(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-2.md", "my-slug-10.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.path.endswith("my-slug-10.md"), "lexical sort picked %s" % info.path


def test_variant_shared_context_excluded(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-shared-context.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.path.endswith("my-slug.md")
    assert "my-slug-shared-context.md" not in getattr(info, "rejected", [])
    assert getattr(info, "variant_selection", None) is None


def test_variant_sibling_unit_excluded(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-1-unit.md",
                         "my-slug-1a-trading-pins.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.path.endswith("my-slug.md")
    assert getattr(info, "variant_selection", None) is None


def test_variant_explicit_path_wins(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-2.md", "my-slug-3.md")
    info = locate_plan("my-slug", plans_dir=plans_dir,
                       explicit_path=str(tmp_path / "my-slug.md"))
    assert info.path == str(tmp_path / "my-slug.md")
    assert getattr(info, "variant_selection", None) is None


def test_variant_no_plan_at_all(tmp_path):
    info = locate_plan("nonexistent", plans_dir=str(tmp_path))
    assert not info.found
    assert info.path == ""


def test_variant_warning_goes_to_stderr(tmp_path):
    buf = io.StringIO()
    with contextlib.redirect_stderr(buf):
        info = locate_plan("my-slug",
                           plans_dir=_mkplans(tmp_path, "my-slug.md", "my-slug-2.md"))
    err = buf.getvalue()
    assert "my-slug.md" in err
    assert "my-slug-2.md" in err
    assert any("SELECTED" in ln and "my-slug-2.md" in ln for ln in err.splitlines())
    assert "--plan" in err

    # silence assertion: single plan emits nothing
    tmp2 = tmp_path / "single"
    tmp2.mkdir()
    buf2 = io.StringIO()
    with contextlib.redirect_stderr(buf2):
        locate_plan("my-slug", plans_dir=_mkplans(tmp2, "my-slug.md"))
    assert buf2.getvalue() == ""


def test_variant_selection_fields_populated(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-3.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.variant_selection == "highest"
    assert "my-slug.md" in info.rejected


# Phase 2 — to_json() strip test
def test_to_json_strips_empty_variant_fields():
    from harness.state import Classification
    res = RunResult(terminal="shipped-held", slug="x",
                    plan=PlanInfo(found=True, path="x.md"))
    d = __import__("json").loads(res.to_json())
    assert "variant_selection" not in d["plan"]
    assert "rejected" not in d["plan"]

    res.plan.variant_selection = "highest"
    res.plan.rejected = ["x.md"]
    d2 = __import__("json").loads(res.to_json())
    assert d2["plan"]["variant_selection"] == "highest"
    assert d2["plan"]["rejected"] == ["x.md"]

    res2 = RunResult(terminal="failed", slug="y", plan=None)
    out = res2.to_json()
    assert not out or __import__("json").loads(out)["plan"] is None


# Phase 3 boundary tests
def test_variant_bare_missing_highest_still_wins(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug-2.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.found
    assert info.path.endswith("my-slug-2.md")
    assert info.variant_selection == "highest"
    assert info.rejected == []


def test_variant_unreadable_dir_is_plan_blind(tmp_path):
    info = locate_plan("s", plans_dir=str(tmp_path / "gone"))
    assert not info.found


def test_variant_regex_special_chars_in_slug(tmp_path):
    plans_dir = _mkplans(tmp_path, "feat.v2+x.md", "feat.v2+x-2.md",
                         "featXv2Yx-3.md")
    info = locate_plan("feat.v2+x", plans_dir=plans_dir)
    assert info.path.endswith("feat.v2+x-2.md")
    assert not info.path.endswith("featXv2Yx-3.md")


# ---------------------------------------------------------------------------
# Slug-drift resolution tests (a–e)
# ---------------------------------------------------------------------------

def test_slug_drift_exact_wins_over_prefix(tmp_path):
    """(a) exact {slug}.md present AND plan-{slug}.md present -> exact wins, slug_drift == ""."""
    plans_dir = _mkplans(tmp_path, "my-slug.md", "plan-my-slug.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.found
    assert info.path.endswith("my-slug.md")
    assert info.slug_drift == ""


def test_slug_drift_adopted_when_only_prefixed(tmp_path):
    """(b) only plan-{slug}.md present -> adopted, found True, slug_drift == 'plan-<slug>.md', path points at it."""
    plans_dir = _mkplans(tmp_path, "plan-my-slug.md")
    buf = io.StringIO()
    with contextlib.redirect_stderr(buf):
        info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.found
    assert info.slug_drift == "plan-my-slug.md"
    assert info.path == str(tmp_path / "plan-my-slug.md")
    err = buf.getvalue()
    assert "slug drift" in err
    assert "plan-my-slug.md" in err
    assert "condition 13" in err


def test_slug_drift_suffix_anchor_excludes_shared_context(tmp_path):
    """(c) only {slug}-shared-context.md present -> NOT adopted (found False, slug_drift == "")."""
    plans_dir = _mkplans(tmp_path, "my-slug-shared-context.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert not info.found
    assert info.slug_drift == ""


def test_slug_drift_ambiguous_stays_blind(tmp_path):
    """(d) two drift candidates -> neither adopted, found False, slug_drift == ""."""
    plans_dir = _mkplans(tmp_path, "plan-my-slug.md", "draft-my-slug.md")
    buf = io.StringIO()
    with contextlib.redirect_stderr(buf):
        info = locate_plan("my-slug", plans_dir=plans_dir)
    assert not info.found
    assert info.slug_drift == ""
    err = buf.getvalue()
    assert "ambiguous" in err


def test_slug_drift_numeric_variants_still_win(tmp_path):
    """(e) numeric variants still win as before when {slug}.md/{slug}-2.md exist — slug_drift == ""."""
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-2.md")
    info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.found
    assert info.path.endswith("my-slug-2.md")
    assert info.variant_selection == "highest"
    assert info.slug_drift == ""


# ---------------------------------------------------------------------------
# plan_source audit-trail tests — a cleared condition (13) must be readable as
# "cleared by --plan", not identical to "never fired".
# ---------------------------------------------------------------------------

def test_plan_source_empty_on_exact_slug_match(tmp_path):
    """Exact {slug}.md derivation leaves plan_source "" (result.json stays byte-identical)."""
    info = locate_plan("my-slug", plans_dir=_mkplans(tmp_path, "my-slug.md"))
    assert info.found
    assert info.plan_source == ""


def test_plan_source_variant(tmp_path):
    plans_dir = _mkplans(tmp_path, "my-slug.md", "my-slug-2.md")
    with contextlib.redirect_stderr(io.StringIO()):
        info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.plan_source == "variant"


def test_plan_source_drift(tmp_path):
    """Drift adoption is labelled "drift" and still sets slug_drift (condition 13 holds)."""
    plans_dir = _mkplans(tmp_path, "plan-my-slug.md")
    with contextlib.redirect_stderr(io.StringIO()):
        info = locate_plan("my-slug", plans_dir=plans_dir)
    assert info.plan_source == "drift"
    assert info.slug_drift == "plan-my-slug.md"


def test_plan_source_override_when_stem_matches_slug(tmp_path):
    """--plan naming {slug}.md is a no-op restatement: condition (13) was never going to fire."""
    plans_dir = _mkplans(tmp_path, "my-slug.md")
    info = locate_plan("my-slug", explicit_path=os.path.join(plans_dir, "my-slug.md"))
    assert info.found
    assert info.plan_source == "override"
    assert info.slug_drift == ""


def test_plan_source_override_mismatch_records_bypassed_derivation(tmp_path):
    """--plan naming a stem != branch slug: slug_drift stays "" (13 clear) but the audit says why."""
    plans_dir = _mkplans(tmp_path, "plan-my-slug.md")
    info = locate_plan("my-slug", explicit_path=os.path.join(plans_dir, "plan-my-slug.md"))
    assert info.found
    assert info.plan_source == "override-mismatch"
    assert info.slug_drift == ""   # the hold is cleared by the flag, exactly as designed


def test_plan_source_empty_when_override_path_absent(tmp_path):
    """A --plan path that does not exist is plan-blind — no source label to record."""
    info = locate_plan("my-slug", explicit_path=str(tmp_path / "nope.md"))
    assert not info.found
    assert info.plan_source == ""


def test_to_json_strips_empty_plan_source():
    res = RunResult(terminal="shipped-held", slug="x",
                    plan=PlanInfo(found=True, path="x.md"))
    assert "plan_source" not in __import__("json").loads(res.to_json())["plan"]
    res.plan.plan_source = "override-mismatch"
    d = __import__("json").loads(res.to_json())
    assert d["plan"]["plan_source"] == "override-mismatch"


def test_runner_audit_line_carries_plan_source():
    """The phase1 audit line is the only per-run record an auditor reads without result.json."""
    runner_path = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "runner.py")
    assert "plan_source={plan.plan_source or '-'}" in open(runner_path).read()


# Phase 4 — runner wiring assertion
def test_runner_threads_explicit_path():
    runner_path = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "runner.py")
    src = open(runner_path).read()
    assert "explicit_path=explicit_path" in src
    assert "explicit_path=args.plan" in src


def test_runner_plan_requires_isolated_mode():
    runner_path = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "runner.py")
    r = subprocess.run(
        ["python3", runner_path, "--mode", "replay", "--plan", "/tmp/x.md",
         "--fixture", "/dev/null"],
        capture_output=True, text=True)
    assert r.returncode == 2
    assert "--plan is only valid with --mode isolated" in r.stderr


# Phase 6 — corpus regression
def test_corpus_regression_jsb_native_docs_repo():
    """The measured 2026-07-29 failure: v1 selected where v3 was the live contract."""
    real_dir = os.path.expanduser("~/claude-plans")
    v3 = os.path.join(real_dir, "jsb-native-docs-repo-3.md")
    if not os.path.isfile(v3):
        pytest.skip("corpus fixture jsb-native-docs-repo-3.md not present (CI / fresh clone)")

    info = locate_plan("jsb-native-docs-repo", plans_dir=real_dir)
    assert info.found
    assert info.path == v3, "expected v3, got %s" % info.path
    assert info.variant_selection == "highest"
    assert "jsb-native-docs-repo.md" in info.rejected
    assert "jsb-native-docs-repo-2.md" in info.rejected

    cf = [p for p, _ann, _ex in info.critical_files]
    assert "bin/check-docs" not in cf, \
        "v3 rejects bin/check-docs (Alternatives Considered); only v1 lists it as Critical"
    assert "ibl5/docs/decisions/0097-jsb-native-docs-repo.md" not in cf, \
        "v3 renamed the ADR to 0097-jsb-native-private-docs-repo.md; only v1 has the old slug"


# ---------------------------------------------------------------------------
# Phase 7 — parse_required_test_methods + conformance.check method loop
# ---------------------------------------------------------------------------

def test_required_test_methods_parses_list():
    """A well-formed ## Required Test Methods section returns names in order, backticks stripped."""
    content = """
## Required Test Methods
- `testTradedPlayerAttributedToFromTeamId`
- testNoArgMethod
- `test_snake_case`

## Some Other Section
- ignored
"""
    methods = parse_required_test_methods(content)
    assert methods == ["testTradedPlayerAttributedToFromTeamId", "testNoArgMethod", "test_snake_case"]


def test_required_test_methods_ignores_fenced_example():
    """Fence-negative canary: bullets inside a fenced block yield no methods.

    This is the test that goes red if _strip_fenced is ever dropped from
    parse_required_test_methods. The phantom-MISSING-METHOD: class it prevents
    is the reason Approach B was chosen over a prose regex sweep.
    """
    content = """
## Required Test Methods

```
- `testPhantomInFence`
- testAnotherPhantom
```

"""
    methods = parse_required_test_methods(content)
    assert methods == [], f"Expected [], got {methods!r} — fence stripping lost"


def test_required_test_methods_absent_section_is_empty():
    """A plan with no ## Required Test Methods section returns [] and raises nothing."""
    content = """
## Critical Files
- `ibl5/classes/Foo.php`

## Verification Matrix
| # | What | Type | Timing | File |
"""
    methods = parse_required_test_methods(content)
    assert methods == []


def test_conformance_flags_missing_method():
    """conformance.check flags a required method absent from diff_body with MISSING-METHOD:."""
    plan = PlanInfo(found=True, has_matrix=True, required_test_methods=["testAlpha"])
    items = conformance.check(plan, [], diff_body="function testOther() {}")
    assert len(items) == 1
    assert items[0].startswith("MISSING-METHOD:")
    assert "testAlpha" in items[0]


def test_conformance_accepts_present_method():
    """No MISSING-METHOD: items when diff_body contains the declaration.

    Both spellings are asserted in one body — function (PHP) and def (pytest) —
    which pins the (function|def) alternation shared with Phase 5's bash block.
    A function-only idiom would fail this test.
    """
    plan = PlanInfo(found=True, has_matrix=True,
                    required_test_methods=["testAlpha", "test_beta"])
    diff_body = "function testAlpha() {\n    // impl\n}\ndef test_beta(self):"
    items = conformance.check(plan, [], diff_body=diff_body)
    method_items = [i for i in items if i.startswith("MISSING-METHOD:")]
    assert method_items == [], f"Unexpected MISSING-METHOD items: {method_items!r}"


def test_conformance_empty_diff_body_is_noop():
    """check(plan, files) with default empty diff_body emits no MISSING-METHOD: items.

    Pins the deliberate fail-open from plan Architectural trade-offs: a missed
    caller silently no-ops the method loop instead of raising TypeError mid-run.
    """
    plan = PlanInfo(found=True, has_matrix=True, required_test_methods=["testAlpha"])
    items = conformance.check(plan, [])
    method_items = [i for i in items if i.startswith("MISSING-METHOD:")]
    assert method_items == []


# ---------------------------------------------------------------------------
# Phase 7b — false-positive MISSING-METHOD guards (backlog#1133)
# ---------------------------------------------------------------------------

def test_required_test_methods_accepts_backtick_with_parens():
    """A backtick-wrapped method with '()' before the closing backtick is accepted.

    Reproduces the regression introduced by the first pass-1 fix: plans write
    entries like '- `testSumsSalariesAcrossPlayersForEachFutureYear()` — two rows'
    and the pure-identifier-only match dropped them (backlog#1133, corpus fix).
    """
    content = """
## Required Test Methods

- `testSumsSalariesAcrossPlayersForEachFutureYear()` — two rows at `cy: 1`
- `testCountsHoldsOnlyForNonZeroSalaries()` — mixed zero/non-zero
- `testValidateAddCapOutcomeFollowsSalaryBasis(): void` — cap branch
"""
    methods = parse_required_test_methods(content)
    assert "testSumsSalariesAcrossPlayersForEachFutureYear" in methods
    assert "testCountsHoldsOnlyForNonZeroSalaries" in methods
    assert "testValidateAddCapOutcomeFollowsSalaryBasis" in methods


def test_required_test_methods_accepts_bare_identifier_with_emdash():
    """A bare identifier followed by an em-dash description is accepted.

    Reproduces the regression introduced by the first pass-2 fix: plans write
    entries like '- testBuildDiscordChunksShortTextProducesOneChunk — three signing
    lines...' and the colon/EOL-only check dropped them (backlog#1133, corpus fix).
    """
    content = """
## Required Test Methods

- testBuildDiscordChunksShortTextProducesOneChunk — three signing lines produce one message
- testBuildDiscordChunksLongTextSplitsOnLineBoundaries — text over 2000 chars yields two
- testInsertNewsStoryReturnsZeroWhenNoRowsAffected — against MockDatabase returns 0
"""
    methods = parse_required_test_methods(content)
    assert "testBuildDiscordChunksShortTextProducesOneChunk" in methods
    assert "testBuildDiscordChunksLongTextSplitsOnLineBoundaries" in methods
    assert "testInsertNewsStoryReturnsZeroWhenNoRowsAffected" in methods


def test_required_test_methods_skips_path_bullet():
    """A backtick-wrapped path bullet (containing /) is not captured as a method name.

    Reproduces MISSING-METHOD: ibl5 / MISSING-METHOD: bin from real PRs
    (#2441, #2432): the plan's Required Test Methods section uses file-path
    bullets like '- `ibl5/tests/SearchViewTest.php` (extend existing class)'
    as context lines, and the old regex captured the first identifier before
    the slash ('ibl5') as a required method name.

    After the fix the path bullet is silently skipped and only the real
    method on the indented sub-bullet is returned.
    """
    content = """
## Required Test Methods

- `ibl5/tests/Search/SearchViewTest.php` (extend existing class)
  - `testRenderLabels`
- `bin/test-harness` — test script
  - `testHarnessBoots`
"""
    methods = parse_required_test_methods(content)
    assert "ibl5" not in methods, (
        f"path prefix 'ibl5' must not be captured as a method name; got {methods!r}"
    )
    assert "bin" not in methods, (
        f"path prefix 'bin' must not be captured as a method name; got {methods!r}"
    )
    assert "testRenderLabels" in methods
    assert "testHarnessBoots" in methods


def test_required_test_methods_skips_label_phrase():
    """A bullet starting with a prose label phrase is not captured as a method name.

    Reproduces MISSING-METHOD: Static from PR #2432: the plan entry
    '- Static guards in `bin/test-pr-cycle`: `g_no_merge`, ...' caused
    'Static' to be captured as a required method because the regex grabbed
    the first identifier on any bullet line regardless of context.
    """
    content = """
## Required Test Methods

- Static guards in `bin/test-pr-cycle`: `g_no_merge`, `g_one_arm`
- `testRealMethod`
"""
    methods = parse_required_test_methods(content)
    assert "Static" not in methods, (
        f"category label 'Static' must not be a method name; got {methods!r}"
    )
    assert "testRealMethod" in methods


def test_conformance_accepts_bash_function_declaration():
    """No MISSING-METHOD: when the diff uses bare 'name() {' bash function syntax.

    Reproduces the false-positive for 'present methods' (backlog#1133): a shell
    guard like 'g_no_merge() {' exists in the diff but the conformance check only
    matched '(function|def) name', missing the bash declaration form.
    """
    plan = PlanInfo(found=True, has_matrix=True, required_test_methods=["g_no_merge"])
    diff_body = "+g_no_merge() {\n+    return 1\n+}\n"
    items = conformance.check(plan, [], diff_body=diff_body)
    method_items = [i for i in items if i.startswith("MISSING-METHOD:")]
    assert method_items == [], (
        f"g_no_merge declared as 'g_no_merge() {{' must not be flagged missing; got {method_items!r}"
    )


def test_conformance_still_flags_truly_absent_method():
    """A method genuinely absent from the diff is still reported as MISSING-METHOD:.

    Regression guard: the false-positive fixes must not suppress detection of
    methods the diff never wrote.
    """
    plan = PlanInfo(found=True, has_matrix=True, required_test_methods=["testAbsent"])
    diff_body = "function testPresent() {}\ng_other() {\n    return 0\n}\n"
    items = conformance.check(plan, [], diff_body=diff_body)
    missing = [i for i in items if i.startswith("MISSING-METHOD:") and "testAbsent" in i]
    assert missing, (
        f"Expected MISSING-METHOD: testAbsent in items; got {items!r}"
    )


# ---------------------------------------------------------------------------
# Phase 5 - D5: shell-command token rejection in parse_matrix
# ---------------------------------------------------------------------------

def _plan_with_phpunit(test_expr: str) -> str:
    """Minimal plan with a PHPUnit matrix row using `test_expr` as the backtick token."""
    return (
        "## Verification Matrix\n\n"
        "| Behavior | Test type | Test |\n"
        "|---|---|---|\n"
        f"| saves row | PHPUnit | `{test_expr}` |\n"
    )


@pytest.mark.parametrize("token,reason", [
    ("npm test && npm run build", "shell metacharacter &"),
    ("pytest -q x/y.py; echo", "shell metacharacter ;"),
    ("bin/x > out/test.log", "shell metacharacter >"),
    ("-k test_foo", "leading dash flag"),
    ('"tests/a.py"', "leading double-quote"),
    ("$CMD tests/a.py", "leading dollar sign"),
    ("run tests/a.py", "whitespace in token"),
])
def test_shell_token_rejected(token, reason):
    """Each shell-shaped token yields planned == [] (Phase 5 D5 guard)."""
    planned, _ = parse_matrix(_plan_with_phpunit(token))
    assert planned == [], f"Expected empty planned for {reason!r}: got {planned!r}"


def test_real_path_lands_in_planned():
    """Positive control: real repo paths with slashes and no shell chars still land in planned."""
    plan = (
        "## Verification Matrix\n\n"
        "| Behavior | Test type | Test |\n"
        "|---|---|---|\n"
        "| spec runs | E2E | `ibl5/tests/e2e/admin/events.spec.ts` |\n"
        "| unit runs | PHPUnit | `tools/postplan-harness/tests/test_planfile.py` |\n"
    )
    planned, _ = parse_matrix(plan)
    assert "ibl5/tests/e2e/admin/events.spec.ts" in planned
    assert "tools/postplan-harness/tests/test_planfile.py" in planned


# ---------------------------------------------------------------------------
# Phase 7 (autonomy-contract) -- frontmatter parser, mirror sync, enforcement
# ---------------------------------------------------------------------------

AUTONOMY_CONTRACT_LIB = os.path.join(REPO_ROOT, "bin", "lib", "plan-autonomy-contract")

# Shared fixture list used by both the sect-7a individual tests and the sect-7b sync sweep.
# Each tuple: (content, label, expected_sc, expected_err_nonempty_when_malformed)
# expected_err_nonempty_when_malformed=None means well-formed (error == "")
_CONTRACT_SYNC_FIXTURES = [
    # absent -> silent
    ("---\nimpl_model: sonnet\n---\n# Plan\n",
     "absent-only-impl-model", "", None),
    # body prose with no frontmatter -- NR==1 guard prevents self-selection
    ("# Docs\n\nstop_condition: whenever\nevidence: some/path\n",
     "body-prose-no-frontmatter", "", None),
    # well-formed: tests-green, two tokens
    ("---\nstop_condition: tests-green\nevidence: a/b, c/d\n---\n# Plan\n",
     "tests-green-two-token", "tests-green", None),
    # well-formed: evidence-present, single token
    ("---\nstop_condition: evidence-present\nevidence: tools/check.sh\n---\n# Plan\n",
     "evidence-present-single", "evidence-present", None),
    # malformed: empty stop_condition
    ("---\nstop_condition:\nevidence: ibl5/foo.php\n---\n# Plan\n",
     "empty-stop-condition", "", "not a legal value"),
    # malformed: stop_condition without evidence
    ("---\nstop_condition: tests-green\n---\n# Plan\n",
     "stop-condition-alone", "", "unit"),
    # malformed: evidence without stop_condition
    ("---\nevidence: ibl5/foo.php\n---\n# Plan\n",
     "evidence-alone", "", "unit"),
    # malformed: absolute path token
    ("---\nstop_condition: tests-green\nevidence: /etc/passwd\n---\n# Plan\n",
     "evidence-absolute-path", "", "repo-relative"),
    # malformed: path traversal token
    ("---\nstop_condition: tests-green\nevidence: ../../etc/passwd\n---\n# Plan\n",
     "evidence-path-traversal", "", "repo-relative"),
    # malformed: shell metacharacter in token (semicolon)
    ("---\nstop_condition: tests-green\nevidence: bin/x; rm -rf /\n---\n# Plan\n",
     "evidence-shell-metachar", "", "repo-relative"),
    # malformed: empty evidence field after whitespace stripping
    ("---\nstop_condition: tests-green\nevidence:\n---\n# Plan\n",
     "empty-evidence", "", "empty"),
]

# Evidence-token grammar cases: (evidence value, label, expect_reject).
# The first block diverged between shell and Python before this fix; the
# second block always agreed and pins the edges the fix must not move.
_TOKEN_GRAMMAR_CASES = [
    ("a,,b", "interior-empty", True),
    (",a", "leading-empty", True),
    ("a,  ,b", "whitespace-only-token", True),
    ("a..b", "dotdot-inside-token", True),
    ("...", "triple-dot", True),
    ("a,", "single-trailing-comma", False),
    ("a,b,", "list-trailing-comma", False),
    (".", "single-dot", False),
    ("./a", "dot-slash", False),
    ("a/", "trailing-slash", False),
    ("a/../b", "dotdot-segment", True),
    ("..", "bare-dotdot", True),
]


def _grammar_plan(ev):
    return f"---\nstop_condition: evidence-present\nevidence: {ev}\n---\n# Plan\n"


_CONTRACT_SYNC_FIXTURES += [
    (_grammar_plan(ev), f"grammar-{label}",
     "" if rej else "evidence-present", "rejected" if rej else None)
    for ev, label, rej in _TOKEN_GRAMMAR_CASES
]


# sect-7a -- Parser unit tests


def test_contract_absent_fields_silent():
    """No stop_condition/evidence -> full tuple is ("", [], "")."""
    content = "---\nimpl_model: sonnet\n---\n# Plan\n"
    assert frontmatter_autonomy_contract(content) == ("", [], "")


def test_contract_parses_well_formed():
    """Well-formed pair returns normalised stop_condition, token list, no error."""
    content = "---\nstop_condition: tests-green\nevidence: a/b, c/d\n---\n# Plan\n"
    sc, tokens, err = frontmatter_autonomy_contract(content)
    assert sc == "tests-green"
    assert tokens == ["a/b", "c/d"]
    assert err == ""


def test_contract_evidence_present_single_token():
    """evidence-present with a single token round-trips correctly."""
    content = "---\nstop_condition: evidence-present\nevidence: tools/check.sh\n---\n# Plan\n"
    sc, tokens, err = frontmatter_autonomy_contract(content)
    assert sc == "evidence-present"
    assert tokens == ["tools/check.sh"]
    assert err == ""


def test_contract_body_prose_does_not_self_select():
    """Fields in the body (not frontmatter) are silently ignored -- NR==1 guard."""
    content = "# Docs\n\nstop_condition: whenever\nevidence: some/path\n"
    assert frontmatter_autonomy_contract(content) == ("", [], "")


def test_contract_present_but_empty_stop_condition_flagged():
    """stop_condition: (empty) with evidence present -> enum error (not a legal value)."""
    content = "---\nstop_condition:\nevidence: ibl5/foo.php\n---\n# Plan\n"
    _, _, err = frontmatter_autonomy_contract(content)
    assert "not a legal value" in err


@pytest.mark.parametrize("content,label", [
    ("---\nstop_condition: tests-green\n---\n# Plan\n", "stop_condition-alone"),
    ("---\nevidence: ibl5/foo.php\n---\n# Plan\n", "evidence-alone"),
])
def test_contract_unpaired_field_flagged(content, label):
    """Either field alone (without its partner) -> paired-unit error."""
    _, _, err = frontmatter_autonomy_contract(content)
    assert "unit" in err, f"expected unit error for {label!r}, got {err!r}"


@pytest.mark.parametrize("content,label", [
    ("---\nstop_condition: tests-green\nevidence: /etc/passwd\n---\n# Plan\n",
     "absolute-path"),
    ("---\nstop_condition: tests-green\nevidence: ../../etc/passwd\n---\n# Plan\n",
     "path-traversal"),
    ("---\nstop_condition: tests-green\nevidence: bin/x; rm -rf /\n---\n# Plan\n",
     "shell-metachar"),
    ("---\nstop_condition: tests-green\nevidence:\n---\n# Plan\n",
     "empty-evidence"),
])
def test_contract_malformed_evidence_token_flagged(content, label):
    """Illegal evidence tokens are rejected with an error (not silently accepted)."""
    _, _, err = frontmatter_autonomy_contract(content)
    assert err != "", f"expected error for {label!r}, got empty string"


# sect-7b -- Shell/Python mirror sync test


def test_contract_lib_sync(tmp_path):
    """Python parser and bin/lib/plan-autonomy-contract classify every fixture
    identically (accept vs reject).

    _TOKEN_GRAMMAR_CASES and test_contract_token_grammar_sweep cover the token
    grammar. The sibling semantic pin is the "Phase 5.0d TWO-WAY AGREEMENT"
    section of bin/test-postplan-arm-conditions.
    """
    if not os.path.isfile(AUTONOMY_CONTRACT_LIB):
        pytest.skip("plan-autonomy-contract lib not found: " + AUTONOMY_CONTRACT_LIB)
    if not os.access(AUTONOMY_CONTRACT_LIB, os.X_OK):
        pytest.skip("plan-autonomy-contract not executable: " + AUTONOMY_CONTRACT_LIB)

    for content, label, _expected_sc, _expected_err in _CONTRACT_SYNC_FIXTURES:
        f = tmp_path / f"{label}.md"
        f.write_text(content)
        proc = subprocess.run(
            [AUTONOMY_CONTRACT_LIB, str(f)],
            capture_output=True, text=True,
        )
        assert proc.returncode in (0, 1), (
            f"unexpected rc={proc.returncode} for '{label}': stderr={proc.stderr!r}")
        _, _, py_err = frontmatter_autonomy_contract(content)
        shell_ok = proc.returncode == 0
        py_ok = py_err == ""
        assert shell_ok == py_ok, (
            f"classification divergence on '{label}': "
            f"shell rc={proc.returncode}, python err={py_err!r}")


def _skip_without_contract_lib():
    if not os.path.isfile(AUTONOMY_CONTRACT_LIB):
        pytest.skip("plan-autonomy-contract lib not found: " + AUTONOMY_CONTRACT_LIB)
    if not os.access(AUTONOMY_CONTRACT_LIB, os.X_OK):
        pytest.skip("plan-autonomy-contract not executable: " + AUTONOMY_CONTRACT_LIB)


@pytest.mark.parametrize("ev,label,expect_reject", _TOKEN_GRAMMAR_CASES)
def test_contract_token_grammar_direction(tmp_path, ev, label, expect_reject):
    """Both parsers reject/accept each grammar case in the stated direction.

    Parity alone would pass if both sides drifted to accept together.
    """
    _skip_without_contract_lib()
    content = _grammar_plan(ev)
    f = tmp_path / f"{label}.md"
    f.write_text(content)
    proc = subprocess.run(
        [AUTONOMY_CONTRACT_LIB, str(f)], capture_output=True, text=True)
    assert proc.returncode == (1 if expect_reject else 0), (
        f"shell rc={proc.returncode} for '{label}' ({ev!r}): stderr={proc.stderr!r}")
    _, _, py_err = frontmatter_autonomy_contract(content)
    assert (py_err != "") == expect_reject, (
        f"python err={py_err!r} for '{label}' ({ev!r}), expect_reject={expect_reject}")


def test_contract_token_grammar_sweep(tmp_path):
    """Every 1-3 field comma list over a small alphabet classifies identically."""
    _skip_without_contract_lib()
    alphabet = ["a", "", "..", "a..b"]
    values = [
        ",".join(combo)
        for n in (1, 2, 3)
        for combo in itertools.product(alphabet, repeat=n)
    ]
    assert len(values) == 84, f"sweep generator produced {len(values)} values"
    for i, value in enumerate(values):
        content = _grammar_plan(value)
        f = tmp_path / f"sweep-{i}.md"
        f.write_text(content)
        proc = subprocess.run(
            [AUTONOMY_CONTRACT_LIB, str(f)], capture_output=True, text=True)
        assert proc.returncode in (0, 1), (
            f"unexpected rc={proc.returncode} for {value!r}: stderr={proc.stderr!r}")
        _, _, py_err = frontmatter_autonomy_contract(content)
        assert (proc.returncode == 0) == (py_err == ""), (
            f"classification divergence on evidence {value!r}: "
            f"shell rc={proc.returncode}, python err={py_err!r}")


# sect-7c -- conformance.check enforcement tests


def test_conformance_contract_absent_is_noop():
    """Plans with no autonomy contract never emit UNMET-CONTRACT items."""
    # Matrix-less, no contract -> always []
    plan_no_matrix = PlanInfo(found=True, has_matrix=False)
    assert conformance.check(plan_no_matrix, []) == []

    # Matrix present, no contract -> same behaviour as before this PR (existing PLAN fixture)
    plan_with_matrix = locate_plan("x", content_override=PLAN)
    all_present = conformance.check(plan_with_matrix, [
        "ibl5/tests/Unit/EventLoggerTest.php",
        "ibl5/tests/e2e/admin/events.spec.ts",
        "ibl5/classes/EventLogger.php",
    ])
    assert all_present == []
    one_missing = conformance.check(plan_with_matrix, ["ibl5/classes/EventLogger.php"])
    assert len(one_missing) == 2 and all(m.startswith("MISSING:") for m in one_missing)
    assert not any("UNMET-CONTRACT:" in m for m in one_missing)


def test_conformance_tests_green_clean_on_pass():
    """tests-green + evidence in diff + phase5_status='pass' -> []."""
    plan = PlanInfo(found=True, stop_condition="tests-green", evidence=["ibl5/foo.php"])
    items = conformance.check(plan, ["ibl5/foo.php"], phase5_status="pass")
    assert items == []


@pytest.mark.parametrize("phase5_status", ["fail", "skipped", None])
def test_conformance_tests_green_holds_on_skipped_and_none(phase5_status):
    """tests-green contract holds when phase5_status is not 'pass'.

    Seam: an == 'fail' implementation passes only the 'fail' case and silently
    no-ops 'skipped' and None. This parametrized test is the only guard between
    the feature and a plausible-looking no-op.
    """
    plan = PlanInfo(found=True, stop_condition="tests-green", evidence=["ibl5/foo.php"])
    items = conformance.check(plan, ["ibl5/foo.php"], phase5_status=phase5_status)
    unmet = [i for i in items if i.startswith("UNMET-CONTRACT: stop_condition")]
    assert len(unmet) == 1, (
        f"expected 1 UNMET-CONTRACT: stop_condition item for {phase5_status=}, "
        f"got {items!r}")


def test_conformance_evidence_present_ignores_phase5():
    """evidence-present + evidence in diff + any phase5_status -> []."""
    plan = PlanInfo(found=True, stop_condition="evidence-present", evidence=["tools/check.sh"])
    items = conformance.check(plan, ["tools/check.sh"], phase5_status=None)
    assert items == []


def test_conformance_evidence_missing_holds_without_matrix():
    """has_matrix=False + evidence token absent from diff -> UNMET-CONTRACT: evidence item.

    Pins that _contract_items runs above the has_matrix early return in check().
    """
    plan = PlanInfo(found=True, has_matrix=False,
                    stop_condition="evidence-present", evidence=["ibl5/foo.php"])
    items = conformance.check(plan, [], phase5_status=None)
    unmet = [i for i in items if "UNMET-CONTRACT: evidence" in i]
    assert len(unmet) == 1, f"expected 1 UNMET-CONTRACT: evidence item, got {items!r}"


def test_conformance_evidence_substring_does_not_satisfy():
    """evidence token 'bin/x' is not discharged by a diff containing 'bin/xylophone'."""
    plan = PlanInfo(found=True, stop_condition="evidence-present", evidence=["bin/x"])
    items = conformance.check(plan, ["bin/xylophone"], phase5_status=None)
    unmet = [i for i in items if "UNMET-CONTRACT: evidence" in i]
    assert len(unmet) == 1, (
        f"expected evidence to hold against substring match, got {items!r}")


def test_conformance_malformed_contract_single_item():
    """contract_error set -> exactly one UNMET-CONTRACT: malformed item; evidence loop skipped."""
    plan = PlanInfo(found=True,
                    contract_error="stop_condition:/evidence: -- the two fields are a unit",
                    evidence=["ibl5/missing.php"])
    items = conformance.check(plan, [], phase5_status=None)
    assert len(items) == 1
    assert items[0].startswith("UNMET-CONTRACT: malformed")
    # Pin that the evidence loop did not execute alongside the malformed item.
    assert not any("never appeared in the diff" in i for i in items)


def test_parse_phases_extracts_numbers_headings_and_backtick_paths():
    plan = """
## Phase 1: Foo

Edit `harness/a.py` and add `tests/test_a.py::test_x`.

## Phase 2: Bar

Update `bin/b` accordingly.
"""
    phases = parse_phases(plan)
    assert len(phases) == 2
    assert [p.number for p in phases] == [1, 2]
    assert phases[0].evidence_paths == ["harness/a.py", "tests/test_a.py"]
    assert phases[1].evidence_paths == ["bin/b"]


def test_parse_phases_counts_heading_paths_as_evidence():
    """backlog#1253 / PR #2580 shape: the heading names the target file, the body cites
    only a fixture string. Heading paths come first, then body paths.

    Mutation caught: dropping the heading seed leaves ["docs/IBL5/rel.md"] only, and
    conformance.phase_omission_items then reports a phase that shipped as MISSING-PHASE.
    """
    plan = """
## Phase 3: Absolute-path parity tests in `tools/postplan-harness/tests/test_planfile.py`

Assert the parser turns `/Users/x/IBL5/docs/IBL5/rel.md` into `docs/IBL5/rel.md`.

## Phase 3: Duplicate heading in `bin/extra`

More.
"""
    phases = parse_phases(plan)
    assert len(phases) == 1
    assert phases[0].evidence_paths[0] == "tools/postplan-harness/tests/test_planfile.py"
    assert "docs/IBL5/rel.md" in phases[0].evidence_paths
    assert "bin/extra" in phases[0].evidence_paths

    info = PlanInfo(found=True, has_matrix=True, phases=phases)
    assert conformance.phase_omission_items(info,["tools/postplan-harness/tests/test_planfile.py"]) == []


def test_parse_phases_strips_line_suffix_from_evidence_paths():
    plan = """
## Phase 1: Foo

See `bin/check-prose:438-457` and `harness/a.py:120`, also `docs/x.md#L12-L20`.

## Phase 2: Bar

Edit `harness/c.py:10-20` then `harness/c.py:300` again.
"""
    phases = parse_phases(plan)
    assert phases[0].evidence_paths == ["bin/check-prose", "harness/a.py", "docs/x.md"]
    assert phases[1].evidence_paths == ["harness/c.py"]


def test_parse_phases_skips_fenced_blocks_and_example_tokens():
    plan = """
## Phase 1: Real

No paths here except `(example)` one: `tools/fake.py` (example).

```
## Phase 9: Phantom inside fence

Edit `harness/phantom.py`.
```
"""
    phases = parse_phases(plan)
    assert len(phases) == 1
    assert phases[0].number == 1
    assert phases[0].evidence_paths == []


def test_parse_phases_marks_all_s_marker_bookkeeping():
    plan = """
## Phase 3: X [phases: S]

Close backlog issue.

## Phase 4: Y [phases: S/S]

Bump doc date.

## Phase 5: Z [phases: S/B]

Mixed work.

## Phase 6: W [phases: B]

Build-only.
"""
    phases = parse_phases(plan)
    by_num = {p.number: p for p in phases}
    assert by_num[3].bookkeeping is True
    assert by_num[4].bookkeeping is True
    assert by_num[5].bookkeeping is False
    assert by_num[6].bookkeeping is False


def test_parse_phases_ignores_subphase_and_h3_headings():
    plan = """
## Phase 5: Main

Edit `harness/main.py`.

### Delegate — part of phase 5

Scope: `tests/test_delegate.py`.

## Phase 5.5: fidelity

Edit `harness/fidelity.py`.

## Phase 2: Dup first

Edit `harness/dup.py`.

## Phase 2: Dup second

Also `harness/dup2.py`.
"""
    phases = parse_phases(plan)
    numbers = [p.number for p in phases]
    assert 5 in numbers
    # Phase 5.5 heading must not produce a separate entry (sub-phase rejected by (?!\.\d))
    assert len([p for p in phases if p.number == 5]) == 1
    # ### Delegate paths accrue to the enclosing h2 phase (5)
    phase5 = next(p for p in phases if p.number == 5)
    assert "tests/test_delegate.py" in phase5.evidence_paths
    # Phase 5.5 paths must not leak into phase 5 (catches a dropped (?!\.\d))
    assert "harness/fidelity.py" not in phase5.evidence_paths
    # Duplicate Phase 2 merges into one entry
    assert len([p for p in phases if p.number == 2]) == 1
    phase2 = next(p for p in phases if p.number == 2)
    assert "harness/dup.py" in phase2.evidence_paths
    assert "harness/dup2.py" in phase2.evidence_paths


def test_parse_deferred_phase_numbers_from_out_of_scope():
    plan = """
## Approach

Phase 3 is handled elsewhere.

## Out of Scope

- Phase 5 (deferred to a follow-up PR)
- phase 7 and Step 7 again
"""
    result = parse_deferred_phase_numbers(plan)
    assert result == [5, 7]


def test_parse_deferred_phase_numbers_absent_section_is_empty():
    plan = """
## Phase 1: Foo

No out of scope section.
"""
    assert parse_deferred_phase_numbers(plan) == []


def test_locate_plan_populates_phases(tmp_path):
    plan_text = """---
auto_merge: true
---

# My Plan

## Phase 1: Do the thing

Edit `harness/x.py`.

## Out of Scope

- Phase 2 (future work)
"""
    plan_file = tmp_path / "my-plan.md"
    plan_file.write_text(plan_text)
    info = locate_plan("my-plan", plans_dir=str(tmp_path))
    assert info.found
    assert len(info.phases) >= 1
    assert info.phases[0].number == 1
    assert info.deferred_phase_numbers == [2]


# ---------------------------------------------------------------------------
# Tests for _normalise_cf_path and parse_critical_files absolute-path handling
# ---------------------------------------------------------------------------

def test_normalise_cf_path_relative_unchanged():
    assert _normalise_cf_path("ibl5/classes/Foo.php") == "ibl5/classes/Foo.php"
    assert _normalise_cf_path("bin/check-plan") == "bin/check-plan"


def test_normalise_cf_path_ibL5_prefix_stripped():
    p = "/Users/ajaynicolas/GitHub/IBL5/ibl5/classes/Foo.php"
    assert _normalise_cf_path(p) == "ibl5/classes/Foo.php"


def test_normalise_cf_path_worktree_prefix_stripped():
    p = "/Users/ajaynicolas/GitHub/IBL5-worktrees/my-branch/ibl5/classes/Foo.php"
    assert _normalise_cf_path(p) == "ibl5/classes/Foo.php"


def test_normalise_cf_path_case_sensitive_no_strip():
    # IBL5 is always uppercase; lowercase does not match.
    p = "/Users/ajaynicolas/GitHub/ibl5/ibl5/classes/Foo.php"
    assert _normalise_cf_path(p) == p


def test_parse_critical_files_normalises_absolute_path():
    plan = (
        "## Critical Files\n\n"
        "- `/Users/ajaynicolas/GitHub/IBL5/ibl5/classes/Foo.php`\n"
        "- `/Users/ajaynicolas/GitHub/IBL5-worktrees/slug/bin/check-plan`\n"
    )
    cf = parse_critical_files(plan)
    paths = [p for p, _ann, _ex in cf]
    assert paths == ["ibl5/classes/Foo.php", "bin/check-plan"]


def test_parse_critical_files_relative_paths_unchanged():
    plan = (
        "## Critical Files\n\n"
        "- `ibl5/classes/Foo.php`\n"
        "- `bin/check-plan`\n"
    )
    cf = parse_critical_files(plan)
    paths = [p for p, _ann, _ex in cf]
    assert paths == ["ibl5/classes/Foo.php", "bin/check-plan"]
