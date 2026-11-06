"""conflict_report: record, mechanical lost-line detection, wording and safe rendering."""
import os
import subprocess
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import conflict_report as cr
from harness.armable import conflict_flag_path, record_conflict_resolution
from harness.conflict import purge_verdict_artifacts
from harness.fidelity import MERGE_DIGEST_HEADING, STICKY_MARKER
from test_gitad_stacked_rebase import _rev, _sh

CLEAN = "CONFLICT-REVIEW=CLEAN"
PROBLEM = "CONFLICT-REVIEW=FOUND-PROBLEM"


def _write_tree(d, files):
    for path, text in files.items():
        full = os.path.join(d, path)
        os.makedirs(os.path.dirname(full), exist_ok=True)
        with open(full, "w") as fh:
            fh.write(text)


def _build_repo(tmp_path, base, master, branch, resolved):
    """base commit, a branch commit and a master commit on top of it, then a resolved
    commit on master. Each stage dict maps path -> full text and overrides the tree."""
    d = str(tmp_path / "repo")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    _write_tree(d, base)
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    base_sha = _rev(d, "HEAD")
    _sh(d, "checkout", "-b", "side")
    _write_tree(d, branch)
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "branch")
    branch_sha = _rev(d, "HEAD")
    _sh(d, "checkout", "master")
    _write_tree(d, master)
    _sh(d, "add", "-A")
    _sh(d, "commit", "--allow-empty", "-m", "master")
    onto_sha = _rev(d, "HEAD")
    _write_tree(d, resolved)
    _sh(d, "add", "-A")
    _sh(d, "commit", "--allow-empty", "-m", "resolved")
    return {"repo": d, "base": base_sha, "branch": branch_sha, "onto": onto_sha,
            "head": _rev(d, "HEAD")}


def _record_for(tmp_path, repo, branch_name, files, **kw):
    cr.write_record(
        repo=repo["repo"], branch=branch_name, resolving_sha=repo["head"],
        resolved_files=files, target_ref=repo["onto"], review_verdict_path=None,
        retries=0, rung=None, fix_summary=None, branch_sha=repo["branch"],
        tmp_dir=str(tmp_path), **kw)
    return cr.read_records(branch_name, str(tmp_path))[0]


def _rec(**kw):
    rec = {"version": 1, "resolving_sha": "a" * 40, "files": ["a.sh"],
           "review": {"verdict": "", "excerpt": "", "retries": 0}, "rung": None,
           "fix_summary": None}
    rec.update(kw)
    return rec


# ---------------------------------------------------------------- record on disk


def test_record_survives_purge_verdict_artifacts():
    branch = f"report-purge-{uuid.uuid4().hex[:10]}"
    key = branch
    path = cr.record_path(branch)
    try:
        cr.write_record(
            repo=".", branch=branch, resolving_sha="d" * 40, resolved_files=["a.sh"],
            target_ref="HEAD", review_verdict_path=None, retries=0, rung=None,
            fix_summary=None)
        assert path.exists()
        purge_verdict_artifacts(key)
        assert path.exists()
        assert [r["resolving_sha"] for r in cr.read_records(branch)] == ["d" * 40]
    finally:
        if path.exists():
            path.unlink()


def test_onto_replay_empty_resolved_files_no_record(tmp_path):
    out = cr.write_record(
        repo=".", branch="onto-replay", resolving_sha="d" * 40, resolved_files=[],
        target_ref="HEAD", review_verdict_path=None, retries=0, rung=None,
        fix_summary=None, tmp_dir=str(tmp_path))
    assert out is None
    assert not cr.record_path("onto-replay", str(tmp_path)).exists()
    assert cr.read_records("onto-replay", str(tmp_path)) == []


def test_marker_bytes_unchanged_by_report_record(tmp_path):
    branch = "marker-bytes"
    record_conflict_resolution(branch, "e" * 40, str(tmp_path))
    marker = conflict_flag_path(branch, str(tmp_path))
    before = open(marker, "rb").read()
    cr.write_record(
        repo=".", branch=branch, resolving_sha="e" * 40, resolved_files=["a.sh"],
        target_ref="HEAD", review_verdict_path=None, retries=0, rung=None,
        fix_summary=None, tmp_dir=str(tmp_path))
    assert open(marker, "rb").read() == before


def test_read_records_unparsable_file_is_empty(tmp_path):
    cr.record_path("junk", str(tmp_path)).write_text("{not json")
    assert cr.read_records("junk", str(tmp_path)) == []


