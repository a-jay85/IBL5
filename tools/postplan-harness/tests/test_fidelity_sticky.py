"""Phase 5 — the sticky verdict comment: terminal lines, composed shape, digest lines.

The composed body is parsed by two other programs (`bin/digest-dm-build` at merge time and
the /pr-ready skill's own sticky finder), so the assertions here are about byte-level shape,
not prose.
"""
import os
import re
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
    ("READY WITH NOTES", None, None, None, None, 0, "READY WITH NOTES"),
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
        plan_hash=kw.pop("plan_hash", ""),
        posted_at=kw.pop("posted_at", ""))


def _digest_labels(body: str) -> dict:
    """The label -> value map bin/digest-dm-build's `_digest_labels` awk produces.

    A port, not a mock: it mirrors the awk's block start, its two exit tokens, its bullet
    strip, its label match and its continuation fold. The whole point of the test below is
    that a line placed after the digest rows gets FOLDED into the last label, so a mock
    that skipped the fold would assert nothing.
    """
    inblk = False
    label, val, out = "", "", {}
    for line in body.splitlines():
        if not inblk:
            if line.rstrip() == "### Merge digest":
                inblk = True
            continue
        if re.match(r"^#+ ", line):
            break
        if re.match(r"^\s*(---+|\*\*\*+|___+)\s*$", line):
            break
        stripped = re.sub(r"^\s*[-*]\s+", "", line)
        m = re.match(r"^\*\*[^*]+:\*\*", stripped)
        if m:
            if label:
                out[label] = val.strip()
            # awk's substr(line, 3, RLENGTH - 5) drops the leading `**` and the trailing
            # `:**`, so the colon is not part of the key.
            label = m.group(0)[2:-3]
            val = stripped[m.end():]
        elif label and line.strip():
            val += " " + line
    if label:
        out[label] = val.strip()
    return out


def test_digest_block_ends_before_the_verdict_tail():
    """The HR after the digest rows is what keeps the tail out of the Discord DM.

    Without it `_digest_labels` folds the posted-at line, the terminal verdict line and
    the sticky marker into `**Machine-authored fixes:**`, and the DM ships all three.
    """
    body = _sticky(posted_at="2026-09-19 22:20:00 PDT",
                   terminal="NOT READY — the blocking findings listed above remain")
    labels = _digest_labels(body)
    assert list(labels) == ["What changed", "Why", "Watch", "Touches",
                            "Machine-authored fixes"]
    assert labels["Machine-authored fixes"] == "none"
    for leaked in ("Verdict posted", "NOT READY", "pr-ready-verdict",
                   "REVIEW-COVERAGE", "Reviewed tree", "details"):
        assert leaked not in labels["Machine-authored fixes"]


def test_digest_block_ends_before_the_tail_with_a_remediation_note():
    """Same contract on the branch that emits a remediation note below the digest."""
    body = _sticky(posted_at="2026-09-19 22:20:00 PDT",
                   fid={"remediation_sha": "abc123def456"})
    labels = _digest_labels(body)
    assert "Remediation: commit" not in labels["Machine-authored fixes"]


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


def test_tree_line_sits_in_audit_trail_after_rule():
    body = _sticky(fid={"verdict_2": "READY", "reviewed_tree_2": TREE})
    h = body.index(fidelity.MERGE_DIGEST_HEADING)
    rule = body.index("\n---\n")
    opened = body.index("<details><summary>Audit trail</summary>")
    closed = body.index("</details>")
    assert h < rule < opened
    assert body.index("**Reviewed tree:**") > body.index("---")
    for line in ("**Reviewed tree:**", "**Re-reviewed tree:**"):
        assert opened < body.index(line) < closed
    assert "Arming decision" not in body


def test_re_reviewed_tree_line_is_absent_without_a_second_verdict():
    assert "**Re-reviewed tree:**" not in _sticky()


def test_holds_are_listed_and_a_clean_run_says_so():
    armed = _sticky()
    assert "Auto-merge held:" not in armed
    assert "fourteen" not in armed
    held = _decision(fidelity_verdict="NOT READY")
    assert held.holds
    body = _sticky(decision=held)
    assert "Auto-merge held:" in body
    assert "- (12) plan-fidelity-verdict" in body
    for c in held.holds:
        assert f"- ({c.number}) {c.name} — {c.reason}" in body
    assert body.splitlines()[0].endswith("· auto-merge held")
    assert "HOLD — auto-merge not armed" not in body
    none_recorded = _sticky(decision=None)
    assert "Auto-merge held:" in none_recorded
    assert "- no arming decision recorded" in none_recorded


