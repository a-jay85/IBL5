"""Phase 6.5 condition (7), `auto_merge: docs-only-ready`: the docs-refresh clauses.

The shell twin is `pr_docs_refresh_hold` in `bin/lib/pr-armable.sh`; the parity test
below runs it through `bash` over the same case table the Python port is checked
against, so the two ports cannot drift."""
import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import (ArmInputs, DOCS_REFRESH_TOKEN, docs_refresh_holds,
                             docs_refresh_path_ok, docs_refresh_rules_date_only, evaluate,
                             split_docs_refresh_rules_diffs)
from harness.planfile import frontmatter_auto_merge_false, frontmatter_auto_merge_value
from harness.state import Classification
from tests.test_armable import BODY_CLEARED

REPO_ROOT = Path(__file__).resolve().parents[3]
PR_ARMABLE = REPO_ROOT / "bin" / "lib" / "pr-armable.sh"

REFRESH_BRANCH = "docs-stale-refresh-20261015"
QUALIFYING_FILES = [
    "ibl5/docs/decisions/0086-x.md",
    "ibl5/docs/guide.md",
    "ibl5/classes/Foo/README.md",
    "ibl5/classes/A/B/README.md",
    "README.md",
]

# (name, head_ref, files, md_state, verdict, expected)
CASES = [
    ("qualifying", REFRESH_BRANCH, QUALIFYING_FILES, "TWO-WAY", "READY", []),
    ("branch-feature", "feature-x", QUALIFYING_FILES, "TWO-WAY", "READY",
     ["docs-refresh:branch:feature-x"]),
    ("branch-empty", "", QUALIFYING_FILES, "TWO-WAY", "READY",
     ["docs-refresh:branch:<empty>"]),
    ("paths-empty", REFRESH_BRANCH, [], "TWO-WAY", "READY",
     ["docs-refresh:paths:empty"]),
    ("paths-claude-rules-no-diff", REFRESH_BRANCH, ["ibl5/docs/a.md", ".claude/rules/x.md"],
     "TWO-WAY", "READY", ["docs-refresh:paths:.claude/rules/x.md"]),  # no diff supplied: a rules path fails closed
    ("paths-claude-skill", REFRESH_BRANCH, [".claude/skills/post-plan/SKILL.md"],
     "TWO-WAY", "READY", ["docs-refresh:paths:.claude/skills/post-plan/SKILL.md"]),
    ("paths-bin", REFRESH_BRANCH, ["bin/docfix-run"], "TWO-WAY", "READY",
     ["docs-refresh:paths:bin/docfix-run"]),
    ("paths-workflow", REFRESH_BRANCH, [".github/workflows/doc-freshness-audit.yml"],
     "TWO-WAY", "READY", ["docs-refresh:paths:.github/workflows/doc-freshness-audit.yml"]),
    ("paths-sql-under-docs", REFRESH_BRANCH, ["ibl5/docs/schema/current-schema.sql"],
     "TWO-WAY", "READY", ["docs-refresh:paths:ibl5/docs/schema/current-schema.sql"]),
    ("paths-tests", REFRESH_BRANCH, ["ibl5/tests/Foo/BarTest.php"], "TWO-WAY", "READY",
     ["docs-refresh:paths:ibl5/tests/Foo/BarTest.php"]),
    ("paths-tools-readme", REFRESH_BRANCH, ["tools/postplan-harness/README.md"],
     "TWO-WAY", "READY", ["docs-refresh:paths:tools/postplan-harness/README.md"]),
    ("paths-nested-readme-not-class", REFRESH_BRANCH, ["ibl5/modules/x/README.md"],
     "TWO-WAY", "READY", ["docs-refresh:paths:ibl5/modules/x/README.md"]),
    ("paths-first-bad-wins", REFRESH_BRANCH, ["bin/x.md", ".claude/y.md"], "TWO-WAY",
     "READY", ["docs-refresh:paths:bin/x.md"]),
    ("merge-danger-one-way", REFRESH_BRANCH, QUALIFYING_FILES, "ONE-WAY", "READY",
     ["docs-refresh:merge-danger:ONE-WAY"]),
    ("merge-danger-missing", REFRESH_BRANCH, QUALIFYING_FILES, "MISSING", "READY",
     ["docs-refresh:merge-danger:MISSING"]),
    ("merge-danger-malformed", REFRESH_BRANCH, QUALIFYING_FILES, "MALFORMED", "READY",
     ["docs-refresh:merge-danger:MALFORMED"]),
    ("merge-danger-empty", REFRESH_BRANCH, QUALIFYING_FILES, "", "READY",
     ["docs-refresh:merge-danger:MISSING"]),
    ("verdict-ready-with-notes", REFRESH_BRANCH, QUALIFYING_FILES, "TWO-WAY",
     "READY WITH NOTES", ["docs-refresh:verdict:READY WITH NOTES"]),
    ("verdict-not-ready", REFRESH_BRANCH, QUALIFYING_FILES, "TWO-WAY", "NOT READY",
     ["docs-refresh:verdict:NOT READY"]),
    ("verdict-empty", REFRESH_BRANCH, QUALIFYING_FILES, "TWO-WAY", "",
     ["docs-refresh:verdict:<none>"]),
    ("verdict-padded-ready", REFRESH_BRANCH, QUALIFYING_FILES, "TWO-WAY", "  READY \n", []),
    ("all-fail", "feature-x", [], "ONE-WAY", "READY WITH NOTES",
     ["docs-refresh:branch:feature-x",
      "docs-refresh:paths:empty",
      "docs-refresh:merge-danger:ONE-WAY",
      "docs-refresh:verdict:READY WITH NOTES"]),
]

