"""conflict_report in the sticky: section_for, compose_sticky wiring, real-PR replays."""
import os
import pathlib
import subprocess
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import runner
from harness import conflict_report as cr
from harness import fidelity
from harness.adapters.gitad import LiveGit
from harness.armable import (
    FLAG_CARRIED,
    classify_conflict_flag,
    conflict_flag_path,
    conflict_verdict_for,
    record_conflict_resolution,
)
from harness.fidelity import MERGE_DIGEST_HEADING, STICKY_MARKER
from sticky_shapes import pr3267_inputs, render_sticky
from test_conflict_fix_rung import (  # noqa: F401  (make_repo is a fixture)
    _COMPOSE,
    _OK_REPLY,
    _compose_repo,
    _fix_handlers,
    _writer,
    make_repo,
)
from test_conflict_report import PROBLEM, _build_repo, _rec
from test_gitad_stacked_rebase import squash_repo  # noqa: F401  (a fixture)

FIXTURES = pathlib.Path(__file__).parent / "fixtures" / "conflict_report"
FAILURE_BLOCK = f"{cr.SECTION_BEGIN}\n{cr.FAILURE_LINE}\n{cr.SECTION_END}"


def _fx(set_name, side, ext):
    return (FIXTURES / set_name / f"{side}.{ext}.txt").read_text()


def _render_with(inputs, section):
    """render_sticky, plus a conflict section."""
    fid = inputs["fid"]
    terminal = fidelity.terminal_line(
        fid.get("verdict_1"), fid.get("error_kind"), fid.get("remediation_sha"),
        fid.get("verdict_2"), fid.get("reviewed_tree_2"), fid.get("rounds_completed", 0))
    return fidelity.compose_sticky(
        inputs["rebase_line"], inputs["ci_line"], fid, inputs["decision"],
        inputs["digest"], fidelity.findings_excerpt(fid["verdict_path"], True), terminal,
        diff_id=fid.get("diff_id", ""), plan_hash=fid.get("plan_hash", ""),
        posted_at=inputs["posted_at"], conflict_section=section)


def _one_file_repo(tmp_path, path, base, master, branch, resolved):
    return _build_repo(tmp_path, {path: base}, {path: master}, {path: branch}, {path: resolved})


def _mark(tmp_path, repo, branch, files, verdict_path=None):
    """Condition-(14) marker plus a report record for the resolving commit."""
    record_conflict_resolution(branch, repo["head"], str(tmp_path))
    cr.write_record(
        repo=repo["repo"], branch=branch, resolving_sha=repo["head"], resolved_files=files,
        target_ref=repo["onto"], review_verdict_path=verdict_path, retries=0, rung=None,
        fix_summary=None, branch_sha=repo["branch"], tmp_dir=str(tmp_path))


def _small_repo(tmp_path):
    return _one_file_repo(tmp_path, "a.sh", "keep line one\n", "keep line one\nmaster extra line\n",
                          "keep line one\nbranch extra line\n", "keep line one\nbranch extra line\n")


# ------------------------------------------------------------------ no section / failures


def test_no_marker_no_section(tmp_path):
    assert cr.section_for("no-marker", ".", tmp_dir=str(tmp_path)) == ""
    inputs = pr3267_inputs(tmp_path)
    assert _render_with(inputs, "") == render_sticky(inputs)