def test_record_keeps_review_verdict_and_excerpt(tmp_path):
    verdict = tmp_path / "v.ok"
    verdict.write_text(f"{PROBLEM}\n{PROBLEM}\n\nThe merge replaced master's helper.\nmore\n")
    cr.write_record(
        repo=".", branch="rev", resolving_sha="f" * 40, resolved_files=["a.sh"],
        target_ref="HEAD", review_verdict_path=verdict, retries=1, rung="opus-retry",
        fix_summary="fixed it", tmp_dir=str(tmp_path))
    rec = cr.read_records("rev", str(tmp_path))[0]
    assert rec["review"] == {"verdict": PROBLEM,
                             "excerpt": "The merge replaced master's helper.", "retries": 1}
    assert rec["rung"] == "opus-retry"


# ----------------------------------------------------------------- lost code


def test_lost_lines_read_from_git_objects(tmp_path):
    base = "run() {\n  echo start\n}\n"
    repo = _build_repo(
        tmp_path,
        base={"bin/run.sh": base},
        master={"bin/run.sh": "run() {\n  echo start\n  master_added_one\n  master_added_two\n}\n"},
        branch={"bin/run.sh": "run() {\n  echo start\n  branch_added_one\n}\n"},
        resolved={"bin/run.sh": "run() {\n  echo start\n  branch_added_one\n}\n"},
    )
    rec = _record_for(tmp_path, repo, "lost-git", ["bin/run.sh"])
    loss = cr.file_loss(repo["repo"], rec, "bin/run.sh", repo["head"])
    assert loss.master_lost == ["  master_added_one", "  master_added_two"]
    assert loss.branch_lost == []
    assert not loss.compare_failed


def test_missing_side_object_marks_compare_failed(tmp_path):
    repo = _build_repo(tmp_path, base={"a.sh": "one_line\n"}, master={"a.sh": "two_line\n"},
                       branch={"a.sh": "three_line\n"}, resolved={"a.sh": "three_line\n"})
    rec = _record_for(tmp_path, repo, "gone", ["a.sh"])
    rec["onto_sha"] = "0" * 40
    assert cr.file_loss(repo["repo"], rec, "a.sh", repo["head"]).compare_failed


def test_both_sides_identical_change_not_lost(tmp_path):
    shared = "shared_new_line\n"
    repo = _build_repo(
        tmp_path,
        base={"a.sh": "alpha_line\n"},
        master={"a.sh": "alpha_line\n" + shared},
        branch={"a.sh": "alpha_line\n" + shared},
        resolved={"a.sh": "alpha_line\n" + shared},
    )
    rec = _record_for(tmp_path, repo, "identical", ["a.sh"])
    loss = cr.file_loss(repo["repo"], rec, "a.sh", repo["head"])
    assert loss.master_lost == [] and loss.branch_lost == [] and loss.moved == []


def test_whitespace_and_marker_lines_ignored():
    lost, moved = cr.lost_lines(
        "start_line\n", "start_line\n  foo   bar\n=======\n",
        "start_line\nfoo bar\n=======\n", {})
    assert lost == [] and moved == []


def test_marker_regex_near_miss():
    side = "start_line\n<<<<<<<abc_def\n<<<<<<< abc\n"
    lost, _ = cr.lost_lines("start_line\n", side, "start_line\n", {})
    assert lost == ["<<<<<<<abc_def"]


def test_line_moved_to_other_file_not_lost(tmp_path):
    repo = _build_repo(
        tmp_path,
        base={"a.sh": "alpha_line\n", "b.sh": "beta_line\n"},
        master={"a.sh": "alpha_line\nspecial_function_xyz\n"},
        branch={"b.sh": "beta_line\nbranch_extra_line\n"},
        resolved={"a.sh": "alpha_line\n", "b.sh": "beta_line\nspecial_function_xyz\nbranch_extra_line\n"},
    )
    rec = _record_for(tmp_path, repo, "moved", ["a.sh", "b.sh"])
    loss = cr.file_loss(repo["repo"], rec, "a.sh", repo["head"])
    assert loss.master_lost == []
    assert loss.moved == [("special_function_xyz", "b.sh")]


def test_moved_line_is_listed_in_the_details(tmp_path):
    repo = _build_repo(
        tmp_path,
        base={"a.sh": "alpha_line\n", "b.sh": "beta_line\n"},
        master={"a.sh": "alpha_line\nspecial_function_xyz\n"},
        branch={"c.sh": "branch_only_line\n"},
        resolved={"a.sh": "alpha_line\n", "b.sh": "beta_line\nspecial_function_xyz\n"},
    )
    rec = _record_for(tmp_path, repo, "movedlist", ["a.sh"])
    loss = cr.file_loss(repo["repo"], rec, "a.sh", repo["head"])
    out = cr.render_section([rec], [loss], CLEAN)
    assert "1 line moved to another file (listed below)." in out
    assert "- (moved to b.sh) special&#95;function&#95;xyz" in out