CASE_IDS = [c[0] for c in CASES]


def _shell(head_ref, files, md_state, verdict, diff_file=None):
    if shutil.which("jq") is None:
        raise RuntimeError("jq is required: pr_docs_refresh_hold is dead without it")
    args = [head_ref, json.dumps([{"path": p} for p in files]), md_state, verdict]
    if diff_file is not None:
        args.append(str(diff_file))
    proc = subprocess.run(
        ["bash", "-c", 'source "$1"; shift; pr_docs_refresh_hold "$@"', "_", str(PR_ARMABLE),
         *args],
        capture_output=True, text=True, check=True,
    )
    return proc.stdout.splitlines()


@pytest.mark.parametrize("path", [
    "ibl5/docs/decisions/0086-x.md",
    "ibl5/docs/guide.md",
    "ibl5/classes/Foo/README.md",
    "ibl5/classes/A/B/README.md",
    "ibl5/classes//README.md",
    "README.md",
])
def test_docs_refresh_path_allowlist_accepts(path):
    assert docs_refresh_path_ok(path)


@pytest.mark.parametrize("path", [
    ".claude/rules/x.md",
    ".claude/skills/post-plan/SKILL.md",
    "CLAUDE.md",
    "AGENTS.md",
    "bin/docfix-run",
    "bin/x.md",
    ".github/workflows/doc-freshness-audit.yml",
    "ibl5/docs/schema/current-schema.sql",
    "ibl5/docs/x.MD",
    "ibl5/tests/Foo/BarTest.php",
    "tools/postplan-harness/README.md",
    "ibl5/modules/x/README.md",
    "ibl5/classes/Foo/Other.md",
    "docs/README.md",
])
def test_docs_refresh_path_allowlist_rejects(path):
    assert not docs_refresh_path_ok(path)


def test_docs_refresh_path_allowlist_matches_shell():
    """The shell allowlist agrees with the Python one on a mixed path set."""
    if shutil.which("jq") is None:
        raise RuntimeError("jq is required")
    paths = [
        "ibl5/docs/guide.md", "ibl5/classes//README.md", "README.md",
        "ibl5/docs/schema/current-schema.sql", ".claude/rules/x.md", "ibl5/docs/x.MD",
    ]
    for p in paths:
        rc = subprocess.run(
            ["bash", "-c", 'source "$1"; pr_docs_refresh_path_ok "$2"', "_", str(PR_ARMABLE), p],
        ).returncode
        assert (rc == 0) == docs_refresh_path_ok(p), p


