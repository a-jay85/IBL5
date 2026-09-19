"""Phase 5 — the sticky verdict comment: terminal lines, composed shape, digest lines.

The composed body is parsed by two other programs (`bin/digest-dm-build` at merge time and
the /pr-ready skill's own sticky finder), so the assertions here are about byte-level shape,
not prose.
"""
import os
import stat
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.armable import ArmInputs, evaluate
from harness.state import Classification

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.dirname(os.path.abspath(__file__)))))
TREE = "c" * 40


# --- terminal_line ------------------------------------------------------------

@pytest.mark.parametrize("v1,err,sha,v2,tree2,rounds,expected", [
    (None, "llm-tooled-empty", None, None, None, 0,
     "NOT READY — plan-fidelity review produced no verdict (llm-tooled-empty); "
     "re-run /post-plan"),
    ("READY", None, None, None, None, 0, "READY"),
    ("READY WITH NOTES", None, None, None, None, 0,
     "READY WITH NOTES — notes left for the merging reviewer; the compiled harness "
     "remediates only NOT READY"),
    ("NOT READY", None, None, None, None, 0,
     "NOT READY — the blocking findings listed above remain; remediate and "
     "re-run /post-plan"),
    ("NOT READY", None, "abc1234", None, None, 0,
     "NOT READY — re-review produced no verdict; re-run /post-plan"),
    ("NOT READY", None, "abc1234", "READY", TREE, 0,
     f"READY (re-review) — findings remediated in abc1234 and re-reviewed clean on "
     f"tree {TREE}"),
    ("NOT READY", None, "abc1234", "NOT READY", TREE, 1,
     "NOT READY (re-review) — the re-review's blocking findings remain; remediate and "
     "re-run /post-plan"),
    ("NOT READY", None, "abc1234", "NOT READY", TREE, 3,
     "NOT READY (re-review) — 3 remediation rounds ran and "
     "the re-review's blocking findings remain; remediate and re-run /post-plan"),
])
def test_terminal_line_rows(v1, err, sha, v2, tree2, rounds, expected):
    assert fidelity.terminal_line(v1, err, sha, v2, tree2, rounds) == expected


@pytest.mark.parametrize("sha", [None, "abc1234"])
@pytest.mark.parametrize("v2", [None, "READY", "READY WITH NOTES", "NOT READY"])
@pytest.mark.parametrize("rounds", [0, 1, 3])
def test_indeterminate_verdict_never_reads_as_ready(sha, v2, rounds):
    """A missing verdict is not a passing one. No v1=None combination may say READY."""
    line = fidelity.terminal_line(None, "llm-tooled-cli", sha, v2, TREE, rounds)
    assert not line.startswith("READY")


def test_unrecorded_tree_is_named_not_blank():
    line = fidelity.terminal_line("NOT READY", None, "abc1234", "READY", None, 1)
    assert line.endswith("on tree unrecorded")


# --- findings_excerpt ---------------------------------------------------------

def test_excerpt_of_a_missing_or_empty_file_is_empty(tmp_path):
    assert fidelity.findings_excerpt(str(tmp_path / "nope.md"), True) == ""
    p = tmp_path / "empty.md"
    p.write_text("\n \n")
    assert fidelity.findings_excerpt(str(p), True) == ""


def test_excerpt_is_empty_when_this_run_produced_no_verdict(tmp_path):
    """The same gate digest_lines takes, for the same stale-/tmp-file reason.

    verdict_path() is stable per PR and never deleted, and every indeterminate branch of
    _run_fidelity still records it. Ungated, a degraded re-run quotes the PREVIOUS run's
    findings underneath a terminal line that says the verdict is missing.
    """
    p = tmp_path / "v.md"
    p.write_text("NOT READY\nstale finding from an earlier run\n")
    assert fidelity.findings_excerpt(str(p), False) == ""
    assert "stale finding" in fidelity.findings_excerpt(str(p), True)


