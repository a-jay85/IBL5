"""Unit tests for the advisory `**How to revert:**` field module."""
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import how_to_revert as htr

REPO_ROOT = Path(__file__).resolve().parents[3]

_MD_LINE = "**Merge danger:** Door: two-way · Blast radius: module · Why: x"
_MIGRATION_DIFF = (
    "diff --git a/ibl5/migrations/NNN_slug.sql b/ibl5/migrations/NNN_slug.sql\n"
    "new file mode 100644\n"
    "--- /dev/null\n"
    "+++ b/ibl5/migrations/NNN_slug.sql\n"
    "@@ -0,0 +1 @@\n"
    "+SELECT 1;\n"
)
_PLAIN_DIFF = (
    "diff --git a/ibl5/classes/Foo.php b/ibl5/classes/Foo.php\n"
    "--- a/ibl5/classes/Foo.php\n"
    "+++ b/ibl5/classes/Foo.php\n"
    "@@ -1 +1 @@\n"
    "-a\n"
    "+b\n"
)


def test_parse_returns_none_when_absent():
    assert htr.parse_how_to_revert("Summary\n\n" + _MD_LINE) is None


@pytest.mark.parametrize("body", ["", None])
def test_parse_returns_none_for_empty_and_none_body(body):
    assert htr.parse_how_to_revert(body) is None
    assert htr.count_field_lines(body) == 0


def test_parse_reads_text_after_prefix():
    body = f"Summary\n{htr.FIELD_PREFIX}   Open a PR with revert  \nFooter"
    assert htr.parse_how_to_revert(body) == "Open a PR with revert"


def test_default_plain_diff_is_git_revert():
    assert htr.default_how_to_revert(["ibl5/classes/Foo.php"]) == htr.PLAIN + "."


def test_default_migration_diff_adds_down_migration_step():
    assert htr.STEP_MIGRATION in htr.default_how_to_revert(["ibl5/migrations/NNN_slug.sql"])
    assert htr.STEP_MIGRATION not in htr.default_how_to_revert(["ibl5/migrations/README.md"])


def test_default_prod_sync_diff_adds_restore_step():
    assert htr.STEP_PROD_SYNC in htr.default_how_to_revert(["bin/db-sync-prod"])


@pytest.mark.parametrize("path", ["ibl5/.htaccess", "ibl5/composer.json", ".github/workflows/python-tests.yml"])
def test_default_config_diff_adds_redeploy_step(path):
    assert htr.STEP_CONFIG in htr.default_how_to_revert([path])
    assert htr.STEP_CONFIG not in htr.default_how_to_revert([".github/actions/x/action.yml"])


def test_default_combines_steps_on_one_line():
    out = htr.default_how_to_revert(
        [".github/workflows/python-tests.yml", "ibl5/migrations/NNN_slug.sql", "bin/db-sync-prod"])
    assert "\n" not in out
    assert out.index(htr.STEP_MIGRATION) < out.index(htr.STEP_PROD_SYNC) < out.index(htr.STEP_CONFIG)
    assert out.endswith(".")


def test_default_from_diff_reads_unified_diff_headers():
    two_files = _PLAIN_DIFF + _MIGRATION_DIFF
    assert htr.STEP_MIGRATION in htr.default_from_diff(two_files)
    assert htr.default_from_diff("") == htr.PLAIN + "."


def test_resolve_keeps_llm_text_for_plain_diff():
    text = "Revert the merge commit via a PR"
    assert htr.resolve_how_to_revert(text, htr.PLAIN + ".") == text


def test_resolve_short_llm_text_falls_back_to_default():
    plain = htr.PLAIN + "."
    assert htr.resolve_how_to_revert("revert it", plain) == plain
    assert htr.resolve_how_to_revert("x" * (htr.MIN_TEXT - 1), plain) == plain
    assert htr.resolve_how_to_revert("x" * htr.MIN_TEXT, plain) == "x" * htr.MIN_TEXT