def test_docs_refresh_holds_qualifying_is_empty():
    _, head, files, md, verdict, _ = CASES[0]
    assert docs_refresh_holds(head, files, md, verdict) == []


@pytest.mark.parametrize("name,head,files,md,verdict,expected", CASES, ids=CASE_IDS)
def test_docs_refresh_holds_each_clause(name, head, files, md, verdict, expected):
    assert docs_refresh_holds(head, files, md, verdict) == expected


@pytest.mark.parametrize("name,head,files,md,verdict,expected", CASES, ids=CASE_IDS)
def test_docs_refresh_shell_parity(name, head, files, md, verdict, expected):
    shell = _shell(head, files, md, verdict)
    assert shell == expected
    assert shell == docs_refresh_holds(head, files, md, verdict)


# --- Rules files: a date-only `last_verified:` bump passes ----------------------------------

def _rules_diff(path, body, header=None):
    """One unified-diff section for `path`. body=None omits the hunk entirely."""
    if header is None:
        header = ["index 1111111..2222222 100644", f"--- a/{path}", f"+++ b/{path}"]
    lines = [f"diff --git a/{path} b/{path}", *header]
    if body is not None:
        lines.append("@@ -1,6 +1,6 @@")
        lines.extend(body)
    return "\n".join(lines) + "\n"


RULES_X = ".claude/rules/x.md"
DOCS_FILE = "ibl5/docs/decisions/0038-smoke-inconclusive-state.md"
BUMP_BODY = [" ---", " description: X.", "-last_verified: 2026-09-16",
             "+last_verified: 2026-11-15", " ---", " ", " # X"]
DOCS_SECTION = _rules_diff(DOCS_FILE, [" ---", "-description: Old.", "+description: New."])


def _hold(path):
    return [f"docs-refresh:paths:{path}"]


# (id, files, diff text or None, expected holds)
RULES_CASES = [
    ("rules_date_bump", [RULES_X, DOCS_FILE], _rules_diff(RULES_X, BUMP_BODY) + DOCS_SECTION, []),
    ("rules_engine_date_bump", ["engine/.claude/rules/y.md"],
     _rules_diff("engine/.claude/rules/y.md", BUMP_BODY), []),
    ("rules_text_change", [RULES_X],
     _rules_diff(RULES_X, [" ---", "-description: Alpha.", "+description: Beta.",
                           " last_verified: 2026-11-15", " ---", " ", " # X"]),
     _hold(RULES_X)),
    ("rules_date_backward", [RULES_X],
     _rules_diff(RULES_X, [" ---", " description: X.", "-last_verified: 2026-11-15",
                           "+last_verified: 2026-09-16", " ---", " ", " # X"]),
     _hold(RULES_X)),
    ("rules_date_same", [RULES_X],
     _rules_diff(RULES_X, [" ---", " description: X.", "-last_verified: 2026-11-15",
                           "+last_verified: 2026-11-15", " ---", " ", " # X"]),
     _hold(RULES_X)),
    ("rules_date_plus_extra_line", [RULES_X],
     _rules_diff(RULES_X, [" ---", " description: X.", "-last_verified: 2026-09-16",
                           "+last_verified: 2026-11-15", "+owner: someone", " ---", " ", " # X"]),
     _hold(RULES_X)),
    ("rules_date_outside_frontmatter", [RULES_X],
     _rules_diff(RULES_X, [" ---", " description: X.", " ---", "-last_verified: 2026-09-16",
                           "+last_verified: 2026-11-15", " "]),
     _hold(RULES_X)),
    ("rules_date_trailing_comment", [RULES_X],
     _rules_diff(RULES_X, [" ---", " description: X.", "-last_verified: 2026-09-16",
                           "+last_verified: 2026-11-15 # bumped", " ---", " ", " # X"]),
     _hold(RULES_X)),
    ("rules_mode_change", [RULES_X],
     _rules_diff(RULES_X, BUMP_BODY,
                 header=["old mode 100644", "new mode 100755", "index 1111111..2222222",
                         f"--- a/{RULES_X}", f"+++ b/{RULES_X}"]),
     _hold(RULES_X)),
    ("rules_binary", [RULES_X],
     _rules_diff(RULES_X, None,
                 header=["index 1111111..2222222 100644",
                         f"Binary files a/{RULES_X} and b/{RULES_X} differ"]),
     _hold(RULES_X)),
    ("rules_second_hunk", [RULES_X],
     _rules_diff(RULES_X, BUMP_BODY + ["@@ -40,3 +40,3 @@", " a", " b", " c"]),
     _hold(RULES_X)),
    ("rules_section_missing", [RULES_X, DOCS_FILE], DOCS_SECTION, _hold(RULES_X)),
    ("rules_non_md", [".claude/rules/x.txt"], _rules_diff(".claude/rules/x.txt", BUMP_BODY),
     _hold(".claude/rules/x.txt")),
]

