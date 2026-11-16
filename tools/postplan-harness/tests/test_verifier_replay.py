"""verifier_replay: archive inventory, eligibility, flip classification and CLI guards.
No git and no LLM: every test builds a synthetic out-root under tmp_path.

The last section replays a real verifier reply through the verdict parser to the
pre-existing label. Fixture: tests/fixtures/verifier-reply-captured.txt, model opus,
captured 2026-11-16, verifier_prompt from commit d39a6ded2. Never hand-edit it;
re-capture instead. The capture ran against a throwaway script at PATH below, which
was deleted afterwards. The replay needs only the prompt text and the reply, never
the script.
"""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from harness import verifier_replay as vr  # noqa: E402
from harness import review, schemas  # noqa: E402
from harness.adapters.llm import extract_json  # noqa: E402
from harness.state import Finding, HarnessError, PlanInfo  # noqa: E402
from tests.test_review_labels import _Gh, _Llm, _canned, _cls  # noqa: E402

SHA_A = "a" * 40
SHA_B = "b" * 40


def _row(score, source="pr-review", agent="A", path="x.php", line=1):
    return {"source": source, "agent": agent, "path": path, "line": line, "score": score,
            "body_head": "body"}


def _run_dir(root, name, pr, rows, sha=None):
    d = root / name
    d.mkdir(parents=True)
    findings = [{k: r[k] for k in ("source", "agent", "path", "line")} | {"body": "full body"}
                for r in rows]
    (d / "result.json").write_text(json.dumps({"pr_number": pr, "slug": "x", "findings": findings,
                                               "scored_findings": rows}))
    if sha:
        (d / f"ci-{sha}.json").write_text("{}")
    return d


def _root(tmp_path):
    root = tmp_path / "out"
    _run_dir(root, "live-x-20261001-120000-1", 101,
             [_row(85, line=1), _row(70, source="security-audit", agent="security", line=2)], SHA_A)
    _run_dir(root, "live-y-20261002-120000-2", 102, [_row(50, line=3)], SHA_B)
    (root / "state").mkdir()
    (root / "state" / "x.reviewcache.json").write_text(json.dumps({
        "run_id": "live-x-20261001-120000-1",
        "review": {"run_id": "live-x-20261001-120000-1",
                   "findings": [], "scored_findings": [_row(85, line=1)]}}))
    return root


def _never_fetchable(pr):
    return False


def test_inventory_counts_held_findings_and_dedups_sources(tmp_path, capsys):
    root = _root(tmp_path)
    assert vr.main(["inventory", "--out-root", str(root)], ref_exists=_never_fetchable) == 0
    out = capsys.readouterr().out
    assert "<!-- verifier-replay-inventory -->" in out
    assert "<!-- /verifier-replay-inventory -->" in out
    assert "runs scanned: 2 result.json + 1 reviewcache (dedup 1)" in out
    assert "code >= 80: 1;" in out
    assert "security >= 75: 0;" in out
    assert "runs with >= 1 held finding: 1" in out
    assert "scored findings: 3;" in out
    assert "eligible for replay (held finding + head sha + pr number): 1; head ref unfetchable: 1" in out


def test_eligible_requires_head_sha_and_pr_number(tmp_path):
    root = tmp_path / "out"
    _run_dir(root, "live-a-20261001-120000-1", 201, [_row(90)], sha=None)
    _run_dir(root, "live-b-20261001-120000-2", 0, [_row(90)], sha=SHA_A)
    _run_dir(root, "live-c-20261001-120000-3", 203, [_row(90)], sha=SHA_B)
    by_id = {r.run_id: r for r in vr.load_runs(str(root))}
    assert by_id["live-a-20261001-120000-1"].head_sha == ""
    assert not vr.eligible(by_id["live-a-20261001-120000-1"])
    assert not vr.eligible(by_id["live-b-20261001-120000-2"])
    assert vr.eligible(by_id["live-c-20261001-120000-3"])
    assert by_id["live-c-20261001-120000-3"].head_sha == SHA_B


def _rec(score, line, kept=True):
    return _row(score, line=line) | {"kept": kept}


def test_classify_flip_requires_every_held_finding_dropped():
    before = [_row(85, line=1), _row(90, line=2)]
    one_kept = vr.classify_flip(before, [_rec(93, 1), _rec(0, 2, kept=False)])
    assert one_kept["held_before"] == 2
    assert one_kept["kept_now"] == 1
    assert one_kept["run_flip"] is False
    both_dropped = vr.classify_flip(before, [_rec(0, 1, kept=False), _rec(0, 2, kept=False)])
    assert both_dropped["run_flip"] is True
    assert both_dropped["kept_now"] == 0
    reverse = vr.classify_flip([_row(50, line=3)], [_rec(87, 3)])
    assert reverse["newly_held"] == 1
    assert reverse["run_flip"] is False


class _RaisingLlm:
    out_dir = None

    def call_tooled(self, *a, **kw):
        raise AssertionError("dry run must not call a verifier")

    def call(self, *a, **kw):
        raise AssertionError("dry run must not call the LLM")


