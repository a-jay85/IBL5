"""The deterministic gate on Executable Mutations repairs, over the real grammar owner."""
import difflib
import os
import sys
from types import SimpleNamespace

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fix_round, mutation_repair
from harness.mutation_repair import (eligible_rows, is_test_path, parse_new_match, parse_rows,
                                     proof_ok, rewrite_match, syntax_ok, validate_repair)

# Captured from harness.mutations.run_executable_mutations on a real repo, never hand-typed.
HELD_LINE = "row 8 HELD — true still exited 0 under mutation"
STALE_LINE = "row 7 NOT-APPLIED — match occurs 0 times in calc.py (need exactly 1)"

FENCE = "`" * 3
SOURCE = "def f(total_cap, used):\n    return total_cap - used\n"
OLD_MATCH = "return cap - used"
NEW_MATCH = "return total_cap - used"
REPLACE = "return total_cap + used"
STALE_REPLACE = "return cap + used"


def _row_block(row, match=OLD_MATCH, replace=REPLACE, check="false", file="calc.py"):
    return (f"{FENCE}mutation\nrow: {row}\nfile: {file}\nmatch: {match}\n"
            f"replace: {replace}\ncheck: {check}\n{FENCE}\n")


def _plan_text(*blocks, rows=(1, 2)):
    matrix = "".join(f"| {r} | x | CLI-executable | post-impl | `false` |\n" for r in rows)
    return ("# Fixture plan\n\n## Verification Matrix\n\n"
            "| # | What to verify | Test type | Timing | Test file / location |\n"
            "|---|---------------|-----------|--------|---------------------|\n"
            f"{matrix}\n## Executable Mutations\n\n" + "\n".join(blocks))


def _parsed(tmp_path, name, *blocks):
    plan = tmp_path / name
    plan.write_text(_plan_text(*blocks))
    rows = parse_rows(plan, tmp_path)
    assert rows is not None
    return rows


def _repair(tmp_path, new_block, *, old_block=None, source=SOURCE, repaired=("1",)):
    (tmp_path / "calc.py").write_text(source)
    old = _parsed(tmp_path, "old.md", old_block or _row_block(1))
    new = _parsed(tmp_path, "new.md", new_block)
    return validate_repair(old, new, set(repaired), lambda p: (tmp_path / p).read_text())


def test_refuses_deleted_row(tmp_path):
    (tmp_path / "calc.py").write_text(SOURCE)
    old = _parsed(tmp_path, "old.md", _row_block(1), _row_block(2))
    new = _parsed(tmp_path, "new.md", _row_block(1))
    assert validate_repair(old, new, set(), lambda p: SOURCE) == ["row-count-changed"]


def test_refuses_changed_check(tmp_path):
    reasons = _repair(tmp_path, _row_block(1, check="true"))
    assert reasons == ["check-changed row 1"]


def test_refuses_changed_replace(tmp_path):
    reasons = _repair(tmp_path, _row_block(1, replace="return cap * used"))
    assert reasons == ["replace-changed row 1"]


def test_refuses_match_not_exactly_once(tmp_path):
    source = SOURCE + "def g(total_cap, used):\n    return total_cap - used\n"
    reasons = _repair(tmp_path, _row_block(1, match=NEW_MATCH), source=source)
    assert reasons == ["match-count row 1"]


def test_refuses_dissimilar_match(tmp_path):
    source = "def f(total_cap, used):\n    x = 1\n    return total_cap - used\n"
    reasons = _repair(tmp_path, _row_block(1, match="x = 1"), source=source)
    assert reasons == ["match-dissimilar row 1"]


def test_refuses_row_weakened_to_always_trip(tmp_path):
    # The new match stops short of the closing bracket, so the fixed replace leaves a parse error
    # that fails every check no matter how strong the test is.
    source = "def f(total_cap, used):\n    return [total_cap - used]\n"
    old_block = _row_block(1, match="return [cap - used]", replace="return [cap + used]")
    new_block = _row_block(1, match="return [total_cap - used", replace="return [cap + used]")
    reasons = _repair(tmp_path, new_block, old_block=old_block, source=source)
    assert reasons == ["mutation-breaks-syntax row 1"]