RULES_CASE_IDS = [c[0] for c in RULES_CASES]


@pytest.mark.parametrize("name,files,diff,expected", RULES_CASES, ids=RULES_CASE_IDS)
def test_rules_case_python(name, files, diff, expected):
    got = docs_refresh_holds(REFRESH_BRANCH, files, "TWO-WAY", "READY",
                             rules_diffs=split_docs_refresh_rules_diffs(diff, files))
    assert got == expected


@pytest.mark.parametrize("name,files,diff,expected", RULES_CASES, ids=RULES_CASE_IDS)
def test_rules_case_shell_parity(name, files, diff, expected, tmp_path):
    diff_file = None
    if diff is not None:
        diff_file = tmp_path / "pr.diff"
        diff_file.write_text(diff)
    shell = _shell(REFRESH_BRANCH, files, "TWO-WAY", "READY", diff_file=diff_file)
    assert shell == expected
    assert shell == docs_refresh_holds(REFRESH_BRANCH, files, "TWO-WAY", "READY",
                                       rules_diffs=split_docs_refresh_rules_diffs(diff, files))


def test_shell_unreadable_diff_file_holds(tmp_path):
    expected = _hold(RULES_X)
    assert _shell(REFRESH_BRANCH, [RULES_X], "TWO-WAY", "READY",
                  diff_file=tmp_path / "absent.diff") == expected
    empty = tmp_path / "empty.diff"
    empty.write_text("")
    assert _shell(REFRESH_BRANCH, [RULES_X], "TWO-WAY", "READY", diff_file=empty) == expected


def test_split_rules_diffs_none_and_trailing_newline():
    files = [RULES_X, DOCS_FILE]
    assert split_docs_refresh_rules_diffs(None, files) is None
    diff = _rules_diff(RULES_X, BUMP_BODY) + DOCS_SECTION
    assert diff.endswith("\n")
    sections = split_docs_refresh_rules_diffs(diff, files)
    assert list(sections) == [RULES_X]
    assert docs_refresh_rules_date_only(RULES_X, sections[RULES_X])
    # A rules section that ends the diff must not keep the trailing empty element.
    last = split_docs_refresh_rules_diffs(_rules_diff(RULES_X, BUMP_BODY), [RULES_X])
    assert not last[RULES_X].endswith("\n")
    assert docs_refresh_rules_date_only(RULES_X, last[RULES_X])


# --- PR 3991 replay: five rules files with date-only bumps, three docs files ---------------

PR3991 = Path(__file__).parent / "fixtures" / "docs_refresh" / "pr3991.diff"
PR3991_RULES = [
    ".claude/rules/adr-append-only.md",
    ".claude/rules/htmx-history-cache.md",
    ".claude/rules/migrations.md",
    ".claude/rules/refactor-flag.md",
    "engine/.claude/rules/engine-context.md",
]


def _pr3991_files(text):
    out = []
    for ln in text.split("\n"):
        if ln.startswith("diff --git a/"):
            out.append(ln.split(" b/", 1)[1])
    return out