def test_resolve_none_llm_text_falls_back():
    plain = htr.PLAIN + "."
    assert htr.resolve_how_to_revert(None, plain) == plain


def test_resolve_special_surface_uses_mechanical_floor():
    mechanical = htr.default_how_to_revert(["ibl5/migrations/NNN_slug.sql"])
    assert htr.resolve_how_to_revert("a long and perfectly fine LLM revert line", mechanical) == mechanical


def test_resolve_strips_newlines_and_pipes():
    out = htr.resolve_how_to_revert("revert  the\nmerge | then  redeploy", htr.PLAIN + ".")
    assert "\n" not in out and "|" not in out
    assert out == "revert the merge then redeploy"


def test_inject_places_line_directly_after_merge_danger():
    body = f"Summary\n\n{_MD_LINE}\n\nFooter"
    lines = htr.inject_how_to_revert(body, "undo it").split("\n")
    assert lines[lines.index(_MD_LINE) + 1] == htr.render_how_to_revert("undo it")


def test_inject_collapses_two_lines_to_one():
    body = f"Summary\n{htr.FIELD_PREFIX} one\n{_MD_LINE}\n{htr.FIELD_PREFIX} two\nFooter"
    out = htr.inject_how_to_revert(body, "undo it")
    assert htr.count_field_lines(out) == 1
    assert htr.parse_how_to_revert(out) == "undo it"


def test_inject_is_idempotent():
    body = f"Summary\n\n{_MD_LINE}\n\nFooter"
    once = htr.inject_how_to_revert(body, "undo it")
    assert htr.inject_how_to_revert(once, "undo it") == once
    bare = htr.inject_how_to_revert("Summary", "undo it")
    assert htr.inject_how_to_revert(bare, "undo it") == bare


def test_inject_appends_when_no_merge_danger_line():
    out = htr.inject_how_to_revert("Summary", "undo it")
    assert out == f"Summary\n\n{htr.FIELD_PREFIX} undo it"


@pytest.mark.parametrize("body", ["", None])
def test_inject_empty_body_yields_single_line(body):
    assert htr.inject_how_to_revert(body, "undo it") == f"{htr.FIELD_PREFIX} undo it"


def test_apply_to_body_prefers_llm_text_on_plain_diff():
    text = "Revert the merge commit via a PR"
    body = f"S\n{_MD_LINE}\n{htr.FIELD_PREFIX} {text}"
    out, source = htr.apply_to_body(body, _PLAIN_DIFF)
    assert source == "llm"
    assert htr.parse_how_to_revert(out) == text


def test_apply_to_body_uses_mechanical_on_migration_diff():
    body = f"S\n{_MD_LINE}\n{htr.FIELD_PREFIX} just revert the merge commit please"
    out, source = htr.apply_to_body(body, _MIGRATION_DIFF)
    assert source == "mechanical"
    assert htr.STEP_MIGRATION in (htr.parse_how_to_revert(out) or "")
    assert htr.count_field_lines(out) == 1


def test_pr_body_claims_documents_how_to_revert_rule():
    claims = (REPO_ROOT / ".claude/skills/post-plan/_pr-body-claims.md").read_text(encoding="utf-8")
    lines = claims.splitlines()
    assert "## How to revert rule" in lines
    start = lines.index("## How to revert rule")
    end = next((i for i in range(start + 1, len(lines)) if lines[i].startswith("## ")), len(lines))
    section = "\n".join(lines[start:end])
    for needle in (htr.FIELD_PREFIX, f"shorter than {htr.MIN_TEXT} characters",
                   "bin/db-sync-prod", "ibl5/.htaccess", "ibl5/composer.json"):
        assert needle in section, needle
    skill = (REPO_ROOT / ".claude/skills/post-plan/SKILL.md").read_text(encoding="utf-8")
    assert "§ How to revert rule" in skill