def test_refuses_reordered_rows(tmp_path):
    (tmp_path / "calc.py").write_text(SOURCE)
    old = _parsed(tmp_path, "old.md", _row_block(1), _row_block(2))
    new = _parsed(tmp_path, "new.md", _row_block(2), _row_block(1))
    assert validate_repair(old, new, set(), lambda p: SOURCE) == ["row-order-changed"]


def test_refuses_changed_file(tmp_path):
    (tmp_path / "other.py").write_text(SOURCE)
    reasons = _repair(tmp_path, _row_block(1, file="other.py"))
    assert reasons == ["file-changed row 1"]


def test_refuses_match_change_on_row_not_repaired(tmp_path):
    reasons = _repair(tmp_path, _row_block(1, match=NEW_MATCH), repaired=())
    assert reasons == ["unexpected-match-change row 1"]


def test_refuses_row_that_was_not_stale(tmp_path):
    source = SOURCE + "def g(cap, used):\n    return cap - used\n"
    reasons = _repair(tmp_path, _row_block(1, match=NEW_MATCH), source=source)
    assert reasons == ["not-stale row 1"]


def test_unreadable_file_fails_closed(tmp_path):
    (tmp_path / "calc.py").write_text(SOURCE)
    old = _parsed(tmp_path, "old.md", _row_block(1))
    new = _parsed(tmp_path, "new.md", _row_block(1, match=NEW_MATCH))
    assert validate_repair(old, new, {"1"}, lambda p: None) == ["not-stale row 1", "match-count row 1"]


def test_refuses_replace_with_renamed_away_identifier(tmp_path):
    reasons = _repair(tmp_path, _row_block(1, match=NEW_MATCH, replace=STALE_REPLACE),
                      old_block=_row_block(1, replace=STALE_REPLACE))
    assert reasons == ["replace-stale-identifier row 1"]


def test_accepts_stale_row_repair_3680(tmp_path):
    (tmp_path / "calc.py").write_text(SOURCE)
    text = _plan_text(_row_block(1))
    plan = tmp_path / "plan.md"
    plan.write_text(text)
    old = parse_rows(plan, tmp_path)

    rewritten = rewrite_match(text, "1", NEW_MATCH)
    assert rewritten != text
    copy = tmp_path / "copy.md"
    copy.write_text(rewritten)
    new = parse_rows(copy, tmp_path)

    assert new[0].match == NEW_MATCH
    assert (new[0].row, new[0].file, new[0].check, new[0].replace) == (
        old[0].row, old[0].file, old[0].check, old[0].replace)
    assert validate_repair(old, new, {"1"}, lambda p: (tmp_path / p).read_text()) == []


def test_rewrite_touches_only_the_named_row(tmp_path):
    text = _plan_text(_row_block(1), _row_block(2))
    rewritten = rewrite_match(text, "2", NEW_MATCH)
    diff = [(a, b) for a, b in zip(text.split("\n"), rewritten.split("\n")) if a != b]
    assert diff == [(f"match: {OLD_MATCH}", f"match: {NEW_MATCH}")]
    plan = tmp_path / "p.md"
    plan.write_text(rewritten)
    (tmp_path / "calc.py").write_text(SOURCE)
    rows = parse_rows(plan, tmp_path)
    assert [r.match for r in rows] == [OLD_MATCH, NEW_MATCH]


def test_rewrite_refuses_a_row_it_cannot_pin_down():
    assert rewrite_match(_plan_text(_row_block(1)), "9", NEW_MATCH) is None
    two = _plan_text(_row_block(1), _row_block(1))
    assert rewrite_match(two, "1", NEW_MATCH) is None
    no_match = f"## Executable Mutations\n\n{FENCE}mutation\nrow: 1\nreplace: x\n{FENCE}\n"
    assert rewrite_match(no_match, "1", NEW_MATCH) is None
    two_match = f"## Executable Mutations\n\n{FENCE}mutation\nrow: 1\nmatch: a\nmatch: b\n{FENCE}\n"
    assert rewrite_match(two_match, "1", NEW_MATCH) is None


