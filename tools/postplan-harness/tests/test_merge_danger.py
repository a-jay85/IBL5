"""Unit tests for harness/merge_danger.py — the PR body's Merge danger field."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import merge_danger as md
from harness.gate_backtest import GateChange
from harness.merge_danger import MalformedMergeDanger, MergeDanger


def _none(_path):
    return None


def _derive(changed, diff="", gates=(), read=_none):
    return md.derive_merge_danger(changed, diff, list(gates), read)


def _migration_diff(path, *added):
    lines = [f"diff --git a/{path} b/{path}", "new file mode 100644", "--- /dev/null", f"+++ b/{path}",
             "@@ -0,0 +1 @@"]
    lines += [f"+{a}" for a in added]
    return "\n".join(lines) + "\n"


MIG = "ibl5/migrations/200_drop_col.sql"


# --- derivation: one-way triggers -------------------------------------------------------------

def test_destructive_migration_forces_one_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "ALTER TABLE ibl_plr DROP COLUMN foo;"))
    assert d.door == "one-way"
    assert d.blast == "site-wide"
    assert f"destructive-migration:{MIG}" in d.triggers


def test_destructive_bypass_marker_does_not_lower_door():
    diff = _migration_diff(MIG, "-- destructive-migration[trigger]: reason text here", "ALTER TABLE t DROP COLUMN c;")
    assert _derive([("A", MIG)], diff).door == "one-way"


def test_additive_migration_is_two_way_site_wide():
    d = _derive([("A", MIG)], _migration_diff(MIG, "ALTER TABLE t ADD COLUMN c INT;"))
    assert (d.door, d.blast) == ("two-way", "site-wide")


def test_destructive_text_outside_a_migration_is_ignored():
    diff = (_migration_diff(MIG, "ALTER TABLE t ADD COLUMN c INT;")
            + _migration_diff("docs/x.md", "DROP TABLE example in prose"))
    assert _derive([("A", MIG), ("A", "docs/x.md")], diff).door == "two-way"


@pytest.mark.parametrize("stmt", [
    "ALTER TABLE t DROP COLUMN c;",
    "DROP TABLE t;",
    "TRUNCATE t;",
    "ALTER TABLE t RENAME COLUMN a TO b;",
    "ALTER TABLE t RENAME TO u;",
])
def test_each_destructive_statement_still_forces_one_way(stmt):
    d = _derive([("A", MIG)], _migration_diff(MIG, stmt))
    assert d.door == "one-way"
    assert f"destructive-migration:{MIG}" in d.triggers


def test_comment_only_destructive_text_in_migration_is_two_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "-- Rollback: ALTER TABLE t DROP COLUMN c;",
                                              "ALTER TABLE t ADD COLUMN c INT;"))
    assert d.door == "two-way"
    assert d.triggers == ()


def test_hash_comment_destructive_text_is_two_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "# TRUNCATE t before rerun",
                                              "ALTER TABLE t ADD COLUMN c INT;"))
    assert d.door == "two-way"


def test_block_comment_destructive_text_in_migration_is_two_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "/*", "DROP TABLE t", "*/",
                                              "ALTER TABLE t ADD COLUMN c INT;"))
    assert d.door == "two-way"


def test_destructive_statement_with_trailing_comment_is_one_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "ALTER TABLE t DROP COLUMN c; -- bye"))
    assert d.door == "one-way"
    assert f"destructive-migration:{MIG}" in d.triggers


def test_destructive_after_closed_block_is_one_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "/* note */ ALTER TABLE t DROP COLUMN c;"))
    assert d.door == "one-way"


def test_destructive_inside_unterminated_block_is_one_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "/* start", "ALTER TABLE t DROP COLUMN c;"))
    assert d.door == "one-way"


def test_dash_dash_inside_string_does_not_hide_statement():
    d = _derive([("A", MIG)], _migration_diff(
        MIG, "INSERT INTO t VALUES ('x -- y'); ALTER TABLE t DROP COLUMN c;"))
    assert d.door == "one-way"


def test_executable_comment_drop_is_one_way():
    d = _derive([("A", MIG)], _migration_diff(MIG, "/*!40000 DROP TABLE t */"))
    assert d.door == "one-way"


def test_db_sync_prod_is_external_one_way():
    d = _derive([("M", "bin/db-sync-prod")])
    assert (d.door, d.blast) == ("one-way", "external")
    assert "external-path:bin/db-sync-prod" in d.triggers


def test_notify_workflow_text_is_external_one_way():
    wf = ".github/workflows/nightly.yml"
    d = _derive([("M", wf)], read=lambda p: "env:\n  DISCORD_WEBHOOK: x\n" if p == wf else None)
    assert (d.door, d.blast) == ("one-way", "external")
    assert f"notify-workflow:{wf}" in d.triggers


def test_workflow_without_notify_text_is_two_way_site_wide():
    wf = ".github/workflows/ci.yml"
    gates = [GateChange(wf, "ci-workflow", "not-replayable", None, "CI workflow")]
    d = _derive([("M", wf)], gates=gates, read=lambda p: "jobs:\n  test: {}\n")
    assert (d.door, d.blast) == ("two-way", "site-wide")


def test_notify_action_and_mail_class_are_external():
    for path in (".github/actions/notify-discord/action.yml", "ibl5/classes/Mail/Mailer.php", "bin/discord-dm"):
        d = _derive([("M", path)])
        assert (d.door, d.blast) == ("one-way", "external"), path


def test_arming_path_is_one_way():
    d = _derive([("M", "bin/lib/pr-armable.sh")])
    assert d.door == "one-way"
    assert d.blast == "site-wide"
    assert "arming-path:bin/lib/pr-armable.sh" in d.triggers


def test_gate_removed_is_one_way():
    gates = [GateChange("bin/check-foo", "check-script", "removed", None, "gate deleted")]
    d = _derive([("D", "bin/check-foo")], gates=gates)
    assert d.door == "one-way"
    assert "gate-removed:bin/check-foo" in d.triggers


def test_edited_check_script_is_two_way_site_wide():
    gates = [GateChange("bin/check-foo", "check-script", "replayable", None, "")]
    d = _derive([("M", "bin/check-foo")], gates=gates)
    assert (d.door, d.blast) == ("two-way", "site-wide")
    assert d.triggers == ()


# --- derivation: blast radius -----------------------------------------------------------------

def test_empty_diff_is_two_way_localized():
    d = _derive([])
    assert (d.door, d.blast) == ("two-way", "localized")
    assert d.why == "mechanical: no one-way trigger in the realized diff"


def test_three_files_one_dir_localized():
    paths = [("M", f"ibl5/classes/Trade/{n}.php") for n in ("A", "B", "C")]
    assert _derive(paths).blast == "localized"


def test_four_files_one_module_is_module():
    paths = [("M", f"ibl5/classes/Trade/{n}.php") for n in ("A", "B", "C")]
    paths.append(("M", "ibl5/classes/Trade/Sub/D.php"))
    assert _derive(paths).blast == "module"
    same_dir = [("M", f"ibl5/classes/Trade/{n}.php") for n in ("A", "B", "C", "D")]
    assert _derive(same_dir).blast == "module"


def test_two_modules_is_site_wide():
    d = _derive([("M", "ibl5/classes/Trade/A.php"), ("M", "ibl5/classes/Draft/B.php")])
    assert (d.door, d.blast) == ("two-way", "site-wide")


def test_tools_subdir_is_one_module():
    paths = [("M", "tools/postplan-harness/a.py"), ("M", "tools/postplan-harness/x/b.py"),
             ("M", "tools/postplan-harness/y/c.py"), ("M", "tools/postplan-harness/z/d.py")]
    assert _derive(paths).blast == "module"


# --- merge_verdicts ---------------------------------------------------------------------------

ONE = MergeDanger("one-way", "site-wide", "mechanical: arming-path:bin/lib/pr-armable.sh",
                  ("arming-path:bin/lib/pr-armable.sh",))
TWO = MergeDanger("two-way", "module", "mechanical: no one-way trigger in the realized diff")


def test_llm_two_way_cannot_lower_mechanical_one_way():
    out = md.merge_verdicts(ONE, "two-way", "localized", "only a small doc change, nothing risky")
    assert out.door == "one-way"
    assert out.blast == "site-wide"


def test_llm_cannot_narrow_blast():
    assert md.merge_verdicts(TWO, "two-way", "localized", None).blast == "module"


def test_llm_may_raise_door_and_blast():
    out = md.merge_verdicts(TWO, "one-way", "external", "sends a DM that cannot be recalled later")
    assert (out.door, out.blast) == ("one-way", "external")
    assert out.why == "sends a DM that cannot be recalled later"


def test_llm_unknown_enum_is_ignored():
    out = md.merge_verdicts(TWO, "three-way", "galactic", None)
    assert (out.door, out.blast) == ("two-way", "module")


def test_short_llm_why_falls_back_to_mechanical():
    assert md.merge_verdicts(TWO, None, None, "too short").why == TWO.why
    assert md.merge_verdicts(TWO, None, None, "").why == TWO.why


def test_one_way_why_keeps_trigger_list():
    out = md.merge_verdicts(ONE, None, None, "edits the arming predicate library itself")
    assert out.why.startswith(ONE.why + ". ")
    assert "arming-path:bin/lib/pr-armable.sh" in md.render_merge_danger(out)
    assert out.triggers == ONE.triggers


def test_llm_why_is_sanitized_and_clamped():
    out = md.merge_verdicts(TWO, None, None, "line one | pipe\nline two " + "x" * 400)
    assert "\n" not in out.why and "|" not in out.why
    assert len(out.why) <= md.WHY_MAX


def test_reapplying_merge_verdicts_does_not_duplicate_the_prefix():
    first = md.merge_verdicts(ONE, "one-way", ONE.blast, "drops the trade_mode column data for every team")
    second = md.merge_verdicts(ONE, first.door, first.blast, first.why)
    third = md.merge_verdicts(ONE, second.door, second.blast, second.why)
    assert second.why == first.why == third.why
    assert first.why.count("mechanical:") == 1
    assert first.why.endswith("for every team")


def test_reapplication_is_a_fixed_point_after_truncation():
    llm = "this sentence is exactly forty chars ok " + "y" * 300
    first = md.merge_verdicts(ONE, "one-way", ONE.blast, llm)
    second = md.merge_verdicts(ONE, first.door, first.blast, first.why)
    assert len(first.why) <= md.WHY_MAX
    assert second.why == first.why
    assert second.why.count("mechanical:") == 1


def test_reapplication_is_a_fixed_point_with_a_long_prefix():
    long_mech = md.MergeDanger("one-way", ONE.blast, "m" * 185)
    first = md.merge_verdicts(long_mech, "one-way", ONE.blast, "z" * 300)
    second = md.merge_verdicts(long_mech, first.door, first.blast, first.why)
    assert first.why == long_mech.why
    assert second.why == first.why


def test_prefixed_prior_with_short_core_falls_back_to_mechanical():
    assert md.merge_verdicts(ONE, "one-way", ONE.blast, f"{ONE.why}. tiny").why == ONE.why


def test_stale_prefix_is_dropped_when_floor_is_two_way():
    out = md.merge_verdicts(TWO, None, None,
                            "mechanical: arming-path:x/y.py. edits the arming predicate library itself")
    assert out.why == "edits the arming predicate library itself"


def test_llm_core_strips_only_the_leading_prefix():
    assert md._llm_core("mechanical: a:b/c.sql. keep this. and this") == "keep this. and this"
    assert md._llm_core("mechanical: no one-way trigger in the realized diff") == ""
    assert md._llm_core("plain words only here") == "plain words only here"


def test_llm_one_way_naming_an_irreversible_effect_still_raises_under_the_bar():
    out = md.merge_verdicts(TWO, "one-way", "site-wide", "drops the trade_mode column data for every team",
                            llm_raise_bar=True)
    assert out.door == "one-way"
    assert out.why.endswith("for every team")


def test_llm_one_way_without_a_concrete_effect_is_kept_at_the_floor():
    why = "the migration widens an ENUM and adds a table for the pipeline"
    out = md.merge_verdicts(TWO, "one-way", "site-wide", why, llm_raise_bar=True)
    assert out.door == "two-way"
    assert out.blast == "site-wide"
    assert out.why == why


def test_pr3472_style_why_is_kept_at_the_floor():
    why = "widens the bug status ENUM, adds the operator context table and backfills rows"
    assert md.merge_verdicts(TWO, "one-way", "site-wide", why, llm_raise_bar=True).door == "two-way"


def test_bar_never_lowers_a_mechanical_one_way():
    out = md.merge_verdicts(ONE, "two-way", "localized", "nothing risky here at all really", llm_raise_bar=True)
    assert out.door == "one-way"


def test_bar_is_off_by_default_so_a_human_raise_survives():
    out = md.merge_verdicts(TWO, "one-way", "localized", "a human marked this as needing careful review")
    assert out.door == "one-way"


def test_bar_does_not_touch_blast_radius():
    out = md.merge_verdicts(TWO, "one-way", "external", "a vague worry about this change overall",
                            llm_raise_bar=True)
    assert out.blast == "external"
    assert out.door == "two-way"


@pytest.mark.parametrize("text, expected", [
    ("sends a Discord DM that cannot be recalled", True),
    ("truncates ibl_hist before the rerun", True),
    ("overwrites prod rows in place", True),
    ("renames a column other code reads", True),
    ("data loss for every team on revert", True),
    ("adds a table and widens an ENUM", False),
    ("edits docs and tests under the harness", False),
    ("a lossless additive change", False),
])
def test_irreversible_lexicon(text, expected):
    assert bool(md._IRREVERSIBLE_RE.search(text)) == expected


# --- render / parse / classify / validate / inject --------------------------------------------

def test_render_parse_roundtrip():
    line = md.render_merge_danger(ONE)
    assert md.FIELD_RE.match(line)
    parsed = md.parse_merge_danger(f"## Summary\n\ntext\n\n{line}\n")
    assert (parsed.door, parsed.blast, parsed.why) == (ONE.door, ONE.blast, ONE.why)


def test_parse_missing_returns_none():
    assert md.parse_merge_danger("## Summary\n\nMerge danger is discussed here.\n") is None
    assert md.parse_merge_danger("") is None


def test_parse_two_lines_is_malformed():
    line = md.render_merge_danger(TWO)
    with pytest.raises(MalformedMergeDanger):
        md.parse_merge_danger(f"{line}\n\n{line}\n")


def test_parse_bad_enum_is_malformed():
    with pytest.raises(MalformedMergeDanger):
        md.parse_merge_danger("**Merge danger:** Door: maybe · Blast radius: module · Why: twenty chars of reason here")
    with pytest.raises(MalformedMergeDanger):
        md.parse_merge_danger("**Merge danger:** Door: two-way · Blast radius: galaxy · Why: twenty chars of reason here")
    with pytest.raises(MalformedMergeDanger):
        md.parse_merge_danger("**Merge danger:** Door: two-way · Blast radius: module · Why: short")
    with pytest.raises(MalformedMergeDanger):
        md.parse_merge_danger("**Merge danger:** two-way, fine")


def test_classify_body_none_is_unreadable():
    assert md.classify_body(None) == "UNREADABLE"
    assert md.classify_body("") == "MISSING"
    assert md.classify_body(md.render_merge_danger(ONE)) == "ONE-WAY"
    assert md.classify_body(md.render_merge_danger(TWO)) == "TWO-WAY"
    assert md.classify_body("**Merge danger:** nope") == "MALFORMED"


def test_validate_reports_missing_and_malformed():
    assert md.validate_merge_danger_field(md.render_merge_danger(TWO)) == []
    missing = md.validate_merge_danger_field("## Summary\n")
    assert len(missing) == 1 and missing[0].startswith("merge-danger: MISSING:")
    bad = md.validate_merge_danger_field("**Merge danger:** nope")
    assert len(bad) == 1 and bad[0].startswith("merge-danger: MALFORMED:")


def test_inject_replaces_in_place_and_is_idempotent():
    body = "## Summary\n\n**Merge danger:** garbage\n\n## Test plan\n\n**Merge danger:** extra\n"
    once = md.inject_merge_danger(body, ONE)
    assert once.count("**Merge danger:**") == 1
    assert once.index("**Merge danger:**") < once.index("## Test plan")
    assert md.inject_merge_danger(once, ONE) == once
    appended = md.inject_merge_danger("## Summary\n\nbody\n", TWO)
    assert appended.endswith("\n\n" + md.render_merge_danger(TWO))
    assert md.inject_merge_danger(appended, TWO) == appended