def test_armed_decision_says_arm():
    body = _sticky()
    assert body.splitlines()[0].endswith("· auto-merge armed")
    assert "ARM — auto-merge is requested right after this comment" not in body


def test_remediation_sha_annotates_the_last_digest_row():
    body = _sticky(fid={"remediation_sha": "abc1234"})
    assert "**Machine-authored fixes:** none (post-plan remediation: abc1234)" in body
    assert "Remediation: commit" not in body
    assert "addresses the verdict-1 NOT READY findings" not in body


def test_missing_verdict_names_the_error_kind():
    body = _sticky(fid={"verdict_1": None, "error_kind": "llm-tooled-empty"}, excerpt="")
    assert body.splitlines()[0].startswith("**missing**")
    assert "Plan-fidelity verdict:" not in body
    assert "(no verdict file: llm-tooled-empty)" in body


def test_header_names_the_round_the_findings_came_from():
    """With a graded remediation round, the header labels the re-review, not verdict 1.

    Verdict 1 stays NOT READY forever; only the re-review reflects the remediated tree.
    Labelling the excerpt with verdict 1 while quoting the re-review is the contradiction
    this line exists to prevent.
    """
    body = _sticky(fid={"verdict_1": "NOT READY", "remediation_sha": "abc1234",
                        "verdict_2": "READY WITH NOTES", "reviewed_tree_2": TREE,
                        "findings_round": 2,
                        "rounds": [{"remediation_sha": "dead123", "verdict": "NOT READY"},
                                   {"remediation_sha": "abc1234",
                                    "verdict": "READY WITH NOTES"}]})
    assert body.splitlines()[0].startswith("**READY WITH NOTES** (re-review, round 2)")
    assert "**NOT READY**" not in body.splitlines()[0]
    assert "Plan-fidelity verdict:" not in body
    assert "Remediation: commit" not in body


def test_header_falls_back_to_verdict_1_without_a_graded_round():
    """findings_round 0 — and a fid that predates the key — both read verdict 1."""
    for fid in ({"verdict_1": "NOT READY", "findings_round": 0}, {"verdict_1": "NOT READY"}):
        body = _sticky(fid=fid)
        assert body.splitlines()[0].startswith("**NOT READY**")
        assert "Plan-fidelity verdict:" not in body
        assert "(re-review, round" not in body.splitlines()[0]


def test_an_out_of_range_findings_round_falls_back_rather_than_raising():
    """A truncated `rounds` list must not IndexError the whole comment away."""
    body = _sticky(fid={"verdict_1": "NOT READY", "findings_round": 3, "rounds": []})
    assert body.splitlines()[0].startswith("**NOT READY**")
    assert "(re-review, round" not in body.splitlines()[0]
    assert "Plan-fidelity verdict:" not in body


def test_the_remediation_sentence_names_the_graded_commit():
    """A trailing ungraded round moves remediation_sha past the round that was graded.

    remediation_sha has to stay the last commit the harness authored — Phase 7 watches CI
    for it. The sentence pairs a commit with the re-review that read it, so it reads the
    sha out of the graded round instead.
    """
    body = _sticky(fid={"verdict_1": "NOT READY", "remediation_sha": "bbb2222",
                        "verdict_2": None, "findings_round": 1,
                        "rounds": [{"remediation_sha": "aaa1111", "verdict": "NOT READY"},
                                   {"remediation_sha": "bbb2222", "verdict": None}]})
    assert "Remediation: commit" not in body
    assert "commit aaa1111 closed" not in body
    assert "commit bbb2222 closed" not in body
    assert body.splitlines()[0].startswith("**NOT READY** (re-review, round 1)")
    assert "**Remediation rounds:**" in body
    assert "aaa1111 → NOT READY" in body
    assert "bbb2222 → INDETERMINATE" in body
    assert body.count("(post-plan remediation: bbb2222)") == 1


# --- compose_sticky target shape (deviation-only sticky) ----------------------

