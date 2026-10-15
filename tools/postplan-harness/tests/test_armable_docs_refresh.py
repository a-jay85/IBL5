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

from harness.armable import docs_refresh_holds, docs_refresh_path_ok

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
