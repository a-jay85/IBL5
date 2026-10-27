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
                             docs_refresh_path_ok, evaluate)
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
    ("paths-claude-rules", REFRESH_BRANCH, ["ibl5/docs/a.md", ".claude/rules/x.md"],
     "TWO-WAY", "READY", ["docs-refresh:paths:.claude/rules/x.md"]),
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


def _shell(head_ref, files, md_state, verdict):
    if shutil.which("jq") is None:
        raise RuntimeError("jq is required: pr_docs_refresh_hold is dead without it")
    proc = subprocess.run(
        ["bash", "-c", 'source "$1"; shift; pr_docs_refresh_hold "$@"', "_", str(PR_ARMABLE),
         head_ref, json.dumps([{"path": p} for p in files]), md_state, verdict],
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


# --- Condition (7) through evaluate() ---------------------------------------------------

def _inputs(**overrides):
    """Fully-arming baseline (conditions 1-18 pass), copied from tests/test_armable.py."""
    base = dict(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                classification=Classification(), findings=[], unresolved_conformance=[], exec_mutations_holding=(), exec_mutations_tree_suspect=False,
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
    d = evaluate(_qualifying(pr_title="feat: x"))
    assert not _cond7(d).blocked
    assert not d.armed