def test_section_for_internal_error_returns_failure_line(tmp_path, monkeypatch):
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "boom", ["a.sh"])

    def explode(*args, **kwargs):
        raise RuntimeError("boom")

    monkeypatch.setattr(cr, "file_loss", explode)
    got = cr.section_for("boom", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert got == FAILURE_BLOCK


class _FakeGit:
    last_conflict_flag_state = ""

    def branch(self):
        return "any-branch"


def test_report_failure_keeps_verdict_and_exit(tmp_path, monkeypatch):
    def explode(*args, **kwargs):
        raise RuntimeError("boom")

    monkeypatch.setattr(cr, "section_for", explode)
    section = runner._conflict_report_section(_FakeGit(), ".", None)
    assert section == FAILURE_BLOCK
    inputs = pr3267_inputs(tmp_path)
    plain = render_sticky(inputs)
    failed = _render_with(inputs, section)
    assert fidelity._sticky_prior_verdict(failed) == fidelity._sticky_prior_verdict(plain)
    assert failed.count(STICKY_MARKER) == plain.count(STICKY_MARKER)


# ------------------------------------------------------------------- dropped / carried


def _commit_on_new_branch(repo, start, text, name="rewritten"):
    """A commit off `start` holding a.sh = `text`, so the resolving commit leaves its history."""
    d = repo["repo"]
    subprocess.run(["git", "-C", d, "checkout", "-q", "-b", name, start], check=True)
    pathlib.Path(d, "a.sh").write_text(text)
    subprocess.run(["git", "-C", d, "add", "-A"], check=True)
    subprocess.run(["git", "-C", d, "commit", "-q", "--allow-empty", "-m", name], check=True)
    return subprocess.run(["git", "-C", d, "rev-parse", "HEAD"], check=True,
                          capture_output=True, text=True).stdout.strip()


def _sticky_block(line):
    return f"{cr.SECTION_BEGIN}\n{cr.HEADING}\n{line}\n{cr.SECTION_END}"


def test_stale_resolution_shows_dropped_line(tmp_path):
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "stale", ["a.sh"])
    # A rebase rewrote the resolving commit and its "branch extra line" did not survive.
    head = _commit_on_new_branch(repo, repo["onto"], "keep line one\nmaster extra line\n")
    prior = "intro\n" + cr.render_section([_rec()], [], None) + "\ntail"
    got = cr.section_for("stale", repo["repo"], head=head, prior_body=prior,
                         tmp_dir=str(tmp_path))
    assert got == _sticky_block(cr.DROPPED_LINE)
    assert ("An earlier conflict resolution by Claude was dropped by a later rebase. "
            "None of it remains on this branch.") in got
    # Nothing recorded and nothing to carry: no section at all.
    assert cr.section_for("stale", repo["repo"], head=head, tmp_dir=str(tmp_path)) == ""


def test_rewritten_commit_whose_lines_survive_is_not_called_dropped(tmp_path):
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "survives", ["a.sh"])
    # The same lines under a new SHA: a clean rebase the marker was not re-bound for.
    head = _commit_on_new_branch(repo, repo["onto"], "keep line one\nbranch extra line\n")
    prior = "intro\n" + cr.render_section([_rec()], [], None) + "\ntail"
    got = cr.section_for("survives", repo["repo"], head=head, prior_body=prior,
                         tmp_dir=str(tmp_path))
    assert got == _sticky_block(cr.REWRITTEN_LINE)


def test_no_record_cannot_prove_dropped(tmp_path):
    repo = _small_repo(tmp_path)
    record_conflict_resolution("unproved", "9" * 40, str(tmp_path))
    prior = "intro\n" + cr.render_section([_rec()], [], None) + "\ntail"
    got = cr.section_for("unproved", repo["repo"], head=repo["head"], prior_body=prior,
                         tmp_dir=str(tmp_path))
    assert got == _sticky_block(cr.REWRITTEN_LINE)


def test_missing_marker_carries_prior_section_not_dropped(tmp_path):
    repo = _small_repo(tmp_path)
    prior = "intro\n" + cr.render_section([_rec()], [], None) + "\ntail"
    got = cr.section_for("gone", repo["repo"], head=repo["head"], prior_body=prior,
                         tmp_dir=str(tmp_path))
    assert got == cr._extract_section(prior)
    assert cr.DROPPED_LINE not in got