def test_replay_dry_run_makes_no_llm_calls(tmp_path, capsys):
    root = _root(tmp_path)
    rc = vr.main(["replay", "--limit", "2", "--dry-run", "--out-root", str(root),
                  "--out", str(tmp_path / "votes")],
                 ref_exists=_never_fetchable, llm_factory=_RaisingLlm)
    assert rc == 0
    out = capsys.readouterr().out
    assert "<!-- verifier-replay-result -->" in out
    assert "verifier calls: 0" in out
    assert f"pr 101 {SHA_A[:7]} held-before=1 fetchable=no" in out
    assert not (tmp_path / "votes").exists()


def test_unknown_flag_and_missing_limit_exit_2(tmp_path, capsys):
    root = _root(tmp_path)
    assert vr.main(["replay", "--bogus", "--out-root", str(root)]) == 2
    assert "usage:" in capsys.readouterr().err
    assert vr.main(["replay", "--out-root", str(root)]) == 2
    assert "usage:" in capsys.readouterr().err
    assert vr.main(["replay"]) == 2
    assert "usage:" in capsys.readouterr().err


# --- Replay of a live-captured verifier reply ---

FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       "fixtures", "verifier-reply-captured.txt")
PATH = "bin/lib/dir-cleanup.sh"

# New-side lines 6-17 of the target file above. Line 15 is the one added line; 6-14 and 16-17
# are unchanged context. The capture runs from the repo root so the verifier can open it.
DIFF = (
    f"diff --git a/{PATH} b/{PATH}\n"
    f"--- a/{PATH}\n"
    f"+++ b/{PATH}\n"
    "@@ -6,11 +6,12 @@ set -u\n"
    " list_dir() {\n"
    '     local dir="$1"\n'
    "     local n\n"
    "     n=$(ls $dir | wc -l)\n"
    '     echo "$n"\n'
    " }\n"
    " \n"
    " clean_dir() {\n"
    '     local name="$1"\n'
    '+    rm -rf "$workdir/$name"\n'
    '     echo "cleaned $name"\n'
    " }\n"
)


def _verifier_findings() -> list[Finding]:
    return [
        # (a) on the added line 15
        Finding(source="code-review", agent="A", path=PATH, line=15,
                body="changed-line defect: WORKDIR is unset by default, so `$workdir` is empty "
                     "and `rm -rf \"$workdir/$name\"` runs `rm -rf /tmp` when the second "
                     "argument is `tmp`."),
        # (b) on unchanged context line 9, which this diff does not touch
        Finding(source="code-review", agent="A", path=PATH, line=9,
                body="pre-existing defect: `ls $dir` is unquoted, so a first argument of "
                     "`my files` is split into two paths and list_dir prints a wrong count."),
        # (c) a claim the shown code cannot produce
        Finding(source="code-review", agent="A", path=PATH, line=10,
                body="false claim: `local n` declares n as an array, so `echo \"$n\"` prints "
                     "only the first element of the count."),
    ]


def _verifier_hunks() -> dict[str, str]:
    return review._diff_by_path(DIFF)


def prompt_text() -> str:
    return review.verifier_prompt(_verifier_findings(), _verifier_hunks())


def _raw() -> str:
    with open(FIXTURE, encoding="utf-8") as fh:
        return fh.read()


def _captured() -> list[dict]:
    data = schemas.unwrap_verdicts_envelope(extract_json(_raw()))
    schemas.validate_verdicts(data)
    return data


class _ReplayLlm(_Llm):
    """Hands every verifier seat the captured raw text, so extract_json runs on real output."""

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        if purpose not in review.VERIFIER_PURPOSES:
            return super().call_tooled(purpose, model, prompt, cwd=cwd, **kw)
        return _raw()


def _finding_dicts() -> list[dict]:
    # The reviewer hint on (b) is what a review agent says; the verifier must agree with it.
    return [{"path": f.path, "line": f.line, "body": f.body,
             "preexisting": f.line == 9} for f in _verifier_findings()]


def _posted() -> dict[int, Finding]:
    llm, gh = _ReplayLlm(_canned(a=_finding_dicts())), _Gh()
    cls = _cls()
    cls.filtered_diff = DIFF
    cls.files = [PATH]
    review.ReviewPhase(llm, gh, worktree="/tmp/wt").run({}, cls, PlanInfo())
    return {f.line: f for f in gh.findings.get("Code review", [])}


def _run() -> dict[int, str]:
    return {line: f.label for line, f in _posted().items()}


def test_fixture_present_and_not_skipped():
    assert _raw().strip().startswith("[")
    assert len(_captured()) == len(_verifier_findings())


def test_captured_verifier_reply_parses_through_contract():
    data = _captured()
    assert [v["n"] for v in data] == [1, 2, 3]
    assert [v["verdict"] for v in data] == ["real", "real", "not-real"]
    assert all(isinstance(v["preexisting"], bool) for v in data)
    assert all(v["reason"].strip() for v in data)
    assert [v["preexisting"] for v in data] == [False, True, False]


def test_preexisting_verdict_reaches_preexisting_label():
    """The captured preexisting: true entry sets Finding.scorer_preexisting, which classify reads."""
    posted = _posted()
    assert posted[9].scorer_preexisting is True
    assert posted[9].label == "pre-existing"
    assert posted[15].scorer_preexisting is False


def test_changed_line_verdict_stays_important():
    posted = _run()
    assert posted[15] == "important"
    assert 10 not in posted


def test_truncated_reply_fails_parse_not_skip():
    with pytest.raises((ValueError, HarnessError)):
        schemas.validate_verdicts(schemas.unwrap_verdicts_envelope(extract_json("[")))