def test_rewrite_ignores_blocks_outside_the_section_and_other_fences():
    outside = f"## Notes\n\n{FENCE}mutation\nrow: 1\nmatch: old\n{FENCE}\n"
    assert rewrite_match(outside, "1", NEW_MATCH) is None
    illustrated = (f"## Executable Mutations\n\n{FENCE}{FENCE}\n{FENCE}mutation\nrow: 1\n"
                   f"match: old\n{FENCE}\n{FENCE}{FENCE}\n")
    assert rewrite_match(illustrated, "1", NEW_MATCH) is None


def test_parse_rows_returns_none_when_the_owner_refuses(tmp_path):
    (tmp_path / "calc.py").write_text(SOURCE)
    plan = tmp_path / "bad.md"
    plan.write_text(_plan_text(_row_block(99)))   # row 99 is not in the matrix
    assert parse_rows(plan, tmp_path) is None


def test_parse_rows_returns_none_when_the_lib_cannot_run(tmp_path):
    plan = tmp_path / "p.md"
    plan.write_text(_plan_text(_row_block(1)))
    assert parse_rows(plan, tmp_path, lib=tmp_path / "no-such-lib") is None


def test_eligible_rows_near_miss_lines():
    assert eligible_rows([HELD_LINE, STALE_LINE]) == (["8"], ["7"])
    near_misses = [
        "row 3 NOT-APPLIED — match occurs 2 times in calc.py (need exactly 1)",
        "row 4 NOT-APPLIED — could not write calc.py",
        "row 5 BASELINE-FAILED — false exited 1 before any mutation",
        "row 6 UNDECIDED — check timed out",
        "row 7 REVERT-FAILED — journal retained",
        "executor-unavailable: run exited 2",
    ]
    assert eligible_rows(near_misses) == ([], [])
    assert eligible_rows(None) == ([], [])


def test_eligible_rows_cap_and_suffix_ids():
    held = [f"row {i} HELD — x" for i in range(1, 6)]
    assert eligible_rows(held)[0] == ["1", "2", "3"]
    stale = [f"row {i}-neg NOT-APPLIED — match occurs 0 times in a.py (need exactly 1)" for i in range(1, 6)]
    assert eligible_rows(stale)[1] == ["1-neg", "2-neg", "3-neg"]


def test_parse_new_match_rejects_envelopes():
    assert parse_new_match("thinking\nNEW-MATCH: return x\n") == "return x"
    for reply in ('{"NEW-MATCH": "x"}', "NEW-MATCH: a\nNEW-MATCH: b\n", "NEW-MATCH:x",
                  "NEW-MATCH: ", "NEW-MATCH: a\r\n", "", None):
        assert parse_new_match(reply) is None
    # No repair recorded means the sticky line carries no repaired clause.
    line = fix_round.summary_line({"status": "no-change", "reevaluate": True, "items": [],
                                   "row_repairs": []})
    assert "repaired" not in line


def test_syntax_ok_by_file_type(tmp_path):
    assert syntax_ok("a.py", "x = 1\n")
    assert not syntax_ok("a.py", "x = (\n")
    assert not syntax_ok("a.py", "x = 1\0\n")
    assert syntax_ok("a.sh", "echo ok\n")
    assert not syntax_ok("a.sh", "if true; then\n")
    assert syntax_ok("bin/tool", "#!/usr/bin/env bash\necho ok\n")
    assert not syntax_ok("bin/tool", "#!/usr/bin/env bash\nif true; then\n")
    assert not syntax_ok("a.md", "# prose\n")
    assert not syntax_ok("a.txt", "x\n")


def test_syntax_ok_php_without_a_parser_fails_closed(monkeypatch):
    monkeypatch.setattr(mutation_repair.shutil, "which", lambda name: None)
    assert not syntax_ok("a.php", "<?php echo 1;\n")


def _report(*results, holding=(), suspect=False):
    return SimpleNamespace(results=tuple(SimpleNamespace(row=r, kind=k) for r, k in results),
                           holding=tuple(holding), tree_suspect=suspect)


def test_proof_ok_requires_every_wanted_row_tripped():
    assert proof_ok(_report((8, "TRIPPED")), ["8"], {"8"})
    held = _report((8, "HELD"), holding=["row 8 HELD — x"])
    assert not proof_ok(held, ["8"], {"8"})