CLEAN_EXCERPT = "Passed: 1, 2, 3, 4, 5, 6.\nREVIEW-COVERAGE: CURRENT\n\n## FINDINGS\n\nREADY"
CI_PASS = "CI: local verification pass; GitHub checks are watched after this comment"


def test_clean_ready_renders_target_shape():
    body = _sticky(excerpt=CLEAN_EXCERPT, ci_line=CI_PASS, diff_id="d" * 40,
                   plan_hash="e" * 64, posted_at=TS)
    for absent in ("Plan-fidelity verdict:", "*Verdict posted",
                   "all fourteen conditions clear", "Arming decision", "REBASE=clean",
                   "CI: local verification", "Remediation: commit"):
        assert absent not in body, absent
    assert "Findings: none." in body
    order = [body.index("### Merge digest"),
             body.index("\n---\n"),
             body.index("<details><summary>Audit trail</summary>"),
             body.index("REVIEW-COVERAGE: CURRENT"),
             body.index("**Reviewed tree:**"),
             body.index("</details>"),
             body.rindex("\nREADY\n"),
             body.index(fidelity.STICKY_MARKER)]
    assert order == sorted(order) and len(set(order)) == len(order)


def test_committed_fixture_matches_writer_tail_shape():
    """bin/test-review-shared-skip runs skip-review.sh against this captured body; if the
    writer's tail or audit-trail anchor ever changes, this fails before the gate goes inert."""
    import json
    import pathlib
    root = pathlib.Path(__file__).resolve().parents[3]
    fx = json.loads((root / "bin/fixtures/review-shared/post-plan-sticky-pr2892.json").read_text())
    stickies = [c["body"] for c in fx["comments"] if fidelity.STICKY_MARKER in (c.get("body") or "")]
    assert len(stickies) == 1
    captured = stickies[0].replace("\r", "").rstrip("\n").split("\n")
    body = _sticky(excerpt=CLEAN_EXCERPT, ci_line=CI_PASS, diff_id="d" * 40,
                   plan_hash="e" * 64, posted_at=TS).rstrip("\n").split("\n")
    # Tail shape the skip-review.sh Step 8 anchor keys on: </details>, blank, READY, marker.
    assert captured[-4:] == body[-4:] == ["</details>", "", "READY", fidelity.STICKY_MARKER]
    opener = "<details><summary>Audit trail</summary>"
    assert captured.count(opener) == 1 and body.count(opener) == 1
    for lines in (captured, body):
        block = lines[lines.index(opener) + 1:len(lines) - 4]
        assert sum(ln.startswith("**Reviewed tree:** ") for ln in block) == 1
        assert not any(ln.startswith(("<details", "</details", "```")) for ln in block)
        # header, 5 labels, blank, then the --- rule that ends the Step 11 digest parse.
        assert lines[lines.index("### Merge digest") + 7] == "---"
    assert captured[0].startswith("**READY**") and not captured[0].startswith("**Reviewed tree:**")


def test_deviating_status_lines_print():
    rebase = "REBASE=conflict auto-resolved via --onto; TREE-EQUIVALENT; manifest=/tmp/m"
    ci_fail = "CI: local verification fail; GitHub checks are watched after this comment"
    body = _sticky(rebase_line=rebase, ci_line=ci_fail)
    h = body.index(fidelity.MERGE_DIGEST_HEADING)
    assert body.index(rebase) < h
    assert body.index(ci_fail) < h
    ci_skipped = ("CI: local verification skipped; GitHub checks are watched after "
                  "this comment")
    skipped = _sticky(ci_line=ci_skipped)
    assert skipped.index(ci_skipped) < skipped.index(fidelity.MERGE_DIGEST_HEADING)
    # The expected REBASE= default beside a deviating CI line is still suppressed.
    assert "REBASE=clean" not in skipped


def test_nonempty_findings_are_kept_verbatim():
    body = _sticky(excerpt="## FINDINGS\n\n- [blocking] x missing\n\nNOT READY",
                   fid={"verdict_1": "NOT READY"}, terminal="NOT READY")
    assert "## FINDINGS" in body
    assert "- [blocking] x missing" in body
    assert "Findings: none." not in body
    assert body.index("- [blocking] x missing") < body.index(fidelity.MERGE_DIGEST_HEADING)