def test_excerpt_cuts_at_digest_and_drops_the_tree_line(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("READY\nREVIEWED_TREE=" + TREE + "\nfinding one\n"
                 "## DIGEST\n**What changed:** secret\n")
    ex = fidelity.findings_excerpt(str(p), True)
    assert "finding one" in ex
    assert "REVIEWED_TREE" not in ex
    assert "secret" not in ex and "## DIGEST" not in ex


def test_excerpt_escapes_the_marker_and_the_heading(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("NOT READY\nthe PR body already carries <!-- pr-ready-verdict -->\n"
                 "### Merge digest\n**What changed:** quoted\n")
    ex = fidelity.findings_excerpt(str(p), True)
    assert fidelity.STICKY_MARKER not in ex
    assert "<!-- pr-ready-verdict (quoted) -->" in ex
    assert "\\### Merge digest" in ex


def test_excerpt_truncates(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("x" * 40000)
    ex = fidelity.findings_excerpt(str(p), True)
    assert ex.endswith("… (truncated)")
    assert len(ex) < 40000


# --- compose_sticky -----------------------------------------------------------

def _decision(**kw):
    base = dict(pr_body="## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed "
                        "— covered.\n",
                pr_title="chore: x", pr_labels=[], classification=Classification(),
                findings=[], unresolved_conformance=[], phase5_status="pass",
                plan_auto_merge_false=False, headless=True,
                dep_state_lookup=lambda n: "MERGED", fidelity_verdict="READY",
                unresolved_findings=[], conflict_resolved=False, current_tree=TREE)
    base.update(kw)
    return evaluate(ArmInputs(**base))


def _sticky(**kw):
    fid = {"verdict_1": "READY", "error_kind": None, "reviewed_tree": TREE,
           "remediation_sha": None, "verdict_2": None, "reviewed_tree_2": None}
    fid.update(kw.pop("fid", {}))
    return fidelity.compose_sticky(
        kw.pop("rebase_line", "REBASE=clean (HEAD already contains origin/master)"),
        kw.pop("ci_line", "CI: local verification pass; GitHub checks are watched "
                          "after this comment"),
        fid, kw.pop("decision", _decision()),
        kw.pop("digest", ["**What changed:** a thing", "**Why:** a reason",
                          "**Watch:** a page", "**Touches:** a file",
                          "**Machine-authored fixes:** none"]),
        kw.pop("excerpt", "finding one"),
        kw.pop("terminal", "READY"),
        diff_id=kw.pop("diff_id", ""),
        plan_hash=kw.pop("plan_hash", ""))


def test_sticky_marker_is_last_and_unique():
    body = _sticky()
    assert body.endswith(fidelity.STICKY_MARKER + "\n")
    assert body.count(fidelity.STICKY_MARKER) == 1
    assert body.count(fidelity.MERGE_DIGEST_HEADING) == 1


def test_a_hostile_excerpt_cannot_duplicate_the_marker_or_heading(tmp_path):
    """The escape is in findings_excerpt, so route the hostile text through it first."""
    p = tmp_path / "v.md"
    p.write_text("NOT READY\n### Merge digest\n**Watch:** injected\n"
                 + fidelity.STICKY_MARKER + "\n")
    body = _sticky(excerpt=fidelity.findings_excerpt(str(p), True))
    assert body.count(fidelity.STICKY_MARKER) == 1
    assert body.count("\n" + fidelity.MERGE_DIGEST_HEADING + "\n") == 1


def test_tree_and_arming_lines_index_before_the_heading():
    body = _sticky(fid={"verdict_2": "READY", "reviewed_tree_2": TREE})
    h = body.index(fidelity.MERGE_DIGEST_HEADING)
    assert body.index("**Reviewed tree:**") < h
    assert body.index("**Re-reviewed tree:**") < h
    assert body.index("Arming decision (Phase 6.5):") < h


def test_re_reviewed_tree_line_is_absent_without_a_second_verdict():
    assert "**Re-reviewed tree:**" not in _sticky()


def test_holds_are_listed_and_a_clean_run_says_so():
    assert "- all fourteen conditions clear" in _sticky()
    held = _decision(fidelity_verdict="NOT READY")
    body = _sticky(decision=held)
    assert "HOLD — auto-merge not armed" in body
    assert "- (12) plan-fidelity-verdict" in body


def test_armed_decision_says_arm():
    assert "ARM — auto-merge is requested right after this comment" in _sticky()


def test_remediation_sha_annotates_the_last_digest_row():
    body = _sticky(fid={"remediation_sha": "abc1234"})
    assert "**Machine-authored fixes:** none (post-plan remediation: abc1234)" in body
    assert "Remediation: commit abc1234 addresses the verdict-1 NOT READY findings" in body


def test_missing_verdict_names_the_error_kind():
    body = _sticky(fid={"verdict_1": None, "error_kind": "llm-tooled-empty"}, excerpt="")
    assert "Plan-fidelity verdict: missing" in body
    assert "(no verdict file: llm-tooled-empty)" in body


# --- the DM parser round-trip -------------------------------------------------

def _dm_labels(body):
    """Run bin/digest-dm-build's own _digest_labels over a composed body."""
    src = subprocess.run(["sed", "-n", "/^_digest_labels() {/,/^}/p",
                          os.path.join(ROOT, "bin", "digest-dm-build")],
                         capture_output=True, text=True, check=True).stdout
    assert src.count("_digest_labels() {") == 1, "extraction anchor must match one line"
    proc = subprocess.run(["bash", "-c", src + '\n_digest_labels'],
                          input=body, capture_output=True, text=True)
    return [l for l in proc.stdout.splitlines() if l.strip()]


def test_dm_parser_reads_exactly_five_records():
    recs = _dm_labels(_sticky(fid={"verdict_2": "READY", "reviewed_tree_2": TREE}))
    assert len(recs) == 5, recs
    assert recs[0].startswith("What changed\t")
    assert "a thing" in recs[0]
    assert any(r.startswith("Watch\t") and "a page" in r for r in recs)


def test_dm_build_round_trip():
    body = _sticky()
    proc = subprocess.run([os.path.join(ROOT, "bin", "digest-dm-build"),
                           "--pr-number", "1", "--title", "t", "--url", "u"],
                          input=body, capture_output=True, text=True)
    if proc.returncode != 0:
        pytest.skip(f"digest-dm-build unavailable: {proc.stderr.strip()[:120]}")
    assert "a thing" in proc.stdout and "a page" in proc.stdout
    assert "No /pr-ready digest" not in proc.stdout


# --- digest_lines -------------------------------------------------------------

FIVE = ("#!/usr/bin/env bash\n"
        "printf '%s\\n' '**What changed:** real' '**Why:** real' '**Watch:** real' "
        "'**Touches:** real' '**Machine-authored fixes:** real'\n")
FOUR = "#!/usr/bin/env bash\nprintf '%s\\n' '**What changed:** real' '**Why:** real'\n"


def _git_shim(tmp_path, monkeypatch, serve: dict):
    """A `git` that serves `git show <sha>:<path>` from `serve`, failing every other path."""
    bindir = tmp_path / "bin"
    bindir.mkdir(exist_ok=True)
    body = ["#!/usr/bin/env bash", 'if [ "$1" = "show" ]; then', '  case "$2" in']
    for rel, content in serve.items():
        body.append(f"    *{rel}) cat <<'EOSHIM'\n{content}\nEOSHIM\n    ;;")
    body += ['    *) exit 1 ;;', '  esac', '  exit 0', 'fi', 'exit 1']
    g = bindir / "git"
    g.write_text("\n".join(body) + "\n")
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")


def _degraded(lines):
    return all(fidelity.DIGEST_UNAVAILABLE in l for l in lines) and len(lines) == 5


def test_digest_falls_back_to_review_shared(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch,
              {".claude/review-shared/scripts/digest.sh": FIVE})
    out = fidelity.digest_lines(str(tmp_path), "deadbeef", str(tmp_path / "v.md"),
                                str(tmp_path), True)
    assert out == ["**What changed:** real", "**Why:** real", "**Watch:** real",
                   "**Touches:** real", "**Machine-authored fixes:** real"]


def test_digest_prefers_the_pr_ready_path(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch,
              {".claude/skills/pr-ready/scripts/digest.sh": FIVE,
               ".claude/review-shared/scripts/digest.sh":
                   "#!/usr/bin/env bash\nexit 1\n"})
    out = fidelity.digest_lines(str(tmp_path), "deadbeef", str(tmp_path / "v.md"),
                                str(tmp_path), True)
    assert out[0] == "**What changed:** real"


def test_digest_degrades_when_neither_path_resolves(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch, {})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), True))