def test_proof_ok_fails_closed():
    assert not proof_ok(None, ["8"], {"8"})
    assert not proof_ok(_report(), ["8"], {"8"})
    assert not proof_ok(_report((8, "TRIPPED"), suspect=True), ["8"], {"8"})
    unavailable = _report((8, "TRIPPED"), holding=["executor-unavailable: run exited 2"])
    assert not proof_ok(unavailable, ["8"], {"8"})


def test_proof_ok_allows_only_rows_that_already_held():
    earlier = _report((8, "TRIPPED"), (9, "HELD"), holding=["row 9 HELD — x"])
    assert proof_ok(earlier, ["8"], {"8", "9"})
    assert not proof_ok(earlier, ["8"], {"8"})


def test_repeated_executor_failure_never_passes_the_proof():
    line = "executor-unavailable: no lib"
    report = SimpleNamespace(results=(SimpleNamespace(row="8", kind="TRIPPED"),), holding=(line,),
                             tree_suspect=False)
    before = mutation_repair.holding_row_ids([line, HELD_LINE])
    assert before == {"8"}
    assert not proof_ok(report, ["8"], before)


def test_is_test_path():
    for path in ("tools/postplan-harness/tests/test_x.py", "ibl5/tests/Foo/BarTest.php",
                 "bin/test-thing", "x/test_y.py", "x/YTest.php", "x/y.spec.ts"):
        assert is_test_path(path)
    for path in ("tools/postplan-harness/harness/x.py", "ibl5/classes/Foo.php",
                 "bin/check-thing", "x/contest.py", "<unreadable-delta>"):
        assert not is_test_path(path)


# --- Per-row verdicts and diff provenance -------------------------------------------------

def test_split_reasons_separates_batch_and_row_reasons():
    split = mutation_repair.split_reasons
    assert split(["match-dissimilar row 40", "not-stale row 7", "row-order-changed"]) == (
        ["row-order-changed"], {"40", "7"})
    assert split(["match-dissimilar row 40"]) == ([], {"40"})


def test_split_reasons_treats_proposal_mismatch_as_batch():
    split = mutation_repair.split_reasons
    assert split(["proposal-mismatch row 8", "match-dissimilar row 40"]) == (
        ["proposal-mismatch row 8"], {"40"})
    # Near-miss: free text with numbers is not `<reason> row <id>`, so it voids the batch.
    assert split(["checks 3 and 4"]) == (["checks 3 and 4"], set())


R40_FILE = "tools/postplan-harness/harness/external_scope.py"
R40_OLD = "line = line.strip()"
R40_NEW = "line = line.rstrip() if keep_indent else line.strip()"
R40_REPLACE = "line = line"
R40_TEXT = ("def _norm(line, keep_indent=False):\n"
            "    line = line.rstrip() if keep_indent else line.strip()\n"
            "    return line\n")
R40_HEAD = (f"diff --git a/{R40_FILE} b/{R40_FILE}\n"
            "index 1111111..2222222 100644\n"
            f"--- a/{R40_FILE}\n"
            f"+++ b/{R40_FILE}\n")
R40_DIFF = (R40_HEAD
            + "@@ -1,3 +1,3 @@\n"
            " def _norm(line, keep_indent=False):\n"
            "-    line = line.strip()\n"
            "+    line = line.rstrip() if keep_indent else line.strip()\n"
            "     return line\n")


def _r40_rows(tmp_path, text=R40_TEXT, new_match=R40_NEW):
    src = tmp_path / R40_FILE
    src.parent.mkdir(parents=True, exist_ok=True)
    src.write_text(text)
    rows = []
    for name, match in (("old.md", R40_OLD), ("new.md", new_match)):
        plan = tmp_path / name
        plan.write_text(_plan_text(_row_block(40, match=match, replace=R40_REPLACE, file=R40_FILE),
                                   rows=(40,)))
        parsed = parse_rows(plan, tmp_path)
        assert parsed is not None
        rows.append(parsed)
    return rows[0], rows[1], {R40_FILE: text}.get