def test_carried_sticky_keeps_section_bytes(tmp_path):
    repo = _small_repo(tmp_path)
    record_conflict_resolution("carried", repo["head"], str(tmp_path))  # marker, no record
    section = cr.render_section(
        [_rec(files=["a.sh"])], [cr.FileLoss(path="a.sh", master_lost=["master extra line"])], None)
    prior = _render_with(pr3267_inputs(tmp_path), section)
    got = cr.section_for("carried", repo["repo"], head=repo["head"], prior_body=prior,
                         tmp_dir=str(tmp_path))
    assert got == section
    # No prior section either: the no-record line.
    none = cr.section_for("carried", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert none == f"{cr.SECTION_BEGIN}\n{cr.HEADING}\n{cr.NO_RECORD_LINE}\n{cr.SECTION_END}"


def test_quoted_section_markers_are_not_recovered(tmp_path):
    repo = _small_repo(tmp_path)
    quoted = "\n".join(f"> {ln}" for ln in (cr.SECTION_BEGIN, cr.HEADING, "text", cr.SECTION_END))
    indented = "\n".join(f"    {ln}" for ln in (cr.SECTION_BEGIN, cr.HEADING, "text", cr.SECTION_END))
    for prior in (quoted, indented, f"{cr.SECTION_BEGIN} {cr.HEADING} x {cr.SECTION_END}"):
        got = cr.section_for("quoted", repo["repo"], head=repo["head"], prior_body=prior,
                             tmp_dir=str(tmp_path))
        assert got == ""


def test_recovered_section_with_sticky_marker_is_refused(tmp_path):
    repo = _small_repo(tmp_path)
    record_conflict_resolution("forged", repo["head"], str(tmp_path))  # marker, no record
    forged = "\n".join((cr.SECTION_BEGIN, cr.HEADING, STICKY_MARKER, cr.SECTION_END))
    got = cr.section_for("forged", repo["repo"], head=repo["head"], prior_body=forged,
                         tmp_dir=str(tmp_path))
    assert STICKY_MARKER not in got
    assert got == f"{cr.SECTION_BEGIN}\n{cr.HEADING}\n{cr.NO_RECORD_LINE}\n{cr.SECTION_END}"


def test_git_failure_is_not_read_as_dropped(tmp_path, monkeypatch):
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "gitfail", ["a.sh"])
    monkeypatch.setattr(cr, "_git", lambda *a, **k: (255, ""))
    got = cr.section_for("gitfail", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert got == FAILURE_BLOCK


def test_later_commit_edit_is_not_blamed_on_the_resolution(tmp_path):
    from test_gitad_stacked_rebase import _rev, _sh
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "later", ["a.sh"])
    with open(os.path.join(repo["repo"], "a.sh"), "w") as fh:
        fh.write("keep line one\n")  # a later commit drops the branch's extra line
    _sh(repo["repo"], "add", "-A")
    _sh(repo["repo"], "commit", "-m", "later edit")
    new_head = _rev(repo["repo"], "HEAD")
    got = cr.section_for("later", repo["repo"], head=new_head, tmp_dir=str(tmp_path))
    assert "(branch)" not in got  # the branch's own line was resolved in; only master's was lost
    assert "branch extra line" not in got