def test_master_line_in_two_files_dropped_from_one_is_lost(tmp_path):
    line = "$stmt->execute();"
    repo = _build_repo(
        tmp_path,
        base={"a.php": "alpha_line\n", "b.php": "beta_line\n"},
        master={"a.php": f"alpha_line\n{line}\n", "b.php": f"beta_line\n{line}\n"},
        branch={"c.sh": "branch_only_line\n"},
        resolved={"a.php": "alpha_line\n"},
    )
    rec = _record_for(tmp_path, repo, "twofiles", ["a.php"])
    loss = cr.file_loss(repo["repo"], rec, "a.php", repo["head"])
    assert loss.master_lost == [line]
    assert loss.moved == []
    assert "No unique code was lost." not in cr.render_section([rec], [loss], CLEAN)


def test_branch_line_in_two_files_dropped_from_one_is_lost(tmp_path):
    repo = _build_repo(
        tmp_path,
        base={"a.sh": "alpha_line\n", "b.sh": "beta_line\n"},
        master={"m.sh": "master_only_line\n"},
        branch={"a.sh": "alpha_line\nbranch_copy_line\n", "b.sh": "beta_line\nbranch_copy_line\n"},
        resolved={"b.sh": "beta_line\nbranch_copy_line\n"},
    )
    rec = _record_for(tmp_path, repo, "branchtwo", ["a.sh"])
    loss = cr.file_loss(repo["repo"], rec, "a.sh", repo["head"])
    assert loss.branch_lost == ["branch_copy_line"]
    assert loss.moved == []


def test_one_gained_copy_excuses_only_one_missing_line(tmp_path):
    repo = _build_repo(
        tmp_path,
        base={"a.sh": "alpha_line\n", "b.sh": "beta_line\n"},
        master={"a.sh": "alpha_line\nrepeated_line\nrepeated_line\n"},
        branch={"c.sh": "branch_only_line\n"},
        resolved={"a.sh": "alpha_line\n", "b.sh": "beta_line\nrepeated_line\n"},
    )
    rec = _record_for(tmp_path, repo, "onecopy", ["a.sh"])
    loss = cr.file_loss(repo["repo"], rec, "a.sh", repo["head"])
    assert loss.moved == [("repeated_line", "b.sh")]
    assert loss.master_lost == ["repeated_line"]


def test_line_other_file_held_at_base_is_not_a_move_target(tmp_path):
    shared = "declare_strict_types_line"
    repo = _build_repo(
        tmp_path,
        base={"a.sh": "alpha_line\n", "b.sh": f"{shared}\n"},
        master={"a.sh": f"alpha_line\n{shared}\n"},
        # The branch edit puts b.sh in the merge-base diff, which is what makes it a candidate.
        branch={"b.sh": f"{shared}\nbranch_extra_line\n"},
        resolved={"a.sh": "alpha_line\n", "b.sh": f"{shared}\nbranch_extra_line\n"},
    )
    rec = _record_for(tmp_path, repo, "heldatbase", ["a.sh"])
    loss = cr.file_loss(repo["repo"], rec, "a.sh", repo["head"])
    assert loss.master_lost == [shared]
    assert loss.moved == []


def test_copy_from_base_then_lost_is_reported():
    base = "return_zero\nreturn_zero\nreturn_zero\n"
    side = base + "return_zero\n"
    lost, moved = cr.lost_lines(base, side, base, {})
    assert lost == ["return_zero"]
    assert moved == []


# -------------------------------------------------------------------- wording


def _section(record, losses=None, current=None):
    return cr.render_section([record], losses if losses is not None else [], current)


def test_cleared_review_still_shows_section():
    out = _section(_rec(), current=CLEAN)
    assert cr.HEADING in out
    assert cr.CLEAN_WORDS in out
    assert "The review ran and found no problem." in out
    assert "No unique code was lost." in out


def test_review_not_run_shows_reason():
    out = _section(_rec(), current=None)
    assert cr.NOT_RUN_WORDS in out
    assert "verdict=ABSENT" in out


def test_purged_review_explains_absent():
    rec = _rec(review={"verdict": PROBLEM, "excerpt": "", "retries": 0})
    out = _section(rec, current=None)
    assert cr.PURGED_WORDS.format(recorded=PROBLEM) in out
    assert ("The review ran on an earlier run (CONFLICT-REVIEW=FOUND-PROBLEM), but a later rebase "
            "cleared its result file, so the merge check reads it as missing (verdict=ABSENT).") in out