def test_provenance_accepts_row40_rewrite(tmp_path):
    ratio = difflib.SequenceMatcher(None, R40_OLD, R40_NEW).ratio()
    assert ratio < mutation_repair.MATCH_SIMILARITY_MIN
    old, new, stub = _r40_rows(tmp_path)
    assert validate_repair(old, new, {"40"}, read_file=stub, diff=R40_DIFF) == []


def test_similar_match_accepted_without_diff(tmp_path):
    ratio = difflib.SequenceMatcher(None, OLD_MATCH, NEW_MATCH).ratio()
    assert ratio >= mutation_repair.MATCH_SIMILARITY_MIN
    (tmp_path / "calc.py").write_text(SOURCE)
    old = _parsed(tmp_path, "old.md", _row_block(1))
    new = _parsed(tmp_path, "new.md", _row_block(1, match=NEW_MATCH))
    assert validate_repair(old, new, {"1"}, read_file={"calc.py": SOURCE}.get, diff=None) == []


def test_provenance_refuses_new_match_only_in_context(tmp_path):
    diff = (R40_HEAD + "@@ -1,3 +1,3 @@\n"
            " def _norm(line, keep_indent=False):\n"
            "-    line = line.strip()\n"
            "+    pass\n"
            "     line = line.rstrip() if keep_indent else line.strip()\n")
    old, new, stub = _r40_rows(tmp_path)
    assert validate_repair(old, new, {"40"}, read_file=stub, diff=diff) == ["match-dissimilar row 40"]


def test_provenance_refuses_pair_split_across_hunks(tmp_path):
    diff = (R40_HEAD
            + "@@ -1,1 +1,1 @@\n"
            "-    line = line.strip()\n"
            "@@ -9,1 +9,1 @@\n"
            "+    line = line.rstrip() if keep_indent else line.strip()\n")
    old, new, stub = _r40_rows(tmp_path)
    assert validate_repair(old, new, {"40"}, read_file=stub, diff=diff) == ["match-dissimilar row 40"]


def test_provenance_refuses_pair_in_different_file(tmp_path):
    diff = R40_DIFF.replace(R40_FILE, "tools/postplan-harness/harness/other.py")
    assert R40_FILE not in diff
    old, new, stub = _r40_rows(tmp_path)
    assert validate_repair(old, new, {"40"}, read_file=stub, diff=diff) == ["match-dissimilar row 40"]


def test_provenance_refuses_when_diff_unreadable(tmp_path):
    old, new, stub = _r40_rows(tmp_path)
    assert validate_repair(old, new, {"40"}, read_file=stub, diff=None) == ["match-dissimilar row 40"]


def test_provenance_refuses_unparseable_hunk_line(tmp_path):
    diff = R40_DIFF.replace("-    line = line.strip()\n", "-    line = line.strip()\ngarbage\n")
    assert "\ngarbage\n" in diff
    assert mutation_repair.rewritten_in_diff(diff, R40_FILE, R40_OLD, R40_NEW) is False
    old, new, stub = _r40_rows(tmp_path)
    assert validate_repair(old, new, {"40"}, read_file=stub, diff=diff) == ["match-dissimilar row 40"]


def test_provenance_header_like_body_lines_stay_in_hunk():
    diff = ("diff --git a/q.py b/q.py\n--- a/q.py\n+++ b/q.py\n@@ -1 +1 @@\n"
            "--- x = 1\n+++ x = 2\n")
    assert mutation_repair.rewritten_in_diff(diff, "q.py", "-- x = 1", "++ x = 2") is True


def test_provenance_refuses_multiline_match():
    diff = ("diff --git a/m.py b/m.py\n--- a/m.py\n+++ b/m.py\n@@ -1,2 +1,2 @@\n"
            "-a = 1\n-b = 2\n+a = 10\n+b = 20\n")
    assert mutation_repair.rewritten_in_diff(diff, "m.py", "a = 1\nb = 2", "a = 10\nb = 20") is False


def test_provenance_keeps_match_count_check(tmp_path):
    twice = R40_TEXT + "    line = line.rstrip() if keep_indent else line.strip()\n"
    old, new, stub = _r40_rows(tmp_path, text=twice)
    reasons = validate_repair(old, new, {"40"}, read_file=stub, diff=R40_DIFF)
    assert "match-count row 40" in reasons