def test_pr3991_fixture_python():
    text = PR3991.read_text()
    files = _pr3991_files(text)
    assert set(PR3991_RULES) <= set(files)
    got = docs_refresh_holds(REFRESH_BRANCH, files, "TWO-WAY", "READY",
                             rules_diffs=split_docs_refresh_rules_diffs(text, files))
    assert got == []


def test_pr3991_fixture_shell():
    files = _pr3991_files(PR3991.read_text())
    assert set(PR3991_RULES) <= set(files)
    assert _shell(REFRESH_BRANCH, files, "TWO-WAY", "READY", diff_file=PR3991) == []


# --- Condition (7) through evaluate() ---------------------------------------------------

def _inputs(**overrides):
    """Fully-arming baseline (conditions 1-18 pass), copied from tests/test_armable.py."""
    base = dict(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                classification=Classification(files=["ibl5/classes/Trade/TradeService.php"]), findings=[], unresolved_conformance=[], exec_mutations_holding=(), exec_mutations_tree_suspect=False, plan_checks_holding=(),
                phase5_status="pass", plan_auto_merge_false=False, headless=True,
                dep_state_lookup=lambda n: "MERGED", fidelity_verdict="READY",
                unresolved_findings=[], conflict_resolved=False, merge_danger_state="TWO-WAY",
                removed_guards_holds=[])
    base.update(overrides)
    return ArmInputs(**base)


def _qualifying(**overrides):
    """Token plus a PR that satisfies every docs-refresh clause."""
    kw = dict(plan_auto_merge_value=DOCS_REFRESH_TOKEN, branch=REFRESH_BRANCH,
              classification=Classification(files=list(QUALIFYING_FILES)),
              merge_danger_state="TWO-WAY", fidelity_verdict="READY")
    kw.update(overrides)
    return _inputs(**kw)


def _cond7(decision):
    (c,) = [c for c in decision.conditions if c.name == "plan-auto-merge-hold"]
    return c


def test_frontmatter_auto_merge_value_absent_false_true_token():
    assert frontmatter_auto_merge_value("# no frontmatter\n") is None
    assert frontmatter_auto_merge_value("") is None
    assert frontmatter_auto_merge_value("---\ntitle: x\n---\nbody\n") is None
    assert frontmatter_auto_merge_value("---\nauto_merge: false\n---\n") == "false"
    assert frontmatter_auto_merge_value("---\nauto_merge: true\n---\n") == "true"
    assert frontmatter_auto_merge_value(
        f"---\nauto_merge: {DOCS_REFRESH_TOKEN}\n---\n") == "docs-only-ready"
    assert frontmatter_auto_merge_value("---\nauto_merge:   docs-only-ready  \n---\n") \
        == "docs-only-ready"
    # never lowercased: `False` is an unknown value
    assert frontmatter_auto_merge_value("---\nauto_merge: False\n---\n") == "False"
    # a body line after the closing fence cannot self-select
    assert frontmatter_auto_merge_value("---\ntitle: x\n---\nauto_merge: false\n") is None
    assert frontmatter_auto_merge_value("# h\n---\nauto_merge: false\n---\n") is None
    assert frontmatter_auto_merge_false("---\nauto_merge: false\n---\n") is True
    assert frontmatter_auto_merge_false("---\nauto_merge: true\n---\n") is False
    assert frontmatter_auto_merge_false("---\nauto_merge: False\n---\n") is False
    assert frontmatter_auto_merge_false("---\nauto_merge: docs-only-ready\n---\n") is False
    assert frontmatter_auto_merge_false("no frontmatter") is False


@pytest.mark.parametrize("value", [None, "true"])
def test_cond7_value_absent_or_true_no_hold(value):
    d = evaluate(_inputs(plan_auto_merge_value=value, plan_auto_merge_false=False))
    c = _cond7(d)
    assert not c.blocked
    assert c.reason == ""


def test_cond7_false_holds_regardless_of_diff():
    d = evaluate(_qualifying(plan_auto_merge_value="false"))
    c = _cond7(d)
    assert c.blocked
    assert c.reason == "plan declares auto_merge: false"
    assert not d.armed


