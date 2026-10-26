from __future__ import annotations

import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import plan_eval as pe

REPO_ROOT = Path(__file__).resolve().parents[3]
NOW = 1_790_000_000
DAY = 86400
SHA_A = "a" * 40
SHA_B = "b" * 40


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _commit(repo, msg, ts, files):
    for name, text in files.items():
        p = repo / name
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text)
    env = {**os.environ, "GIT_COMMITTER_DATE": f"{ts} +0000", "GIT_AUTHOR_DATE": f"{ts} +0000"}
    subprocess.run(["git", "-C", str(repo), "add", "-A"], check=True, env=env)
    subprocess.run(["git", "-C", str(repo), "-c", "user.email=t@t", "-c", "user.name=t",
                    "-c", "commit.gpgsign=false", "commit", "-q", "--no-verify", "-m", msg],
                   check=True, env=env)
    return subprocess.run(["git", "-C", str(repo), "rev-parse", "HEAD"], check=True,
                          capture_output=True, text=True).stdout.strip()


def _mk_repo(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    base = _commit(repo, "chore: base (#1)", NOW - 100 * DAY, {
        "a.txt": "a\n", "b.txt": "b\n",
        ".claude/skills/plan/SKILL.md": "skill v1\n",
        "bin/check-plan": "#!/bin/sh\nexit 0\n",
        "unrelated.txt": "u\n",
    })
    merge = _commit(repo, "feat: thing (#10)", NOW - 60 * DAY,
                    {"a.txt": "a2\n", "b.txt": "b2\n"})
    return {"root": repo, "base": base, "merge": merge}


def _mk_case(**overrides):
    case = {"id": "c1", "pr": 10, "base_sha": SHA_A, "merge_sha": SHA_B,
            "tier": "plan-architect", "core": True, "prompt": "prompts/c1.txt",
            "expected_files": ["a.txt", "b.txt"]}
    case.update(overrides)
    return case


def _write_prompts(corpus_dir, cases, text="Do the thing please.\n"):
    for c in cases:
        p = corpus_dir / c["prompt"]
        p.parent.mkdir(parents=True, exist_ok=True)
        if not p.exists():
            p.write_text(text)


def _corpus4(tmp_path, **case_overrides):
    tiers = ["plan-architect", "plan-architect", "plan-architect-sonnet",
             "plan-architect-xhigh"]
    cases = [_mk_case(id=f"c{i}", prompt=f"prompts/c{i}.txt", tier=t, **case_overrides)
             for i, t in enumerate(tiers)]
    _write_prompts(tmp_path, cases)
    return {"schema_version": 1, "window_days": 14, "cases": cases}


# ---------------------------------------------------------------------------
# Phase 1: schema, follow-ups, surface hash, CLI
# ---------------------------------------------------------------------------

def test_validate_schema_accepts_minimal_corpus(tmp_path):
    assert pe.validate_schema(_corpus4(tmp_path), tmp_path) == []


def test_validate_schema_accepts_empty_placeholder(tmp_path):
    corpus = {"schema_version": 1, "window_days": 14, "cases": []}
    assert pe.validate_schema(corpus, tmp_path) == []


def test_validate_schema_rejects_unknown_key(tmp_path):
    corpus = _corpus4(tmp_path)
    corpus["cases"][0]["notes"] = "x"
    errors = pe.validate_schema(corpus, tmp_path)
    assert any("bad keys" in e and "notes" in e for e in errors)


def test_validate_schema_rejects_bad_tier(tmp_path):
    corpus = _corpus4(tmp_path)
    corpus["cases"][0]["tier"] = "opus"
    errors = pe.validate_schema(corpus, tmp_path)
    assert any("tier: 'opus'" in e for e in errors)


def test_validate_schema_expected_files_bounds(tmp_path):
    for n, ok in ((1, False), (2, True), (12, True), (13, False)):
        corpus = _corpus4(tmp_path)
        corpus["cases"][0]["expected_files"] = [f"f{i}.txt" for i in range(n)]
        errors = pe.validate_schema(corpus, tmp_path)
        assert (errors == []) is ok, (n, errors)


def test_validate_schema_rejects_prompt_naming_expected_path(tmp_path):
    corpus = _corpus4(tmp_path)
    corpus["cases"][0]["expected_files"] = ["bin/plan-now", "b.txt"]
    (tmp_path / corpus["cases"][0]["prompt"]).write_text("Fix bin/plan-now resume.\n")
    errors = pe.validate_schema(corpus, tmp_path)
    assert any("prompt names expected path bin/plan-now" in e for e in errors)


def test_validate_schema_core_must_cover_tiers(tmp_path):
    corpus = _corpus4(tmp_path)
    for c in corpus["cases"]:
        c["tier"] = "plan-architect"
    errors = pe.validate_schema(corpus, tmp_path)
    assert any("cover every tier" in e for e in errors)
    corpus = _corpus4(tmp_path)
    corpus["cases"][0]["core"] = False
    errors = pe.validate_schema(corpus, tmp_path)
    assert any("exactly 4 core" in e for e in errors)


def test_find_followups_window_and_subject(tmp_path):
    r = _mk_repo(tmp_path)
    root = r["root"]
    merged_at = NOW - 60 * DAY
    _commit(root, "feat: later feature", merged_at + 1 * DAY, {"a.txt": "feat\n"})
    near = _commit(root, "fix: quick fix", merged_at + 3 * DAY, {"a.txt": "fix1\n"})
    _commit(root, "fix: much later", merged_at + 20 * DAY, {"b.txt": "fix2\n"})
    case = _mk_case(base_sha=r["base"], merge_sha=r["merge"])
    found = pe.find_followups(root, case, 14)
    assert found == [f"c1: follow-up {near[:12]} fix: quick fix"]


def test_validate_git_checks_parent_and_files(tmp_path):
    r = _mk_repo(tmp_path)
    case = _mk_case(base_sha=r["base"], merge_sha=r["merge"])
    corpus = {"schema_version": 1, "window_days": 14, "cases": [case]}
    assert pe.validate_git(r["root"], corpus) == []
    case["expected_files"] = ["a.txt", "unrelated.txt"]
    assert any("unrelated.txt" in e for e in pe.validate_git(r["root"], corpus))


def test_plan_surface_hash_tracks_skill_edit(tmp_path):
    r = _mk_repo(tmp_path)
    root = r["root"]
    clean = pe.surface_hash_worktree(root)
    assert clean == pe.surface_hash_ref(root, "HEAD")
    (root / "unrelated.txt").write_text("changed\n")
    assert pe.surface_hash_worktree(root) == clean
    (root / ".claude/skills/plan/SKILL.md").write_text("skill v2\n")
    assert pe.surface_hash_worktree(root) != clean
    assert ".claude/skills/plan/SKILL.md" in pe.expand_surface(pe.worktree_lister(root))


def test_validate_cli_rejects_unknown_flag():
    assert pe.main(["validate", "--bogus"]) == 2