def test_coverage_marker_is_lifted_not_duplicated():
    body = _sticky(excerpt="REVIEW-COVERAGE: STALE\nnarration line here\n\nREADY")
    assert body.count("REVIEW-COVERAGE: STALE") == 1
    opened = body.index("<details><summary>Audit trail</summary>")
    assert opened < body.index("REVIEW-COVERAGE: STALE") < body.index("</details>")
    assert body.index("narration line here") < body.index(fidelity.MERGE_DIGEST_HEADING)


def test_bare_verdict_word_is_dropped_from_the_excerpt():
    body = _sticky(excerpt=CLEAN_EXCERPT)
    lines = body.splitlines()
    assert lines.count("READY") == 1
    assert lines[lines.index(fidelity.STICKY_MARKER) - 1] == "READY"


def test_composed_sticky_round_trips_through_readers():
    body = _sticky(excerpt=CLEAN_EXCERPT, diff_id="d" * 40, plan_hash="e" * 64)
    assert fidelity.carry_forward_predicate(body, "d" * 40, "e" * 64) == ("READY", "")
    labels = _dm_labels(body)
    assert len(labels) == 5, labels
    for rec in labels:
        assert "REVIEW-COVERAGE" not in rec
        assert "Reviewed" not in rec


# Hand-built clean-READY verdict excerpts, modelled on the shape of the #2565 sticky.
# OLD is what the reviewer wrote before the terse contract; NEW is what it writes now.
OLD_EXCERPT = "\n".join([
    "1. Intent coverage: matches. Every plan phase has a matching change in the diff.",
    "2. Intent fidelity: matches. Each change does what its phase describes.",
    "3. Scope creep: matches. The diff touches only files the plan names.",
    "4. PR body vs. reality: matches. The body's hand-written claims agree with the diff.",
    "5. Verification Matrix realisation: matches. Every matrix row has a test in the diff.",
    "6. Conflict-resolution audit: matches. The rebase was clean, so the list is empty.",
    "",
    "Phase 4B structured code review ran on 2026-09-28 "
    "(https://github.com/a-jay85/IBL5/pull/2565#issuecomment-1). It reviewed head "
    "abc1234, and today's head adds no commits past it, so the review covers the "
    "whole diff.",
    "REVIEW-COVERAGE: CURRENT",
    "Phase 4B reviewed the pre-rebase diff. No path was conflict-resolved, so no "
    "line lacks structured review coverage.",
    "procedure-source: master pin loaded as expected.",
    "",
    "## FINDINGS",
    "",
    "READY",
])
NEW_EXCERPT = "Passed: 1, 2, 3, 4, 5, 6.\nREVIEW-COVERAGE: CURRENT\n\n## FINDINGS\n\nREADY"


def _master_fidelity(tmp_path):
    """The pre-PR composer from origin/master, or None when it cannot be loaded."""
    proc = subprocess.run(
        ["git", "show", "origin/master:tools/postplan-harness/harness/fidelity.py"],
        cwd=os.path.dirname(os.path.abspath(__file__)), capture_output=True, text=True)
    if proc.returncode != 0 or not proc.stdout:
        return None
    path = tmp_path / "fidelity_master.py"
    path.write_text(proc.stdout)
    import importlib.util
    # A dotted name gives the module the `harness` package, so its relative imports
    # (`from .state import HarnessError`) resolve against this checkout.
    spec = importlib.util.spec_from_file_location("harness._fidelity_master", path)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def test_clean_ready_byte_count_shrinks(tmp_path):
    master = _master_fidelity(tmp_path)
    if master is None:
        pytest.skip("origin/master fidelity.py unavailable")

    def compose(mod, excerpt):
        fid = {"verdict_1": "READY", "error_kind": None, "reviewed_tree": TREE,
               "remediation_sha": None, "verdict_2": None, "reviewed_tree_2": None}
        return mod.compose_sticky(
            "REBASE=clean (HEAD already contains origin/master)", CI_PASS, fid,
            _decision(),
            ["**What changed:** a thing", "**Why:** a reason", "**Watch:** a page",
             "**Touches:** a file", "**Machine-authored fixes:** none"],
            excerpt, "READY", diff_id="d" * 40, plan_hash="e" * 64, posted_at=TS)

    before = compose(master, OLD_EXCERPT)
    if "Plan-fidelity verdict:" not in before:
        pytest.skip("origin/master already carries the deviation-only composer")
    after = compose(fidelity, NEW_EXCERPT)
    print(f"STICKY-BYTES before={len(before.encode())} after={len(after.encode())}")
    assert len(after.encode()) < len(before.encode())
    # The composer alone must shrink the comment too, so reverting Phase 2 fails here
    # even though the terser reviewer excerpt would still shrink the total.
    assert len(compose(fidelity, OLD_EXCERPT).encode()) < len(before.encode())