def test_cond7_bool_only_false_holds():
    d = evaluate(_inputs(plan_auto_merge_false=True, plan_auto_merge_value=None))
    c = _cond7(d)
    assert c.blocked
    assert c.reason == "plan declares auto_merge: false"


@pytest.mark.parametrize("value", ["False", "yes", "docs-only", "docs-only-ready-later"])
def test_cond7_unknown_value_holds(value):
    d = evaluate(_qualifying(plan_auto_merge_value=value))
    c = _cond7(d)
    assert c.blocked
    assert c.reason.startswith("unknown auto_merge value")
    assert not d.armed


def test_cond7_token_qualifying_does_not_hold():
    d = evaluate(_qualifying())
    c = _cond7(d)
    assert not c.blocked
    assert c.reason == ""
    assert d.armed


@pytest.mark.parametrize("overrides,expected", [
    pytest.param(dict(branch="feature-x"), "docs-refresh:branch:feature-x", id="branch"),
    pytest.param(dict(branch=""), "docs-refresh:branch:<empty>", id="branch-empty"),
    pytest.param(dict(classification=Classification(
        files=["ibl5/docs/a.md", ".claude/rules/x.md"])),
        "docs-refresh:paths:.claude/rules/x.md", id="paths-bad"),
    pytest.param(dict(classification=Classification(files=[])),
                 "docs-refresh:paths:empty", id="paths-empty"),
    pytest.param(dict(merge_danger_state="ONE-WAY"),
                 "docs-refresh:merge-danger:ONE-WAY", id="merge-danger-one-way"),
    pytest.param(dict(merge_danger_state="MISSING"),
                 "docs-refresh:merge-danger:MISSING", id="merge-danger-missing"),
    pytest.param(dict(fidelity_verdict="READY WITH NOTES"),
                 "docs-refresh:verdict:READY WITH NOTES", id="verdict-notes"),
    pytest.param(dict(fidelity_verdict=None), "docs-refresh:verdict:<none>",
                 id="verdict-none"),
])
def test_cond7_token_each_failed_clause_names_it(overrides, expected):
    c = _cond7(evaluate(_qualifying(**overrides)))
    assert c.blocked
    assert expected in c.reason
    assert c.reason.startswith(f"auto_merge: {DOCS_REFRESH_TOKEN} failed: ")


def test_cond7_token_uses_tree_gated_verdict_2():
    gated = dict(fidelity_verdict="READY WITH NOTES", fidelity_verdict_2="READY",
                 fidelity_tree_2="t1")
    ok = _cond7(evaluate(_qualifying(current_tree="t1", **gated)))
    assert not ok.blocked
    stale = _cond7(evaluate(_qualifying(current_tree="t2", **gated)))
    assert stale.blocked
    assert "READY WITH NOTES" in stale.reason
    assert "verdict source: verdict-1" in stale.reason


def test_cond7_token_never_releases_other_conditions():
    d = evaluate(_qualifying(unresolved_findings=None))
    assert not _cond7(d).blocked
    assert not d.armed
    assert 11 in {c.number for c in d.holds}


def test_docs_refresh_holds_on_self_governing():
    assert docs_refresh_holds(REFRESH_BRANCH, QUALIFYING_FILES, "SELF-GOVERNING", "READY") == \
        ["docs-refresh:merge-danger:SELF-GOVERNING"]


def test_evaluate_docs_only_ready_rules_date_bump():
    """Condition (7) reads ArmInputs.docs_refresh_rules_diffs: a bump passes, None holds."""
    files = [RULES_X]
    diffs = split_docs_refresh_rules_diffs(_rules_diff(RULES_X, BUMP_BODY), files)
    ok = _cond7(evaluate(_qualifying(classification=Classification(files=files),
                                     docs_refresh_rules_diffs=diffs)))
    assert not ok.blocked
    assert "docs-refresh:paths" not in ok.reason
    held = _cond7(evaluate(_qualifying(classification=Classification(files=files),
                                       docs_refresh_rules_diffs=None)))
    assert held.blocked
    assert "docs-refresh:paths:.claude/rules/x.md" in held.reason
