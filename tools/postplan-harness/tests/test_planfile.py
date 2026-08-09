import contextlib
import io
import os
import pytest
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.planfile import (EXEMPT_RE, _strip_fenced, frontmatter_auto_merge_false,
                              locate_plan, parse_critical_files, parse_matrix,
                              parse_required_test_methods)
from harness.state import PlanInfo, RunResult

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
    assert len(manual) == 1 and "eyeball" in manual[0]
    cf = parse_critical_files(PLAN)
    assert cf[0][0] == "ibl5/classes/EventLogger.php" and not cf[0][2]
    assert cf[1][0] == "ibl5/schema.sql" and cf[1][2]  # "(read-only reference)" -> exempt


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
    assert not any("eyeball it" in row for row in manual)


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
            "- `ibl5/docs/backlog/README.md` (conditional) - only if the op changes it\n"
            "- `ibl5/docs/backlog/other.md` - only if the op changes it\n")
    assert _classify(plan) == ["EXEMPT:ibl5/docs/backlog/README.md",
                               "MUST_APPEAR:ibl5/docs/backlog/other.md"]


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
