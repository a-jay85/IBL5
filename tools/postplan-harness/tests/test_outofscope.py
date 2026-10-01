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

    def issue_create(self, title, body, label):
        self.create_calls += 1
        if self.create_calls in self.fail_create_at:
            raise HarnessError("gh", "boom")
        self.bodies = getattr(self, "bodies", []) + [body]
        self.created.append(title)
        return super().issue_create(title, body, label)


def _creates(gh):
    return [a for a in gh.actions() if a.get("action") == "issue_create"]


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


def test_issue_create_failure_continues(tmp_path):
    gh = _TitlesGh(tmp_path, fail_create_at={1})
    nums = file_deferral_issues(gh, _hits(), SLUG, 77)
    assert len(nums) == 1
    assert len(_creates(gh)) == 1


def test_hit_cap_limits_filing(tmp_path):
    gh = _TitlesGh(tmp_path)
    logs: list[str] = []
    nums = file_deferral_issues(gh, _make_hits(7), SLUG, 77, log=logs.append)
    assert len(nums) == 5
    assert any("2 hits over cap" in m for m in logs)


def test_issue_titles_strict_raises_default_swallows(tmp_path, monkeypatch):
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
