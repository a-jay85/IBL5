"""verifier_replay: archive inventory, eligibility, flip classification and CLI guards.
No git and no LLM: every test builds a synthetic out-root under tmp_path."""
import json
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from harness import verifier_replay as vr  # noqa: E402

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
