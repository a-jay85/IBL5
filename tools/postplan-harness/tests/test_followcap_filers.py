"""The follow-up cap as the three filers see it: bin/backlog new, fidelity notes, OOS sweep."""
from __future__ import annotations

import dataclasses
import json
import os
import subprocess
from pathlib import Path

import pytest

from harness import fidelity
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.followcap import ROLLUP_MARKER, FollowcapError, checklist_titles, issues_for_pr
from harness.outofscope import DeferralHit, file_deferral_issues
from harness.state import HarnessError

REPO_ROOT = Path(__file__).resolve().parents[3]
FIXTURE = Path(__file__).resolve().parent / "fixtures" / "fake_backlog_gh.py"
PR = 900
URL = f"https://github.com/a-jay85/IBL5/pull/{PR}"
SLUG = "cap-demo"


def _hits(n: int, start: int = 0) -> list[DeferralHit]:
    return [
        DeferralHit(text=f"item {i} goes to a separate plan", line_no=10 + i, key=f"oos-{i:010x}")
        for i in range(start, start + n)
    ]


def _notes(n: int) -> list[dict]:
    return [
        {"title": f"note {k}", "detail": f"unique-token-{k} detail text", "kind": "followup"}
        for k in range(1, n + 1)
    ]


def _pr_issues(gh: RecordingGh):
    return issues_for_pr(gh._followup_store.list_all(), URL)


def _rollup(issues):
    found = [i for i in issues if ROLLUP_MARKER in i.body]
    assert len(found) == 1
    return found[0]


def test_outofscope_eight_hits_nothing_dropped(tmp_path):
    gh = RecordingGh(str(tmp_path))
    logs: list[str] = []
    hits = _hits(8)
    file_deferral_issues(gh, hits, SLUG, PR, log=logs.append)
    issues = _pr_issues(gh)
    assert len(issues) == 3
    rollup = _rollup(issues)
    for h in hits[2:]:
        assert f"[{h.key}]" in rollup.body
    assert not any("over cap" in m for m in logs)


@pytest.mark.parametrize("n", [2, 3, 4, 8])
def test_fidelity_counts_two_three_four_eight(tmp_path, n):
    gh = RecordingGh(str(tmp_path))
    fidelity.file_note_issues(gh, _notes(n), PR)
    issues = _pr_issues(gh)
    assert len(issues) == min(n, 3)
    if n > 3:
        rollup = _rollup(issues)
        for k in range(3, n + 1):
            assert f"unique-token-{k}" in rollup.body


def test_retry_with_fresh_adapter_files_nothing_new(tmp_path):
    run1 = RecordingGh(str(tmp_path / "run1"))
    fidelity.file_note_issues(run1, _notes(5), PR)
    seeded = [dataclasses.asdict(i) for i in run1._followup_store.list_all()]
    assert len(seeded) == 3

    run2 = RecordingGh(str(tmp_path / "run2"), fixture={"backlog_issues": seeded})
    fidelity.file_note_issues(run2, _notes(5), PR)
    acts = [a for a in run2.actions() if a["action"] == "followup_create"]
    assert len(acts) == 5
    assert {a["kind"] for a in acts} == {"exists"}
    assert len(run2._followup_store.list_all()) == 3


class _UnreadableStore:
    def list_all(self):
        raise FollowcapError("read failed")


def test_followup_error_surfaces_as_harness_error(tmp_path):
    gh = RecordingGh(str(tmp_path))
    with pytest.raises(HarnessError):
        gh.followup_create("t", "plain prose, no PR URL\n\nmore", "maintenance")

    broken = RecordingGh(str(tmp_path / "broken"))
    broken._followup_store = _UnreadableStore()
    logs: list[str] = []
    assert file_deferral_issues(broken, _hits(2), SLUG, PR, log=logs.append) == []
    assert any("followup_create failed" in m for m in logs)


def _fake_gh_on_path(tmp_path: Path, monkeypatch) -> Path:
    bindir = tmp_path / "bin"
    bindir.mkdir()
    store = tmp_path / "store.json"
    wrapper = bindir / "gh"
    wrapper.write_text(f'#!/bin/sh\nexec python3 "{FIXTURE}" --store "{store}" "$@"\n')
    wrapper.chmod(0o755)
    monkeypatch.setenv("PATH", f"{bindir}{os.pathsep}{os.environ['PATH']}")
    monkeypatch.delenv("BACKLOG_GH", raising=False)
    return store


def _backlog_new(title: str, k: int) -> None:
    body = (
        f"{URL}\n\nbin/backlog:144 failure scenario for agent finding {k}, padded so the body "
        f"clears the one hundred character floor. unique-token-agent-{k}"
    )
    proc = subprocess.run(
        [str(REPO_ROOT / "bin" / "backlog"), "new", "maintenance", title, body],
        capture_output=True, text=True, timeout=60,
    )
    assert proc.returncode == 0, proc.stderr


def test_mixed_filers_share_one_cap(tmp_path, monkeypatch):
    store = _fake_gh_on_path(tmp_path, monkeypatch)
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "b")
    notes = _notes(2)
    hits = _hits(2)

    def run_all() -> None:
        _backlog_new("agent 1", 1)
        _backlog_new("agent 2", 2)
        fidelity.file_note_issues(gh, notes, PR)
        file_deferral_issues(gh, hits, SLUG, PR)

    def snapshot():
        rows = json.loads(store.read_text())["issues"]
        return [r for r in rows if r["body"].split("\n", 1)[0].strip() == URL]

    run_all()
    issues = snapshot()
    assert len(issues) == 3
    assert [i["title"] for i in issues[:2]] == ["agent 1", "agent 2"]
    rollup = issues[2]
    assert ROLLUP_MARKER in rollup["body"]
    titles = checklist_titles(rollup["body"])
    assert titles[:2] == [n["title"] for n in notes]
    assert [f"[{h.key}]" in t for h, t in zip(hits, titles[2:])] == [True, True]
    assert len(titles) == 4

    before = store.read_text()
    run_all()
    assert len(snapshot()) == 3
    assert checklist_titles(snapshot()[2]["body"]) == titles
    assert json.loads(store.read_text()) == json.loads(before)
