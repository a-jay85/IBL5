"""Out-of-scope deferral sweep: extractor, filer, runner wiring, CLI, skill fallback."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import outofscope  # noqa: E402
from harness.outofscope import dedup_key, extract_deferral_hits  # noqa: E402

FIXTURE = Path(__file__).parent / "fixtures" / "outofscope" / "deferral_plan.md"
SLUG = "oos-fixture"


def _hits():
    return extract_deferral_hits(FIXTURE.read_text(), SLUG)


def test_hits_only_inside_out_of_scope():
    hits = _hits()
    assert len(hits) == 2
    assert all("Approach" not in h.text for h in hits)
    lines = FIXTURE.read_text().splitlines()
    for h in hits:
        assert lines[h.line_no - 1].lstrip("-* ").startswith(h.text.split(" ")[0])
    assert "legacy importer" in hits[0].text
    assert "cache warmer" in hits[1].text


def test_fenced_block_deferral_ignored():
    assert all("fenced thing" not in h.text for h in _hits())


def test_wrapped_bullet_is_one_hit():
    warmer = [h for h in _hits() if "cache warmer" in h.text]
    assert len(warmer) == 1
    assert "scheduler" in warmer[0].text and "separately" in warmer[0].text


def test_rejection_line_not_a_hit():
    assert all("resolved decision" not in h.text for h in _hits())


def test_cited_issue_line_not_a_hit():
    assert all("backlog#12" not in h.text for h in _hits())


def test_scope_boundary_plural_not_a_hit():
    assert all("separate services" not in h.text for h in _hits())


def test_absent_section_returns_empty():
    assert extract_deferral_hits("# Plan\n\n## Approach\n\nfile it separately\n", SLUG) == []
    assert extract_deferral_hits("# Plan\n\n## Out of Scope\n\n## Next\n\nx\n", SLUG) == []


def test_dedup_key_stable_across_whitespace_and_case():
    a = dedup_key(SLUG, "Move the `cache`  warmer to a **separate PR**")
    b = dedup_key(SLUG, "move the cache warmer\nto a separate pr")
    assert a == b
    assert a.startswith("oos-") and len(a) == len("oos-") + 10


def test_dedup_key_differs_by_slug():
    assert dedup_key("slug-a", "same text") != dedup_key("slug-b", "same text")


# --- Phase 2: filer ----------------------------------------------------------

import re  # noqa: E402

from harness.adapters.ghad import LiveGh, RecordingGh  # noqa: E402
from harness.outofscope import DeferralHit, file_deferral_issues  # noqa: E402
from harness.state import HarnessError  # noqa: E402


class _TitlesGh(RecordingGh):
    """RecordingGh whose title list echoes its own creates, like `gh issue list`."""

    def __init__(self, out_dir, seeded=(), read_error=None, fail_create_at=()):
        super().__init__(str(out_dir))
        self.seeded = list(seeded)
        self.read_error = read_error
        self.fail_create_at = set(fail_create_at)
        self.created: list[str] = []
        self.create_calls = 0

    def issue_titles(self, label, *, strict=False):
        if self.read_error is not None:
            raise self.read_error
        return self.seeded + self.created

    def followup_create(self, title, body, label):
        self.create_calls += 1
        if self.create_calls in self.fail_create_at:
            raise HarnessError("gh", "boom")
        self.bodies = getattr(self, "bodies", []) + [body]
        self.created.append(title)
        return super().followup_create(title, body, label)


def _creates(gh):
    return [a for a in gh.actions() if a.get("action") == "followup_create"]


def _make_hits(n):
    return [DeferralHit(text=f"item {i} goes to a separate plan",
                        line_no=10 + i, key=dedup_key(SLUG, f"item {i}")) for i in range(n)]


def test_files_one_issue_per_hit_with_provenance(tmp_path):
    gh = _TitlesGh(tmp_path)
    nums = file_deferral_issues(gh, _hits(), SLUG, 77)
    assert len(nums) == 2
    creates = _creates(gh)
    assert len(creates) == 2
    for c in creates:
        assert re.search(r"\[oos-[0-9a-f]{10}\]$", c["title"])
    for body, hit in zip(gh.bodies, _hits()):
        first = body.splitlines()[0]
        assert re.fullmatch(r"https://github\.com/a-jay85/IBL5/pull/[0-9]+", first)
        assert f"{SLUG}.md:{hit.line_no}" in body
        assert re.search(r"[a-zA-Z0-9_./-]+:[0-9]+", body)
        assert len(body) > 100


def test_rerun_files_no_duplicate(tmp_path):
    gh = _TitlesGh(tmp_path)
    file_deferral_issues(gh, _hits(), SLUG, 77)
    again = file_deferral_issues(gh, _hits(), SLUG, 77)
    assert again == []
    assert len(_creates(gh)) == 2


def test_existing_closed_issue_title_suppresses_filing(tmp_path):
    hits = _hits()
    gh = _TitlesGh(tmp_path, seeded=[f"Retitled by a human [{hits[0].key}]"])
    file_deferral_issues(gh, hits, SLUG, 77)
    assert len(_creates(gh)) == 1
    assert hits[1].key in _creates(gh)[0]["title"]


def test_dedup_read_failure_files_nothing(tmp_path):
    gh = _TitlesGh(tmp_path, read_error=HarnessError("gh", "rate limited"))
    logs: list[str] = []
    assert file_deferral_issues(gh, _hits(), SLUG, 77, log=logs.append) == []
    assert _creates(gh) == []
    assert any("dedup read failed" in m for m in logs)


def test_followup_create_failure_continues(tmp_path):
    gh = _TitlesGh(tmp_path, fail_create_at={1})
    nums = file_deferral_issues(gh, _hits(), SLUG, 77)
    assert len(nums) == 1
    assert len(_creates(gh)) == 1


def test_every_hit_files_none_dropped(tmp_path):
    gh = _TitlesGh(tmp_path)
    logs: list[str] = []
    nums = file_deferral_issues(gh, _make_hits(7), SLUG, 77, log=logs.append)
    assert len(nums) == 7
    assert not any("over cap" in m for m in logs)
    bodies = "\n".join(a["body"] for a in _creates(gh))
    for h in _make_hits(7):
        assert h.key in bodies


def test_issue_titles_strict_raises_default_swallows(tmp_path, monkeypatch):
    """LiveGh.issue_titles swallows by default, raises under strict, omits --label for None."""
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "br")

    def _boom(*args, **kw):
        raise HarnessError("gh", "down")

    monkeypatch.setattr(gh, "_gh", _boom)
    assert gh.issue_titles(None) == []
    with pytest.raises(HarnessError):
        gh.issue_titles(None, strict=True)

    seen: list[tuple] = []

    def _capture(*args, **kw):
        seen.append(args)
        return "[]"

    monkeypatch.setattr(gh, "_gh", _capture)
    gh.issue_titles(None)
    gh.issue_titles("maintenance")
    assert "--label" not in seen[0] and "--state" in seen[0] and "all" in seen[0]
    assert "--label" in seen[1]


# --- Phase 3: runner wiring --------------------------------------------------

import copy  # noqa: E402
import json  # noqa: E402
import tempfile  # noqa: E402

from test_backlog_closes import _BACKLOG_FIXTURE, _REPLAY_CANNED  # noqa: E402

_OOS_PLAN = """# Plan: oos replay

