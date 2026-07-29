import os
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.planfile import (EXEMPT_RE, frontmatter_auto_merge_false, locate_plan,
                              parse_critical_files, parse_matrix)

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
