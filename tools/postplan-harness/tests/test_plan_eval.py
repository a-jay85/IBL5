from __future__ import annotations

import io
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
    _commit(root, "feat: later feature", merged_at + 1 * DAY, {"unrelated.txt": "feat\n"})
    near = _commit(root, "fix: quick fix", merged_at + 3 * DAY, {"a.txt": "fix1\n"})
    _commit(root, "fix: much later", merged_at + 20 * DAY, {"b.txt": "fix2\n"})
    case = _mk_case(base_sha=r["base"], merge_sha=r["merge"])
    found = pe.find_followups(root, case, 14)
    assert found == [f"c1: follow-up {near[:12]} fix: quick fix"]


def test_find_followups_ignores_fix_not_blaming_pr_lines(tmp_path):
    r = _mk_repo(tmp_path)
    root = r["root"]
    merged_at = NOW - 60 * DAY
    # Appends to a.txt without changing the PR's line, so it does not blame the PR.
    _commit(root, "fix: append only", merged_at + 2 * DAY, {"a.txt": "a2\nextra\n"})
    case = _mk_case(base_sha=r["base"], merge_sha=r["merge"])
    assert pe.find_followups(root, case, 14) == []


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


# ---------------------------------------------------------------------------
# Phase 2: mine
# ---------------------------------------------------------------------------

def test_parse_tier_prefers_specific_suffix():
    assert pe.parse_tier("Step 3 MUST route to plan-architect-xhigh.") == "plan-architect-xhigh"
    assert pe.parse_tier("routes to the default plan-architect") == "plan-architect"
    assert pe.parse_tier("plan-architect-sonnet (recipe)") == "plan-architect-sonnet"
    assert pe.parse_tier("no tier named") == "plan-architect"


def test_expected_files_drop_last_verified_only(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    base = _commit(repo, "base", NOW, {"a.md": "---\nlast_verified: 2026-01-01\n---\nA\n",
                                       "b.md": "B\n"})
    head = _commit(repo, "docs", NOW + DAY, {"a.md": "---\nlast_verified: 2026-02-01\n---\nA\n",
                                             "b.md": "B2\n"})
    assert pe.expected_files_for(repo, base, head) == ["b.md"]


def _mine_repo(tmp_path, spec):
    """spec: list of (slug, pr, tier, prompt_text). One merge commit per slug, 2 files each."""
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    _commit(repo, "base", NOW - 100 * DAY, {"seed.txt": "s\n"})
    lookup, rows = {}, []
    for i, (slug, pr, tier, prompt) in enumerate(spec):
        sha = _commit(repo, f"feat: {slug} (#{pr})", NOW - 90 * DAY + i * 60,
                      {f"{slug}/x.txt": "x\n", f"{slug}/y.txt": "y\n"})
        if pr is not None:
            lookup[slug] = (pr, sha)
        rows.append({"slug": slug, "tier_line": f"Step 3 MUST route to {tier}.",
                     "prompt": prompt})
    return repo, rows, lookup


def _spec(n_default, n_sonnet, n_xhigh):
    spec, pr = [], 100
    for tier, n in (("plan-architect", n_default), ("plan-architect-sonnet", n_sonnet),
                    ("plan-architect-xhigh", n_xhigh)):
        short = {"plan-architect": "d", "plan-architect-sonnet": "s",
                 "plan-architect-xhigh": "x"}[tier]
        for i in range(n):
            # z-prefixed slugs sort opposite to PR order, so a slug sort picks wrong rows
            spec.append((f"{'z' * (n - i)}{short}{i}", pr, tier, "p" * (50 + pr)))
            pr += 1
    return spec


def test_select_cases_quota_and_order(tmp_path):
    repo, rows, lookup = _mine_repo(tmp_path, _spec(8, 3, 4))
    cases = pe.select_cases(rows, lookup.get, repo, 14, out=io.StringIO())
    by_tier = {t: sorted(c["pr"] for c in cases if c["tier"] == t) for t in pe.TIERS}
    assert by_tier["plan-architect"] == [100, 101, 102, 103, 104]
    assert by_tier["plan-architect-sonnet"] == [108, 109]
    assert by_tier["plan-architect-xhigh"] == [111, 112, 113]


def test_select_cases_shortfall_backfills_default(tmp_path):
    repo, rows, lookup = _mine_repo(tmp_path, _spec(8, 1, 4))
    out = io.StringIO()
    cases = pe.select_cases(rows, lookup.get, repo, 14, out=out)
    assert len(cases) == 10
    tiers = [c["tier"] for c in cases]
    assert tiers.count("plan-architect") == 6
    assert tiers.count("plan-architect-sonnet") == 1
    assert "shortfall plan-architect-sonnet 1/2" in out.getvalue()


def test_select_cases_zero_tier_exits_1(tmp_path, monkeypatch):
    repo, rows, lookup = _mine_repo(tmp_path, _spec(6, 2, 0))
    pdir = tmp_path / "prompts-in"
    pdir.mkdir()
    lines = []
    for r in rows:
        (pdir / f"{r['slug']}.txt").write_text(r["prompt"])
        lines.append(f"{r['slug']}\t0\t2026-01-01\t0\t{r['tier_line']}")
    (pdir / "INDEX.tsv").write_text("\n".join(lines) + "\n")
    monkeypatch.setattr(pe, "gh_lookup", lookup.get)
    out_dir = tmp_path / "out"
    rc = pe.main(["mine", "--prompts-dir", str(pdir), "--repo", str(repo),
                  "--out-dir", str(out_dir)])
    assert rc == 1
    assert not (out_dir / "corpus.json").exists()


def test_select_cases_core_is_smallest_prompts(tmp_path):
    spec = _spec(5, 2, 3)
    sizes = {"zzzzzd0": 900, "zzzzd1": 300, "zzzd2": 100, "zzd3": 700, "zd4": 500,
             "zzs0": 400, "zs1": 200, "zzzx0": 800, "zzx1": 150, "zx2": 600}
    spec = [(slug, pr, tier, "p" * sizes[slug]) for slug, pr, tier, _ in spec]
    repo, rows, lookup = _mine_repo(tmp_path, spec)
    cases = pe.select_cases(rows, lookup.get, repo, 14, out=io.StringIO())
    assert sorted(c["id"] for c in cases if c["core"]) == sorted(["zzzd2", "zzzzd1", "zs1", "zzx1"])


def test_select_cases_skips_missing_pr(tmp_path):
    spec = _spec(6, 2, 3)
    spec[0] = (spec[0][0], None, spec[0][2], spec[0][3])
    repo, rows, lookup = _mine_repo(tmp_path, spec)
    out = io.StringIO()
    cases = pe.select_cases(rows, lookup.get, repo, 14, out=out)
    assert f"skip {spec[0][0]}: no merged PR" in out.getvalue()
    assert spec[0][0] not in {c["id"] for c in cases}