## Approach

Do the thing.

## Out of Scope

- Rewriting the legacy importer is a separate plan because it touches every season table.
"""

_NO_OOS_PLAN = """# Plan: no oos

## Approach

Do the thing; file it separately later.
"""


def _replay(plan_content, monkeypatch=None, counter=None):
    import runner
    from harness.adapters.llm import FixtureLlm
    from harness.state import UsageLedger
    fx = dict(_BACKLOG_FIXTURE, plan_content=plan_content)
    out = tempfile.mkdtemp(prefix="postplan-test-oos-")
    llm = FixtureLlm(UsageLedger(), copy.deepcopy(_REPLAY_CANNED))
    res = runner.run(fx, out, llm, mode="replay", headless=True)
    actions = []
    path = os.path.join(out, "actions.jsonl")
    if os.path.exists(path):
        with open(path) as fh:
            actions = [json.loads(ln) for ln in fh if ln.strip()]
    return res, actions


def test_locate_plan_populates_deferral_hits():
    from harness.planfile import locate_plan
    info = locate_plan("s", content_override=FIXTURE.read_text())
    assert len(info.deferral_hits) == 2
    text, line_no, key = info.deferral_hits[0]
    assert "legacy importer" in text and isinstance(line_no, int) and key.startswith("oos-")
    assert locate_plan("s", content_override=_NO_OOS_PLAN).deferral_hits == []


def test_replay_run_files_oos_issue():
    res, actions = _replay(_OOS_PLAN)
    creates = [a for a in actions if a["action"] == "followup_create"]
    assert len(creates) == 1
    assert creates[0]["label"] == "maintenance"
    assert "[oos-" in creates[0]["title"]


def test_replay_run_without_out_of_scope_files_nothing(monkeypatch):
    calls: list = []
    monkeypatch.setattr(RecordingGh, "issue_titles",
                        lambda self, label, *, strict=False: calls.append(label) or [])
    _res, actions = _replay(_NO_OOS_PLAN)
    assert [a for a in actions if a["action"] == "followup_create"] == []
    assert calls == []


def test_sweep_exception_does_not_change_terminal_state(monkeypatch):
    plain, _ = _replay(_OOS_PLAN)

    def _boom(*args, **kw):
        raise RuntimeError("kaboom")

    monkeypatch.setattr(outofscope, "file_deferral_issues", _boom)
    broken, _ = _replay(_OOS_PLAN)
    assert broken.terminal == plain.terminal
    assert any("oos-sweep: sweep failed" in line for line in broken.audit)


# --- Phase 4: CLI + skill fallback -------------------------------------------

REPO_ROOT = Path(__file__).resolve().parents[3]


def test_cli_rejects_non_integer_pr():
    for bad in ("abc", "0"):
        with pytest.raises(SystemExit) as ei:
            outofscope.main(["--plan", str(FIXTURE), "--slug", "s", "--pr", bad])
        assert ei.value.code == 2


def test_cli_missing_plan_exits_zero(tmp_path, monkeypatch, capsys):
    built: list = []
    monkeypatch.setattr(outofscope, "LiveGh", lambda *a, **k: built.append(a))
    rc = outofscope.main(["--plan", str(tmp_path / "nope.md"), "--slug", "s", "--pr", "5"])
    assert rc == 0
    assert "plan not found" in capsys.readouterr().out
    assert built == []


def _patch_gh(monkeypatch, tmp_path):
    gh = _TitlesGh(tmp_path / "gh")
    monkeypatch.setattr(outofscope, "LiveGh", lambda *a, **k: gh)
    return gh


def test_cli_files_through_gh_seam(tmp_path, monkeypatch):
    gh = _patch_gh(monkeypatch, tmp_path)
    args = ["--plan", str(FIXTURE), "--slug", SLUG, "--pr", "5"]
    assert outofscope.main(args) == 0
    assert len(_creates(gh)) == 2
    assert outofscope.main(args) == 0
    assert len(_creates(gh)) == 2


def test_cli_dry_run_files_nothing(tmp_path, monkeypatch, capsys):
    gh = _patch_gh(monkeypatch, tmp_path)
    rc = outofscope.main(["--plan", str(FIXTURE), "--slug", SLUG, "--pr", "5", "--dry-run"])
    assert rc == 0
    assert _creates(gh) == []
    assert capsys.readouterr().out.count("would-file") == 2


def test_cli_filing_exception_exits_zero(tmp_path, monkeypatch, capsys):
    _patch_gh(monkeypatch, tmp_path)

    def _boom(*a, **k):
        raise RuntimeError("kaboom")

    monkeypatch.setattr(outofscope, "file_deferral_issues", _boom)
    rc = outofscope.main(["--plan", str(FIXTURE), "--slug", SLUG, "--pr", "5"])
    assert rc == 0
    assert "sweep failed" in capsys.readouterr().out


def test_skill_phase_25_invokes_sweep():
    from harness.planfile import _section
    skill = (REPO_ROOT / ".claude" / "skills" / "post-plan" / "SKILL.md").read_text()
    section = _section(skill, r"Phase 2\.5")
    assert "python3 -m harness.outofscope" in section
    assert '--slug "$SLUG"' in section
    assert "(non-blocking)" in section
    assert "exit 1" not in section


# --- Exclusions added from the corpus review ----------------------------------

@pytest.mark.parametrize("item", [
    "Each exclusion below is a decision, not a deferral. None becomes a follow-up ticket.",
    "Gating the check on the changes job is cleaner. Its own PR if the extractor ever becomes fragile.",
    "Widening it would loosen a gate, which calls for its own plan.",
    "Moving the runner to Linux would be its own plan.",
    "Fixing the leak (backlog#1103) is a separate PR.",
    "That work is a separate follow-up, owned by a peer session as a separate PR.",
    "The leaders instrument is a separate plan: `jsb-j13-2-leaders`.",
    "The leaders instrument (`jsb-j13-2-leaders`) — separate PR.",
    "If a tagged spec fails in a phase, that is its own PR.",
    "The stacked change lands as a separate PR that merges first.",
])
def test_corpus_review_exclusions_are_not_hits(item):
    plan = f"# Plan\n\n## Out of Scope\n\n- {item}\n"
    assert extract_deferral_hits(plan, SLUG) == []


def test_plain_deferral_still_hits_after_exclusions():
    plan = "# Plan\n\n## Out of Scope\n\n- Retire the old CSV endpoint in a follow-up PR.\n"
    assert len(extract_deferral_hits(plan, SLUG)) == 1