def test_digest_degrades_on_the_wrong_line_count(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch, {".claude/skills/pr-ready/scripts/digest.sh": FOUR})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), True))


def test_digest_degrades_on_a_nonzero_exit(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch, {".claude/skills/pr-ready/scripts/digest.sh":
                                      "#!/usr/bin/env bash\nexit 7\n"})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), True))


def test_no_verdict_means_no_script_runs_at_all(tmp_path, monkeypatch):
    """A stale /tmp verdict from an earlier run must never be digested into this comment."""
    _git_shim(tmp_path, monkeypatch, {".claude/skills/pr-ready/scripts/digest.sh": FIVE})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), False))
    assert not os.path.exists(os.path.join(str(tmp_path), "fidelity-digest.sh"))


def test_digest_uses_the_scripts_own_degrade_lines_verbatim(tmp_path, monkeypatch):
    own = ("#!/usr/bin/env bash\n"
           "printf '%s unavailable — DIGEST section is empty\\n' "
           "'**What changed:**' '**Why:**' '**Watch:**' '**Touches:**' "
           "'**Machine-authored fixes:**'\n")
    _git_shim(tmp_path, monkeypatch, {".claude/skills/pr-ready/scripts/digest.sh": own})
    out = fidelity.digest_lines(str(tmp_path), "deadbeef", str(tmp_path / "v.md"),
                                str(tmp_path), True)
    assert out[0] == "**What changed:** unavailable — DIGEST section is empty"