# --- the DM parser round-trip -------------------------------------------------

def _details_shaped_body(terminal: str, diff_id: str = "d" * 40,
                         plan_hash: str = "e" * 64) -> str:
    """A hand-built sticky in the post-PR shape: audit trail after `---`."""
    return "\n".join([
        "**READY** · auto-merge armed", "",
        "Findings: none.", "",
        "### Merge digest",
        "**What changed:** x", "**Why:** y", "**Watch:** z",
        "**Touches:** w", "**Machine-authored fixes:** none",
        "", "---", "",
        "<details><summary>Audit trail</summary>", "",
        "REVIEW-COVERAGE: CURRENT",
        f"**Reviewed tree:** {'a' * 40}",
        f"**Reviewed diff:** {diff_id}",
        f"**Plan hash:** {plan_hash}",
        "", "</details>", "",
        terminal,
        "<!-- pr-ready-verdict -->", ""])


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


def test_dm_parser_stops_at_rule_before_audit_trail():
    labels = _dm_labels(_details_shaped_body("READY"))
    assert len(labels) == 5, labels
    for rec in labels:
        for tok in ("REVIEW-COVERAGE", "Reviewed tree", "Reviewed diff", "details", "READY"):
            assert tok not in rec, (tok, rec)


def test_dm_build_round_trip_excludes_audit_trail():
    body = _details_shaped_body("READY")
    proc = subprocess.run([os.path.join(ROOT, "bin", "digest-dm-build"),
                           "--pr-number", "1", "--title", "t", "--url", "u"],
                          input=body, capture_output=True, text=True)
    if proc.returncode != 0:
        pytest.skip(f"digest-dm-build unavailable: {proc.stderr.strip()[:120]}")
    for tok in ("REVIEW-COVERAGE", "**Reviewed tree:**", "<details>"):
        assert tok not in proc.stdout, tok


def test_carry_forward_reads_machine_lines_inside_details():
    body = _details_shaped_body("READY WITH NOTES")
    assert fidelity.carry_forward_predicate(body, "d" * 40, "e" * 64) == ("READY WITH NOTES", "")
    assert fidelity.carry_forward_predicate(body, "f" * 40, "e" * 64) == (None, "diff-changed")


@pytest.mark.parametrize("terminal,expected", [
    ("READY", "READY"),
    ("READY WITH NOTES", "READY WITH NOTES"),
    ("READY WITH NOTES \u2014 notes left for the merging reviewer; the compiled harness "
     "remediates only NOT READY", "READY WITH NOTES"),
    ("READY (re-review) \u2014 findings remediated in abc1234 and re-reviewed clean on tree "
     + "b" * 40, None),
    ("NOT READY \u2014 the blocking findings listed above remain; remediate and re-run "
     "/post-plan", None),
    ("NOT READY (re-review) \u2014 the re-review's blocking findings remain; remediate and "
     "re-run /post-plan", None),
    ("NOT READY \u2014 plan-fidelity review produced no verdict (timeout); re-run /post-plan",
     None),
])
def test_prior_verdict_classifies_every_terminal_string(terminal, expected):
    assert fidelity._sticky_prior_verdict(_details_shaped_body(terminal)) == expected


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


def test_digest_uses_review_shared_path(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch,
              {".claude/review-shared/scripts/digest.sh": FIVE})
    out = fidelity.digest_lines(str(tmp_path), "deadbeef", str(tmp_path / "v.md"),
                                str(tmp_path), True)
    assert out[0] == "**What changed:** real"


def test_digest_degrades_when_neither_path_resolves(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch, {})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), True))


def test_digest_degrades_on_the_wrong_line_count(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch, {".claude/review-shared/scripts/digest.sh": FOUR})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), True))