def test_incomplete_review_counts_attempts():
    rec = _rec(review={"verdict": "", "excerpt": "", "retries": 1})
    assert cr.INCOMPLETE_WORDS.format(n=2) in _section(rec, current=None)


def test_found_problem_reason_from_review_excerpt():
    rec = _rec(review={"verdict": PROBLEM, "excerpt": "The merge dropped the retry loop.",
                       "retries": 0})
    out = _section(rec, current=PROBLEM)
    assert cr.PROBLEM_WORDS in out
    assert 'The review recorded: "The merge dropped the retry loop."' in out


def test_no_reason_recorded_says_so():
    assert f"Why: {cr.NO_REASON}" in _section(_rec(), current=None)
    assert "Why: No reason was recorded." in _section(_rec(), current=None)


def test_ladder_rung_reason_attributed():
    rec = _rec(rung="opus-retry", fix_summary="restored the dropped guard")
    out = _section(rec, current=None)
    assert 'escalation rung opus-retry changed the resolution: "restored the dropped guard"' in out
    assert cr.NO_REASON not in out


def test_first_pass_rung_is_not_attributed():
    out = _section(_rec(rung="first-pass"), current=None)
    assert "escalation rung" not in out


# ------------------------------------------------------------- size and safety


def _lost(n, side="master"):
    return [f"lost_line_number_{i}" for i in range(n)]


def test_per_file_truncation_shows_count():
    fl = cr.FileLoss(path="a.sh", master_lost=_lost(50))
    out = _section(_rec(), [fl])
    assert out.count("- (master) ") == 40
    assert "... and 10 more lines not shown (50 lost in this file)" in out


def test_total_truncation_shows_count():
    losses = [cr.FileLoss(path=f"f{i}.sh", master_lost=_lost(40)) for i in range(4)]
    out = _section(_rec(files=[fl.path for fl in losses]), losses)
    assert out.count("- (master) ") == 120
    assert "... and 40 more lines not shown (40 lost in this file)" in out


def test_hostile_sticky_marker_line_neutralized():
    hostile = f"{STICKY_MARKER} {cr.SECTION_END}"
    fl = cr.FileLoss(path="a.sh", master_lost=[hostile], branch_lost=[hostile])
    out = _section(_rec(), [fl])
    assert STICKY_MARKER not in out
    assert out.count(cr.SECTION_END) == 1
    assert out.count(cr.SECTION_BEGIN) == 1
    assert "<!--" not in out.replace(cr.SECTION_BEGIN, "").replace(cr.SECTION_END, "")


def test_hostile_merge_digest_line_neutralized():
    fl = cr.FileLoss(path="### Merge digest", master_lost=["### Merge digest"])
    rec = _rec(review={"verdict": PROBLEM, "excerpt": "**READY** ### Merge digest",
                       "retries": 0}, rung="opus-retry", fix_summary="**READY**")
    out = _section(rec, [fl], current=PROBLEM)
    assert MERGE_DIGEST_HEADING not in out
    assert "### Merge digest" not in out
    assert "**" not in out


def test_neutralize_drops_control_characters_and_clips():
    assert cr.neutralize("a\tb\nc\x00d\x1b[", 50) == "a b cd&#91;"
    assert cr.neutralize("x" * 10, 4) == "xxxx..."


# ------------------------------------------------------------------------ CLI


def test_cli_entry_prints_section_and_exits_zero(tmp_path, capsys):
    repo = _build_repo(tmp_path, base={"a.sh": "alpha_line\n"}, master={"a.sh": "master_line\n"},
                       branch={"a.sh": "branch_line\n"}, resolved={"a.sh": "branch_line\n"})
    branch = f"cli-entry-{uuid.uuid4().hex[:10]}"
    flag = conflict_flag_path(branch)
    report = cr.record_path(branch)
    try:
        record_conflict_resolution(branch, repo["head"])
        cr.write_record(
            repo=repo["repo"], branch=branch, resolving_sha=repo["head"],
            resolved_files=["a.sh"], target_ref=repo["onto"], review_verdict_path=None,
            retries=0, rung=None, fix_summary=None, branch_sha=repo["branch"])
        code = cr.main(["--branch", branch, "--repo", repo["repo"]])
    finally:
        for path in (flag, str(report)):
            if os.path.exists(path):
                os.unlink(path)
    assert code == 0
    out = capsys.readouterr().out
    assert cr.HEADING in out
    assert "- (master) master&#95;line" in out