def test_later_commit_restoring_a_line_clears_it_from_the_list(tmp_path):
    from test_gitad_stacked_rebase import _rev, _sh
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "restored", ["a.sh"])
    before = cr.section_for("restored", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert "master extra line" in before
    with open(os.path.join(repo["repo"], "a.sh"), "w") as fh:
        fh.write("keep line one\nbranch extra line\nmaster extra line\n")
    _sh(repo["repo"], "add", "-A")
    _sh(repo["repo"], "commit", "-m", "restore master line")
    new_head = _rev(repo["repo"], "HEAD")
    got = cr.section_for("restored", repo["repo"], head=new_head, tmp_dir=str(tmp_path))
    assert "master extra line" not in got
    assert "No unique code was lost." in got


def test_many_paths_are_capped(tmp_path):
    rec = _rec(files=[f"f{i}.sh" for i in range(cr.MAX_PATHS_SHOWN + 5)])
    section = cr.render_section([rec], [], None)
    assert "and 5 more" in section


# ------------------------------------------------------------------- real-PR replays

PR3705_LOST = [
    '    # On Linux, Node 24+ names its main thread "MainThread", and lsof prints that',
    '    # as "MainThrea". Other programs can use that thread name too, so that name',
    '    # only counts when /proc says the binary really is node.',
    '        | awk -v p="$wt_path" \'($1 == "node" || $1 ~ /^MainThre/) && ($NF == p || index($NF, p "/") == 1) { print $1, $2 }\' \\',
    '        | while read -r name pid; do',
    '            exe="$(readlink "/proc/$pid/exe" 2>/dev/null)"',
    '            if [ "$name" = "node" ] || [ "${exe##*/}" = "node" ]; then',
    '                echo "$pid"',
    '        done \\',
]


def test_pr3705_replay_names_replaced_master_lines(tmp_path):
    path = "bin/lib/wt-guards.sh"
    master = _fx("pr3705", "master", "sh")
    repo = _one_file_repo(tmp_path, path, _fx("pr3705", "base", "sh"), master,
                          _fx("pr3705", "branch", "sh"), _fx("pr3705", "resolved", "sh"))
    verdict = tmp_path / "review.ok"
    verdict.write_text(f"{PROBLEM}\nThe merge dropped master's node check.\n")
    _mark(tmp_path, repo, "pr3705", [path], verdict_path=verdict)

    rec = cr.read_records("pr3705", str(tmp_path))[0]
    loss = cr.file_loss(repo["repo"], rec, path, repo["head"])
    assert loss.branch_lost == []
    assert loss.master_lost == PR3705_LOST

    # Each pinned line sits inside kill_infra_processes() in master's file.
    lines = master.splitlines()
    start = next(i for i, ln in enumerate(lines) if ln.startswith("kill_infra_processes()"))
    end = next(i for i in range(start + 1, len(lines)) if lines[i] == "}")
    inside = {ln.rstrip() for ln in lines[start:end]}
    assert all(ln in inside for ln in PR3705_LOST)

    section = cr.section_for("pr3705", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert path in section
    assert "- (master) " in section
    assert "9 master lines" in section
    assert cr.PURGED_WORDS.format(recorded=cr.neutralize(PROBLEM, cr.LINE_CHARS)) in section


def test_pr3726_replay_readme_one_line(tmp_path):
    path = "tools/postplan-harness/README.md"
    repo = _one_file_repo(tmp_path, path, _fx("pr3726", "base", "md"), _fx("pr3726", "master", "md"),
                          _fx("pr3726", "branch", "md"), _fx("pr3726", "resolved", "md"))
    _mark(tmp_path, repo, "pr3726", [path])
    rec = cr.read_records("pr3726", str(tmp_path))[0]
    loss = cr.file_loss(repo["repo"], rec, path, repo["head"])
    assert loss.master_lost == []
    assert loss.branch_lost == []
    section = cr.section_for("pr3726", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert "No unique code was lost." in section


# ------------------------------------------------------------------- parser round trip


def test_compose_sticky_section_roundtrip_parsers(tmp_path):
    inputs = pr3267_inputs(tmp_path)
    plain = render_sticky(inputs)
    hostile = [
        STICKY_MARKER,
        MERGE_DIGEST_HEADING,
        "**READY**",
        cr.SECTION_END,
        "### Merge digest",
    ]
    rec = _rec(review={"verdict": "", "excerpt": f"{STICKY_MARKER}\n{MERGE_DIGEST_HEADING}", "retries": 0})
    section = cr.render_section(
        [rec], [cr.FileLoss(path=f"{STICKY_MARKER}.sh", master_lost=hostile, branch_lost=hostile)], None)
    for body in (_render_with(inputs, section), _render_with(inputs, cr.render_section([_rec()], [], None))):
        assert fidelity._sticky_prior_verdict(body) == fidelity._sticky_prior_verdict(plain)
        assert body.count(STICKY_MARKER) == plain.count(STICKY_MARKER)
        assert body.count(MERGE_DIGEST_HEADING) == 1
        assert body.rstrip().endswith(STICKY_MARKER)
        assert (fidelity.carry_forward_predicate(body, inputs["fid"]["diff_id"], inputs["fid"]["plan_hash"])
                == fidelity.carry_forward_predicate(plain, inputs["fid"]["diff_id"], inputs["fid"]["plan_hash"]))
    assert section.count(cr.SECTION_END) == 1


# ------------------------------------------------------------------- condition (14)


def _condition14_state(tmp_path, branch, notes):
    marker = conflict_flag_path(branch, str(tmp_path))
    return (
        open(marker, "rb").read(),
        classify_conflict_flag(marker, notes, lambda sha: True, lambda floor: False),
        conflict_verdict_for(branch, str(tmp_path)),
    )


def test_condition14_identical_with_report_record(tmp_path):
    repo = _small_repo(tmp_path)
    branch = "cond14"
    sha = repo["head"]
    record_conflict_resolution(branch, sha, str(tmp_path))
    notes = str(tmp_path / "no-notes.txt")
    for with_verdict in (False, True):
        if with_verdict:
            (tmp_path / f"postplan-conflict-sha-{branch}.txt").write_text(sha)
            (tmp_path / f"postplan-conflict-verdict-{branch}-{sha}.ok").write_text("CONFLICT-REVIEW=CLEAN\nreply\n")
        before = _condition14_state(tmp_path, branch, notes)
        cr.write_record(
            repo=repo["repo"], branch=branch, resolving_sha=sha, resolved_files=["a.sh"],
            target_ref=repo["onto"], review_verdict_path=None, retries=0, rung=None,
            fix_summary=None, branch_sha=repo["branch"], tmp_dir=str(tmp_path))
        after = _condition14_state(tmp_path, branch, notes)
        assert before == after
    assert before[1][0] == FLAG_CARRIED
    assert before[2] == "CONFLICT-REVIEW=CLEAN"


def test_stale_flag_state_reads_the_records_after_reconcile_deleted_marker(tmp_path):
    from harness.armable import FLAG_STALE
    repo = _small_repo(tmp_path)
    cr.write_record(
        repo=repo["repo"], branch="wiped", resolving_sha=repo["head"], resolved_files=["a.sh"],
        target_ref=repo["onto"], review_verdict_path=None, retries=0, rung=None,
        fix_summary=None, branch_sha=repo["branch"], tmp_dir=str(tmp_path))
    head = _commit_on_new_branch(repo, repo["onto"], "keep line one\nmaster extra line\n")
    prior = "intro\n" + cr.render_section([_rec()], [], None) + "\ntail"
    got = cr.section_for("wiped", repo["repo"], head=head, prior_body=prior,
                         tmp_dir=str(tmp_path), flag_state=FLAG_STALE)
    assert got == _sticky_block(cr.DROPPED_LINE)


def _feature_branch():
    return f"report-{uuid.uuid4().hex[:12]}"


def _clear_tmp(branch):
    for path in (conflict_flag_path(branch), cr.record_path(branch)):
        if os.path.exists(path):
            os.unlink(path)


def test_reconcile_stale_state_reaches_the_section_through_the_runner(tmp_path):
    repo = _small_repo(tmp_path)
    branch = _feature_branch()
    subprocess.run(["git", "-C", repo["repo"], "checkout", "-q", "-b", branch], check=True)
    record_conflict_resolution(branch, "9" * 40)
    try:
        git = LiveGit(repo["repo"])
        git._reconcile_conflict_flag(branch, branch, "master")
        assert git.last_conflict_flag_state == "stale"
        assert not os.path.exists(conflict_flag_path(branch))
        prior = "intro\n" + cr.render_section([_rec()], [], None) + "\ntail"
        assert runner._conflict_report_section(git, repo["repo"], prior) == _sticky_block(cr.REWRITTEN_LINE)
    finally:
        _clear_tmp(branch)


def test_three_clean_replays_keep_one_record(tmp_path, monkeypatch):
    """Through the real rebase_onto. The marker only grows, so a rebind that kept the old
    entries would double the list on each replay."""
    import harness.premerge as premerge
    monkeypatch.setattr(premerge, "load", lambda key: None)
    repo = _small_repo(tmp_path)
    d = repo["repo"]
    branch = _feature_branch()

    def sh(*args):
        return subprocess.run(["git", "-C", d, *args], check=True, capture_output=True,
                              text=True).stdout.strip()

    sh("checkout", "-q", "-b", branch)
    first = sh("rev-parse", "HEAD")
    record_conflict_resolution(branch, first)
    cr.write_record(
        repo=d, branch=branch, resolving_sha=first, resolved_files=["a.sh"],
        target_ref=repo["onto"], review_verdict_path=None, retries=0, rung=None,
        fix_summary=None, branch_sha=repo["branch"])
    try:
        git = LiveGit(d)
        for n in (1, 2, 3):
            sh("checkout", "-q", "master")
            pathlib.Path(d, f"m{n}.txt").write_text("m\n")
            sh("add", "-A")
            sh("commit", "-q", "-m", f"master {n}")
            sh("checkout", "-q", branch)
            git.rebase_onto("master")
            assert sh("rev-parse", "HEAD") != first
            records = cr.read_records(branch)
            assert len(records) == 1, f"records after replay {n}: {len(records)}"
            assert records[0]["resolving_sha"] == sh("rev-parse", "HEAD")
            assert records[0]["original_sha"] == first
    finally:
        _clear_tmp(branch)


def test_rebind_leaves_records_alone_when_the_replay_kept_the_sha(tmp_path):
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "noop", ["a.sh"])
    before = cr.read_records("noop", str(tmp_path))
    cr.rebind_records("noop", repo["head"], [repo["head"]], str(tmp_path))
    assert cr.read_records("noop", str(tmp_path)) == before
    assert "original_sha" not in before[0]


def test_later_deletion_is_not_blamed_after_a_replay(tmp_path):
    repo = _small_repo(tmp_path)
    _mark(tmp_path, repo, "later", ["a.sh"])
    # A later commit deletes the branch's line. The replay re-stamps the record onto it.
    head = _commit_on_new_branch(repo, repo["head"], "keep line one\n")
    record_conflict_resolution("later", head, str(tmp_path))
    cr.rebind_records("later", head, [repo["head"]], str(tmp_path))
    got = cr.section_for("later", repo["repo"], head=head, tmp_dir=str(tmp_path))
    assert "(master) master extra line" in got  # the resolution's own loss stays listed
    assert "branch extra line" not in got


def test_passing_rung_trail_reaches_the_record_and_not_the_blocked_ship_field(make_repo, tmp_path):
    fx = _compose_repo(make_repo)
    llm = _fix_handlers(fx, _writer(_COMPOSE, fx.full, reply=_OK_REPLY))
    git = LiveGit(fx.d, llm=llm, state_dir=str(tmp_path / "state"))
    git.rebase_onto()
    # runner.py prints this field as "the lost-work proof still failed".
    assert git.last_conflict_fix_summary == ""
    record = cr.read_records(git.branch())[-1]
    assert record["rung"] == "sonnet-fix"
    assert "sonnet-fix: passed" in record["fix_summary"]


def test_stacked_replay_rebinds_the_record_to_the_rewritten_head(squash_repo):
    d, _parent, master_sha, _key, branch = squash_repo
    git = LiveGit(d)
    old_head = git.head()
    record_conflict_resolution(branch, old_head)
    cr.write_record(
        repo=d, branch=branch, resolving_sha=old_head, resolved_files=["feature.txt"],
        target_ref=master_sha, review_verdict_path=None, retries=0, rung=None,
        fix_summary=None, branch_sha=old_head)
    try:
        assert git.autoresolve_stacked_rebase().resolved is True
        assert git.head() != old_head
        records = cr.read_records(branch)
        assert [r["resolving_sha"] for r in records] == [git.head()]
        assert records[0]["original_sha"] == old_head
        got = cr.section_for(branch, d, head=git.head())
        assert cr.NO_RECORD_LINE not in got
        assert "feature.txt" in got
    finally:
        _clear_tmp(branch)


# ------------------------------------------------------------------- renames / failed reads

_RENAMED = "".join(f"shared line number {n}\n" for n in range(12))


def _rename_repo(tmp_path, master_file, resolved):
    """Master renames bin/a to bin/b (holding `master_file`). The branch edits bin/a."""
    d = str(tmp_path / "rename")
    subprocess.run(["git", "init", "-q", "-b", "master", d], check=True)

    def sh(*args):
        return subprocess.run(["git", "-C", d, "-c", "user.email=t@t", "-c", "user.name=t", *args],
                              check=True, capture_output=True, text=True).stdout.strip()

    pathlib.Path(d, "bin").mkdir()
    pathlib.Path(d, "bin/a").write_text(_RENAMED)
    sh("add", "-A")
    sh("commit", "-m", "base")
    base = sh("rev-parse", "HEAD")
    sh("checkout", "-q", "-b", "side")
    pathlib.Path(d, "bin/a").write_text(_RENAMED + "branch edit line\n")
    sh("add", "-A")
    sh("commit", "-m", "branch")
    branch = sh("rev-parse", "HEAD")
    sh("checkout", "-q", "master")
    pathlib.Path(d, "bin/a").unlink()
    pathlib.Path(d, "bin/b").write_text(master_file)
    sh("add", "-A")
    sh("commit", "-m", "master")
    onto = sh("rev-parse", "HEAD")
    pathlib.Path(d, "bin/b").write_text(resolved)
    sh("add", "-A")
    sh("commit", "--allow-empty", "-m", "resolved")
    return d, {"base_sha": base, "onto_sha": onto, "branch_sha": branch}, sh("rev-parse", "HEAD")


def test_rename_on_master_keeps_the_branch_side_in_the_check(tmp_path):
    d, rec, head = _rename_repo(tmp_path, _RENAMED, _RENAMED)
    loss = cr.file_loss(d, rec, "bin/b", head)
    assert not loss.compare_failed
    assert loss.branch_lost == ["branch edit line"]


def test_rename_git_cannot_detect_marks_compare_failed(tmp_path):
    rewritten = "".join(f"totally different text {n}\n" for n in range(12))
    d, rec, head = _rename_repo(tmp_path, rewritten, rewritten)
    assert cr.file_loss(d, rec, "bin/b", head).compare_failed


def test_file_only_one_side_added_is_still_compared(tmp_path):
    repo = _build_repo(tmp_path, {"a.sh": "keep line one\n"}, {"a.sh": "keep line one\n"},
                       {"a.sh": "keep line one\n", "new.sh": "branch only line\n"},
                       {"new.sh": "resolved only line\n"})
    rec = {"base_sha": repo["base"], "onto_sha": repo["onto"], "branch_sha": repo["branch"]}
    loss = cr.file_loss(repo["repo"], rec, "new.sh", repo["head"])
    assert not loss.compare_failed
    assert loss.branch_lost == ["branch only line"]


def test_show_reads_absent_only_from_a_tree_without_the_path(tmp_path, monkeypatch):
    repo = _small_repo(tmp_path)
    assert cr._show(repo["repo"], repo["head"], "no-such-file") == ""
    assert cr._show(repo["repo"], "0" * 40, "a.sh") is None
    real = cr._git

    def failing_show(code):
        def fake(d, *args):
            return (code, "") if args[0] == "show" else real(d, *args)
        return fake

    for code in (cr._GIT_FAILED, 128):  # a timeout, then a read error on a path the tree holds
        monkeypatch.setattr(cr, "_git", failing_show(code))
        assert cr._show(repo["repo"], repo["head"], "a.sh") is None


def test_failed_read_of_another_file_marks_compare_failed(tmp_path, monkeypatch):
    repo = _build_repo(tmp_path, {"a.sh": "keep line one\n", "b.sh": "other file\n"},
                       {"b.sh": "other file changed\n"}, {"a.sh": "keep line one\nbranch line\n"},
                       {"a.sh": "keep line one\n"})
    rec = {"base_sha": repo["base"], "onto_sha": repo["onto"], "branch_sha": repo["branch"]}
    real = cr._show
    monkeypatch.setattr(cr, "_show", lambda d, sha, path: None if path == "b.sh" else real(d, sha, path))
    assert cr.file_loss(repo["repo"], rec, "a.sh", repo["head"]).compare_failed


def test_branch_lookup_failure_stays_inside_the_report_guard():
    class _BrokenGit:
        def branch(self):
            raise RuntimeError("git failed")

    assert runner._conflict_report_section(_BrokenGit(), ".", None) == FAILURE_BLOCK


def test_clean_replay_rebinds_record_to_new_sha(tmp_path):
    repo = _small_repo(tmp_path)
    old = "9" * 40
    record_conflict_resolution("replay", old, str(tmp_path))
    cr.write_record(
        repo=repo["repo"], branch="replay", resolving_sha=old, resolved_files=["a.sh"],
        target_ref=repo["onto"], review_verdict_path=None, retries=0, rung=None,
        fix_summary=None, branch_sha=repo["branch"], tmp_dir=str(tmp_path))
    record_conflict_resolution("replay", repo["head"], str(tmp_path))  # the replay's new HEAD
    cr.rebind_records("replay", repo["head"], [old], str(tmp_path))
    got = cr.section_for("replay", repo["repo"], head=repo["head"], tmp_dir=str(tmp_path))
    assert cr.NO_RECORD_LINE not in got
    assert "a.sh" in got
    assert "verdict=ABSENT" in got