def test_digest_degrades_on_a_nonzero_exit(tmp_path, monkeypatch):
    _git_shim(tmp_path, monkeypatch, {".claude/review-shared/scripts/digest.sh":
                                      "#!/usr/bin/env bash\nexit 7\n"})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), True))


def test_no_verdict_means_no_script_runs_at_all(tmp_path, monkeypatch):
    """A stale /tmp verdict from an earlier run must never be digested into this comment."""
    _git_shim(tmp_path, monkeypatch, {".claude/review-shared/scripts/digest.sh": FIVE})
    assert _degraded(fidelity.digest_lines(str(tmp_path), "deadbeef",
                                           str(tmp_path / "v.md"), str(tmp_path), False))
    assert not os.path.exists(os.path.join(str(tmp_path), "fidelity-digest.sh"))


def test_digest_uses_the_scripts_own_degrade_lines_verbatim(tmp_path, monkeypatch):
    own = ("#!/usr/bin/env bash\n"
           "printf '%s unavailable — DIGEST section is empty\\n' "
           "'**What changed:**' '**Why:**' '**Watch:**' '**Touches:**' "
           "'**Machine-authored fixes:**'\n")
    _git_shim(tmp_path, monkeypatch, {".claude/review-shared/scripts/digest.sh": own})
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
    """Backlog line sits above ### Merge digest; the rounds line sits in the audit trail
    after `---`; marker is last; labels belong to the digest section only."""
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
    assert ("[a-jay85/IBL5-backlog#101](<https://github.com/a-jay85/IBL5-backlog/issues/101>), "
            "[a-jay85/IBL5-backlog#102](<https://github.com/a-jay85/IBL5-backlog/issues/102>)"
            ) in body
    rule = body.index("\n---\n")
    opened = body.index("<details><summary>Audit trail</summary>")
    closed = body.index("</details>")
    assert b < h < rule < opened < r < closed
    assert body.splitlines()[-2] == "READY"
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


def test_digest_drops_the_appended_reviewed_tree_line(tmp_path):
    """The REVIEWED_TREE record sits below ## DIGEST in every real verdict file.

    digest.sh folds it into the fifth label, so without the strip the merge DM ships
    `**Machine-authored fixes:** none REVIEWED_TREE=<40 hex>`.
    """
    tree = "9" * 40
    v = tmp_path / "v.md"
    v.write_text("READY\n\n## DIGEST\n**What changed:** x\n**Why:** y\n**Watch:** z\n"
                 "**Touches:** t\n**Machine-authored fixes:** none\n"
                 f"REVIEWED_TREE={tree}\n")
    out = fidelity.digest_lines(None, "deadbeef", str(v), str(tmp_path), True)
    assert len(out) == 5
    assert out[4] == "**Machine-authored fixes:** none"
    assert not any("REVIEWED_TREE" in ln for ln in out)


def test_diff_and_plan_hash_lines_follow_reviewed_tree_inside_audit_trail():
    diff_id = "e" * 40
    plan_hash = "c" * 64
    body = _sticky(diff_id=diff_id, plan_hash=plan_hash)
    assert f"**Reviewed diff:** {diff_id}" in body
    assert f"**Plan hash:** {plan_hash}" in body
    assert body.index("**Reviewed tree:**") < body.index("**Reviewed diff:**")
    assert body.index("**Reviewed diff:**") < body.index("**Plan hash:**")
    rule = body.index("\n---\n")
    assert body.index(fidelity.MERGE_DIGEST_HEADING) < rule < body.index("**Reviewed tree:**")
    assert body.index("**Plan hash:**") < body.index("</details>")
    assert body.index("</details>") < body.rindex("\nREADY\n") < body.index(fidelity.STICKY_MARKER)
    assert body.count(fidelity.MERGE_DIGEST_HEADING) == 1


def test_diff_and_plan_hash_omitted_write_no_line():
    body = _sticky()
    assert "**Reviewed diff:**" not in body
    assert "**Plan hash:**" not in body