# --- compose_sticky new lines (rows 16, 17) -----------------------------------

def test_rounds_line():
    """**Remediation rounds:** absent for 0 or 1 round, present for 2+."""
    assert "**Remediation rounds:**" not in _sticky()
    one = _sticky(fid={"rounds": [{"remediation_sha": "abc1234", "verdict": "NOT READY"}]})
    assert "**Remediation rounds:**" not in one
    two = _sticky(fid={
        "rounds": [
            {"remediation_sha": "abc1234ab", "verdict": "NOT READY"},
            {"remediation_sha": "def5678de", "verdict": "READY"},
        ]
    })
    assert "**Remediation rounds:**" in two
    assert "abc1234ab" in two
    assert "def5678de" in two


def test_sticky_ordering_and_marker():
    """New sticky lines sit above ### Merge digest; marker is last; labels belong
    to the digest section only (not used as round/backlog line prefixes)."""
    body = _sticky(fid={
        "rounds": [
            {"remediation_sha": "abc1234ab", "verdict": "NOT READY"},
            {"remediation_sha": "def5678de", "verdict": "READY"},
        ],
        "backlog_issue_numbers": [101, 102],
    })
    h = body.index(fidelity.MERGE_DIGEST_HEADING)
    r = body.index("**Remediation rounds:**")
    b = body.index("**Backlog issues filed:**")
    assert "a-jay85/IBL5-backlog#101, a-jay85/IBL5-backlog#102" in body
    assert r < h and b < h
    assert body.endswith(fidelity.STICKY_MARKER + "\n")
    for lbl in fidelity.LABELS:
        assert body.index(lbl) > h


def test_digest_reads_the_real_script_in_replay_mode(tmp_path):
    """worktree=None reads the checkout on disk — no git, no shim."""
    v = tmp_path / "v.md"
    v.write_text("READY\n\n## DIGEST\n**What changed:** x\n**Why:** y\n**Watch:** z\n"
                 "**Touches:** t\n**Machine-authored fixes:** none\n")
    out = fidelity.digest_lines(None, "deadbeef", str(v), str(tmp_path), True)
    assert len(out) == 5
    for i, lbl in enumerate(fidelity.LABELS):
        assert out[i].startswith(lbl)


def test_diff_and_plan_hash_lines_between_reviewed_tree_and_digest():
    diff_id = "e" * 40
    plan_hash = "c" * 64
    body = _sticky(diff_id=diff_id, plan_hash=plan_hash)
    assert f"**Reviewed diff:** {diff_id}" in body
    assert f"**Plan hash:** {plan_hash}" in body
    assert body.index("**Reviewed tree:**") < body.index("**Reviewed diff:**")
    assert body.index("**Reviewed diff:**") < body.index("**Plan hash:**")
    assert body.index("**Plan hash:**") < body.index(fidelity.MERGE_DIGEST_HEADING)
    assert body.count(fidelity.MERGE_DIGEST_HEADING) == 1


def test_diff_and_plan_hash_omitted_write_no_line():
    body = _sticky()
    assert "**Reviewed diff:**" not in body
    assert "**Plan hash:**" not in body


def test_carried_forward_line_present_only_when_flagged():
    body_with = _sticky(fid={"carried_forward": True})
    body_without = _sticky()
    assert "**Carried forward:**" in body_with
    assert body_with.index("**Carried forward:**") < body_with.index(fidelity.MERGE_DIGEST_HEADING)
    assert "**Carried forward:**" not in body_without
    assert body_with.rstrip().endswith(fidelity.STICKY_MARKER)
    assert body_without.rstrip().endswith(fidelity.STICKY_MARKER)