def test_carried_forward_line_present_only_when_flagged():
    body_with = _sticky(fid={"carried_forward": True})
    body_without = _sticky()
    assert "**Carried forward:**" in body_with
    cf = body_with.index("**Carried forward:**")
    assert body_with.index("\n---\n") < body_with.index("<details>") < cf
    assert cf < body_with.index("</details>") < body_with.rindex("\nREADY\n")
    assert "**Carried forward:**" not in body_without
    assert body_with.rstrip().endswith(fidelity.STICKY_MARKER)
    assert body_without.rstrip().endswith(fidelity.STICKY_MARKER)


# --- posted_at / timestamp tests -----------------------------------------------

TS = "2026-09-19 21:51:18 PDT"


def _sticky_ts(**kw):
    """Like _sticky() but with posted_at=TS set."""
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
        posted_at=TS)


def test_posted_at_empty_produces_no_timestamp_anywhere():
    """With posted_at='', line 0 carries no timestamp separator."""
    body = _sticky()
    assert body.splitlines()[0] == "**READY** · auto-merge armed"
    assert TS not in body
    assert "LATEST VERDICT" not in body
    assert "posted" not in body
    assert "*Verdict posted" not in body


def test_banner_names_same_verdict_word_as_verdict_line_verdict1_path():
    """The banner is the only verdict header: line 0 carries the word."""
    body = _sticky_ts(fid={"verdict_1": "NOT READY"}, terminal="NOT READY — reason")
    lines = body.splitlines()
    assert lines[0] == f"**NOT READY** — {TS} · auto-merge armed"
    assert not any("Plan-fidelity verdict:" in l for l in lines)


def test_banner_names_same_verdict_word_as_verdict_line_rereview_path():
    """Re-review path: the banner uses the graded round's verdict and names the round."""
    body = _sticky_ts(
        fid={"verdict_1": "NOT READY", "findings_round": 2,
             "rounds": [{"remediation_sha": "dead123", "verdict": "NOT READY"},
                        {"remediation_sha": "abc1234", "verdict": "READY WITH NOTES"}]},
        terminal="READY WITH NOTES")
    lines = body.splitlines()
    assert lines[0] == (f"**READY WITH NOTES** (re-review, round 2) — {TS} "
                        "· auto-merge armed")
    assert not any("Plan-fidelity verdict:" in l for l in lines)


def test_posted_at_appears_in_banner_verdict_line_and_above_terminal():
    body = _sticky_ts()
    lines = body.splitlines()
    assert lines[0] == f"**READY** — {TS} · auto-merge armed"
    assert body.count(TS) == 1
    assert "*Verdict posted" not in body
    assert "LATEST VERDICT" not in body
    marker_idx = lines.index(fidelity.STICKY_MARKER)
    assert lines[marker_idx - 1] == "READY"


def test_last_non_empty_line_before_marker_is_terminal_with_posted_at():
    """The carry-forward contract: last non-empty line before marker stays the terminal."""
    body = _sticky_ts(terminal="READY")
    head = body.rsplit(fidelity.STICKY_MARKER, 1)[0]
    non_empty = [ln.strip() for ln in head.splitlines() if ln.strip()]
    assert non_empty[-1] == "READY"


def test_sticky_prior_verdict_still_reads_ready_when_posted_at_set():
    """Regression: _sticky_prior_verdict must not be fooled by the top banner or italic line."""
    body = _sticky_ts(terminal="READY")
    assert fidelity._sticky_prior_verdict(body) == "READY"


def test_sticky_prior_verdict_reads_ready_with_notes_when_posted_at_set():
    terminal = "READY WITH NOTES — all notes remediated"
    body = _sticky_ts(terminal=terminal)
    assert fidelity._sticky_prior_verdict(body) == "READY WITH NOTES"
    lines = body.splitlines()
    assert lines[lines.index(fidelity.STICKY_MARKER) - 1] == terminal


# --- REMEDIATION_ALLOWED/DENIED constants ---

def test_remediation_bash_in_allowed_tools():
    assert "Bash" in fidelity.REMEDIATION_ALLOWED_TOOLS


def test_remediation_denied_tools_contain_write_and_merge_verbs():
    denied = fidelity.REMEDIATION_DENIED_TOOLS
    assert "Bash(git push:*)" in denied
    assert "Bash(git commit:*)" in denied
    assert "Bash(gh pr merge:*)" in denied
    assert "Bash(gh pr review:*)" in denied
    assert "Bash(gh api:*)" in denied
    assert "Agent" in denied
