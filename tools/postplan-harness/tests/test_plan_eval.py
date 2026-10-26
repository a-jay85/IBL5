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


# ---------------------------------------------------------------------------
# Phase 3: scorer
# ---------------------------------------------------------------------------

PLAN_GOOD = """---
impl_model: sonnet
---

# Plan: canned

## Phase 1: Do it

Edit things.

## Phase 2: Test it

Test things.

## Critical Files

- `bin/plan-now` Modify: the wrapper.
- `bin/test-plan-now` Modify: its tests.

## Verification Matrix

| # | What | Type | Timing | Where |
|---|------|------|--------|-------|
| 1 | it works | CLI-executable | post-impl | `bin/test-plan-now` |
"""

PLAN_NO_MATRIX = PLAN_GOOD.split("## Verification Matrix")[0]
PLAN_EMPTY = ""
SCASE = {"id": "c1", "tier": "plan-architect",
         "expected_files": ["bin/plan-now", "bin/test-plan-now"]}


def _rec(**kw):
    r = {"id": "c1", "run": 1, "status": "ok", "plan_text": PLAN_GOOD, "check_plan_rc": 0,
         "staleness_rc": 0, "critical_files": ["bin/plan-now", "bin/test-plan-now"],
         "tier_chosen": "plan-architect", "output_tokens": 1, "cache_read_tokens": 1,
         "cache_write_tokens": 1, "cost_usd": 1.5, "wall_s": 1, "detail": ""}
    r.update(kw)
    return r


def test_score_run_good_plan_full_marks():
    s = pe.score_run(_rec(), SCASE)
    assert s == pe.RunScore(mech=True, file_hits=2, file_total=2, tier_ok=True, scored=True)


def test_score_run_missing_matrix_fails_mech():
    assert pe.score_run(_rec(plan_text=PLAN_NO_MATRIX), SCASE).mech is False


def test_score_run_check_plan_failure_fails_mech():
    assert pe.score_run(_rec(check_plan_rc=1), SCASE).mech is False


def test_score_run_staleness_failure_fails_mech():
    assert pe.score_run(_rec(staleness_rc=1), SCASE).mech is False


def test_score_run_no_plan_counts_as_zero():
    s = pe.score_run(_rec(status="no_plan", plan_text=PLAN_EMPTY), SCASE)
    assert s.scored is True and s.file_hits == 0 and s.mech is False and s.file_total == 2


def test_score_run_degraded_unscored():
    for status in ("degraded", "leaked"):
        assert pe.score_run(_rec(status=status), SCASE).scored is False
    recs = [_rec(), _rec(status="degraded"), _rec(status="leaked")]
    score = pe.compute_score([pe.score_run(r, SCASE) for r in recs], recs,
                             v="a" * 12, c="b" * 12)
    assert (score.runs, score.files_total, score.degraded, score.leaked) == (1, 2, 1, 1)
    assert score.cost_usd == 4.5


def test_score_run_path_normalization():
    assert pe.score_run(_rec(critical_files=["./bin/plan-now"]), SCASE).file_hits == 1


def test_compute_score_all_degraded_raises():
    recs = [_rec(status="degraded"), _rec(status="degraded")]
    with pytest.raises(pe.EvalUsageError):
        pe.compute_score([pe.score_run(r, SCASE) for r in recs], recs, v="a" * 12, c="b" * 12)


def _score(**kw):
    base = dict(v="a" * 12, c="b" * 12, runs=4, mech=3, files=9, files_total=12, tier=3,
                no_plan=0, degraded=0, leaked=0, cost_usd=12.5)
    base.update(kw)
    return pe.PlanScore(**base)


def test_score_line_round_trip():
    s = _score()
    assert pe.parse_score_line(pe.format_score_line(s)) == s
    bad = pe.format_score_line(s).replace("mech=3/4", "mech=5/4")
    with pytest.raises(pe.EvalUsageError):
        pe.parse_score_line(bad)


def test_is_worse_detects_tier_drop():
    assert pe.is_worse(_score(), _score(tier=2)) is True
    assert pe.is_worse(_score(), _score()) is False
    with pytest.raises(pe.EvalUsageError):
        pe.is_worse(_score(), _score(runs=3, mech=3, tier=3))


def test_critical_files_from_real_check_plan(tmp_path):
    plan = tmp_path / "plan.md"
    plan.write_text(PLAN_GOOD)
    got = pe.critical_files_of(plan, REPO_ROOT / "bin" / "check-plan")
    assert got == ["bin/plan-now", "bin/test-plan-now"]


# ---------------------------------------------------------------------------
# Phase 4: replay clone, overlay, stub gh, prompt
# ---------------------------------------------------------------------------

def _git_out(repo, *args):
    return subprocess.run(["git", "-C", str(repo), *args], check=True,
                          capture_output=True, text=True).stdout


def _abc_repo(tmp_path):
    repo = tmp_path / "abc"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", "-b", "main", str(repo)], check=True)
    a = _commit(repo, "A", NOW, {"f.txt": "a\n"})
    b = _commit(repo, "B", NOW + 1, {"f.txt": "b\n"})
    c = _commit(repo, "C", NOW + 2, {"f.txt": "c\n"})
    subprocess.run(["git", "-C", str(repo), "branch", "feat", c], check=True)
    subprocess.run(["git", "-C", str(repo), "tag", "v1", c], check=True)
    return repo, a, b, c


def test_replay_clone_hides_future_history(tmp_path):
    repo, a, b, c = _abc_repo(tmp_path)
    with pe.ReplayClone(repo, b) as rc:
        assert _git_out(rc.root, "log", "--all", "--format=%H").split() == [b, a]
        assert _git_out(rc.root, "tag").strip() == ""
        assert _git_out(rc.root, "remote").strip() == ""
        assert (rc.root / "f.txt").read_text() == "b\n"
        assert (rc.plans_dir / ".drafts").is_dir()


def test_replay_clone_removed_on_exit(tmp_path):
    repo, a, b, c = _abc_repo(tmp_path)
    with pe.ReplayClone(repo, b) as rc:
        tmp = rc.tmp
        assert tmp.is_dir()
    assert not tmp.exists()
    with pe.ReplayClone(repo, b, keep=True) as rc:
        kept = rc.tmp
    assert kept.is_dir()
    import shutil
    shutil.rmtree(kept)


def test_replay_clone_bad_sha_raises(tmp_path):
    repo, a, b, c = _abc_repo(tmp_path)
    with pytest.raises(pe.SurfaceError):
        with pe.ReplayClone(repo, "0" * 40):
            pass


def test_overlay_replaces_and_deletes_stale(tmp_path):
    src = tmp_path / "src"
    clone = tmp_path / "clone"
    subprocess.run(["git", "init", "-q", str(src)], check=True)
    _commit(src, "src", NOW, {".claude/skills/plan/SKILL.md": "new skill\n",
                              "bin/check-plan": "#!/bin/sh\nexit 0\n"})
    (src / "bin/check-plan").chmod(0o755)
    (clone / ".claude/skills/plan").mkdir(parents=True)
    (clone / ".claude/skills/plan/old.md").write_text("stale\n")
    (clone / ".claude/skills/plan/SKILL.md").write_text("old skill\n")
    (clone / "bin").mkdir()
    (clone / "bin/check-plan").write_text("old\n")
    pe.overlay_surface(src, clone)
    assert not (clone / ".claude/skills/plan/old.md").exists()
    assert (clone / ".claude/skills/plan/SKILL.md").read_text() == "new skill\n"
    assert os.stat(clone / "bin/check-plan").st_mode & 0o777 == 0o755


def test_stub_gh_silent_success(tmp_path):
    gh = pe.write_stub_gh(tmp_path / "stubbin")
    r = subprocess.run([str(gh), "pr", "list", "--state", "merged"],
                       capture_output=True, text=True)
    assert r.returncode == 0 and r.stdout == "" and r.stderr == ""


def test_build_prompt_rewrites_slug_and_plans_dir(tmp_path):
    prompt = "Fix the thing.\n\n- Branch slug: real-slug\n- Base: master\n"
    out = pe.build_prompt(prompt, "eval-x-r1", tmp_path / "plans", "real-slug")
    assert out.startswith("/plan ")
    assert "- Branch slug: eval-x-r1" in out
    assert "- Branch slug: real-slug" not in out
    assert f"PLAN_FILE: {tmp_path / 'plans'}/eval-x-r1.md" in out
    assert "~/claude-plans/<slug>.md" not in out


def test_canned_plan_passes_check_plan():
    plan = REPO_ROOT / pe.CANNED_PLAN
    for tool in ("bin/check-plan", "bin/check-plan-staleness"):
        r = subprocess.run([str(REPO_ROOT / tool), str(plan)], cwd=str(REPO_ROOT),
                           capture_output=True, text=True)
        assert r.returncode == 0, (tool, r.stdout, r.stderr)


# ---------------------------------------------------------------------------
# Phase 5: stream parse, plan resolution, leak sweep, single-case worker
# ---------------------------------------------------------------------------

FAKE_CLAUDE = REPO_ROOT / "tools/postplan-harness/fixtures/plan-eval/fake-claude.py"


def _lines(*events):
    return [json.dumps(e) for e in events]


def _asst(mid, out_tokens, blocks=()):
    return {"type": "assistant", "message": {
        "id": mid, "usage": {"output_tokens": out_tokens, "cache_read_input_tokens": 1000,
                             "cache_creation_input_tokens": 10},
        "content": list(blocks)}}


def _tool(name, inp):
    return {"type": "tool_use", "name": name, "input": inp}


def test_parse_stream_reads_tier_tokens_cost():
    lines = _lines(
        _asst("m1", 100, [{"type": "text", "text": "hi"}]),
        _asst("m1", 100, [_tool("Agent", {"subagent_type": "plan-architect-sonnet"})]),
        {"type": "assistant", "parent_tool_use_id": "t1", "message": {
            "id": "sub1", "usage": {"output_tokens": 50}, "content": []}},
        _asst("m2", 7, [_tool("Agent", {"subagent_type": "plan-architect-xhigh"})]),
        {"type": "result", "result": "done", "total_cost_usd": 3.5},
    )
    f = pe.parse_stream(lines, "orig")
    assert f.tier_chosen == "plan-architect-sonnet"
    assert f.output_tokens == 157
    assert f.cache_read_tokens == 2000
    assert f.cost_usd == 3.5
    assert f.has_result and f.final_text == "done"


def test_parse_stream_skips_bad_lines_and_flags_no_result(tmp_path):
    f = pe.parse_stream(["this is not json {"], "orig")
    assert f.bad_lines == 1
    assert not f.has_result


def test_parse_stream_detects_answer_key_read():
    bad = pe.parse_stream(_lines(_asst("m1", 1, [
        _tool("Read", {"file_path": "/Users/x/claude-plans/plan-now-resume-2.md"})])),
        "plan-now-resume")
    assert bad.contaminated == "claude-plans/plan-now-resume-2.md"
    ok = pe.parse_stream(_lines(_asst("m1", 1, [
        _tool("Read", {"file_path": "/Users/x/claude-plans/plan-now-resume-shared-context.md"})])),
        "plan-now-resume")
    assert ok.contaminated == ""


def test_marker_outside_plans_dir_rejected(tmp_path):
    plans = tmp_path / "plans"
    plans.mkdir()
    outside = tmp_path / "elsewhere.md"
    outside.write_text("x\n")
    status, detail, plan = pe.resolve_plan(f"done\nPLAN_FILE: {outside}", plans, "eval-x-r1")
    assert status == "no_plan" and plan is None
    status, _, plan = pe.resolve_plan("done\nPLAN_FILE: /tmp/elsewhere.md", plans, "eval-x-r1")
    assert status == "no_plan"


def test_marker_decorated_accepted(tmp_path):
    plans = tmp_path / "plans"
    plans.mkdir()
    p = plans / "eval-x-r1.md"
    p.write_text("x\n")
    status, detail, plan = pe.resolve_plan(f"done\n**PLAN_FILE:** `{p}`", plans, "eval-x-r1")
    assert status == "ok" and detail == "" and plan == p.resolve()
    status, detail, _ = pe.resolve_plan("done\nNO_PLAN: already merged in #12", plans, "x")
    assert status == "no_plan" and "already merged" in detail
    status, detail, plan = pe.resolve_plan("done, no marker", plans, "eval-x-r1")
    assert status == "ok" and detail == "marker missing; adopted eval-x-r1.md"


def test_sweep_leaks_deletes_only_prefixed_files(tmp_path):
    home = tmp_path / "claude-plans"
    (home / ".drafts").mkdir(parents=True)
    for name in ("eval-a-r1.md", ".drafts/eval-a-r1.draft.md", "eval-a-r10.md",
                 "evaluation.md", "plan-eval.md"):
        (home / name).write_text("x\n")
    (home / "eval-a-r1").mkdir()
    hits = pe.sweep_leaks(home, "eval-a-r1")
    assert sorted(hits) == [".drafts/eval-a-r1.draft.md", "eval-a-r1.md"]
    assert not (home / "eval-a-r1.md").exists()
    assert not (home / ".drafts/eval-a-r1.draft.md").exists()
    for name in ("eval-a-r10.md", "evaluation.md", "plan-eval.md", "eval-a-r1"):
        assert (home / name).exists(), name
    assert pe.sweep_leaks(home, pattern=pe.LEAK_ANY_RE) == ["eval-a-r10.md"]


def test_claude_bin_empty_env_uses_default(monkeypatch):
    monkeypatch.delenv("PLAN_EVAL_CLAUDE", raising=False)
    assert pe.claude_bin() == "claude"
    monkeypatch.setenv("PLAN_EVAL_CLAUDE", "")
    assert pe.claude_bin() == "claude"
    monkeypatch.setenv("PLAN_EVAL_CLAUDE", "/x/fake")
    assert pe.claude_bin() == "/x/fake"


def _fake_shim(tmp_path):
    shim = tmp_path / "fake-claude"
    shim.write_text(f'#!/bin/sh\nexec "{sys.executable}" "{FAKE_CLAUDE}" "$@"\n')
    shim.chmod(0o755)
    return str(shim)


def _e2e(tmp_path, monkeypatch, mode="ok", sleep="0", timeout=60):
    repo = _mk_repo(tmp_path)
    corpus_dir = tmp_path / "corpus"
    case = _mk_case(id="x", base_sha=repo["base"], merge_sha=repo["merge"],
                    prompt="prompts/x.txt")
    _write_prompts(corpus_dir, [case], text="Do the thing.\n\n- Branch slug: x\n")
    home = tmp_path / "home-plans"
    monkeypatch.setenv("FAKE_CLAUDE_MODE", mode)
    monkeypatch.setenv("FAKE_CLAUDE_SLEEP", sleep)
    monkeypatch.setenv("FAKE_CLAUDE_LEAK_DIR", str(home))
    cache = tmp_path / "cache"
    seen = []
    real_clone = pe.ReplayClone

    class SpyClone(real_clone):
        def __enter__(self):
            r = super().__enter__()
            seen.append(self.tmp)
            return r

    monkeypatch.setattr(pe, "ReplayClone", SpyClone)
    rec = pe.run_case(case, 1, repo=repo["root"], source_root=REPO_ROOT, corpus_dir=corpus_dir,
                      cache_dir=cache, claude=_fake_shim(tmp_path), timeout=timeout,
                      plans_home=home)
    return rec, cache, seen, home


def test_run_case_end_to_end_fake_claude(tmp_path, monkeypatch):
    rec, cache, seen, _ = _e2e(tmp_path, monkeypatch)
    assert rec["status"] == "ok", rec["detail"]
    on_disk = json.loads((cache / "x-r1.json").read_text())
    assert on_disk["status"] == "ok"
    assert on_disk["critical_files"] == ["bin/check-plan"]
    assert on_disk["check_plan_rc"] == 0
    assert on_disk["tier_chosen"] == "plan-architect"
    assert "## Verification Matrix" in on_disk["plan_text"]
    assert (cache / "eval-x-r1.stream.jsonl").is_file()
    assert seen and not seen[0].exists()


def test_run_case_timeout_is_degraded(tmp_path, monkeypatch):
    rec, cache, seen, _ = _e2e(tmp_path, monkeypatch, sleep="5", timeout=1)
    assert rec["status"] == "degraded"
    on_disk = json.loads((cache / "x-r1.degraded.json").read_text())
    assert "timeout" in on_disk["detail"]
    assert not (cache / "x-r1.json").exists()


def test_run_case_leak_marks_leaked(tmp_path, monkeypatch):
    rec, cache, seen, home = _e2e(tmp_path, monkeypatch, mode="leak")
    on_disk = json.loads((cache / "x-r1.degraded.json").read_text())
    assert on_disk["status"] == "leaked"
    assert "eval-x-r1.md" in on_disk["detail"]
    assert not (home / "eval-x-r1.md").exists()


# ---------------------------------------------------------------------------
# Phase 6: run orchestration, cache, estimate, compare, smoke
# ---------------------------------------------------------------------------

def _run_env(tmp_path):
    repo = _mk_repo(tmp_path)
    cdir = tmp_path / "corpus"
    cdir.mkdir()
    corpus = _corpus4(cdir, base_sha=repo["base"], merge_sha=repo["merge"])
    cpath = cdir / "corpus.json"
    cpath.write_text(json.dumps(corpus))
    return repo["root"], cpath, corpus


def _cache_of(root, cpath, corpus):
    v = pe.surface_hash_worktree(root)
    c = pe.corpus_digest(corpus, cpath.parent)
    return root / pe.CACHE_ROOT / f"{v}-{c}"


class _Launcher:
    def __init__(self, pid=None):
        self.calls = []
        self.pid = pid if pid is not None else os.getpid()

    def __call__(self, spec):
        self.calls.append(spec)
        return self.pid


def _run(tmp_path, root, cpath, *extra, launcher=None):
    args = pe.build_parser().parse_args(
        ["run", "--repo", str(root), "--corpus", str(cpath),
         "--plans-home", str(tmp_path / "plans-home"), *extra])
    out, err = io.StringIO(), io.StringIO()
    rc = pe._cmd_run(args, out, err, launcher=launcher or _Launcher(), sleep=lambda s: None)
    return rc, out.getvalue(), err.getvalue()


def _ok_record(case, k=1):
    return {"case_id": case["id"], "run": k, "status": "ok", "check_plan_rc": 0,
            "staleness_rc": 0, "plan_text": PLAN_GOOD, "critical_files": ["a.txt", "b.txt"],
            "tier_chosen": case["tier"], "cost_usd": 1.0, "detail": ""}


def test_run_dry_run_estimate_launches_nothing(tmp_path):
    root, cpath, corpus = _run_env(tmp_path)
    launcher = _Launcher()
    rc, out, _ = _run(tmp_path, root, cpath, "--dry-run", launcher=launcher)
    assert rc == 0
    assert "estimate launch=4 output_tokens=294000 " in out
    assert out.count("to-launch ") == 4
    assert launcher.calls == []
    assert not (root / pe.CACHE_ROOT).exists()


def test_run_scores_from_cache_without_launching(tmp_path):
    root, cpath, corpus = _run_env(tmp_path)
    cache = _cache_of(root, cpath, corpus)
    cache.mkdir(parents=True)
    for case in corpus["cases"]:
        pe.write_record(cache, _ok_record(case))
    launcher = _Launcher()
    rc, out, err = _run(tmp_path, root, cpath, launcher=launcher)
    assert rc == 0, err
    lines = out.strip().splitlines()
    assert len(lines) == 1 and pe.SCORE_LINE_RE.match(lines[0])
    assert "mech=4/4 files=8/8 tier=4/4" in lines[0]
    assert launcher.calls == []
    assert err.count("case c") == 4


def test_run_parallel_cap(tmp_path):
    root, cpath, _ = _run_env(tmp_path)
    launcher = _Launcher()
    rc, out, _ = _run(tmp_path, root, cpath, "--parallel", "2", "--wait", "0",
                      launcher=launcher)
    assert rc == 4
    assert len(launcher.calls) == 2


def test_run_pending_exits_4(tmp_path):
    root, cpath, _ = _run_env(tmp_path)
    rc, out, _ = _run(tmp_path, root, cpath, "--wait", "0", "--parallel", "4")
    assert rc == 4
    assert out.startswith("pending=4 ")


def test_run_never_relaunches_alive_worker(tmp_path):
    root, cpath, corpus = _run_env(tmp_path)
    first = _Launcher()
    _run(tmp_path, root, cpath, "--wait", "0", "--parallel", "4", launcher=first)
    assert len(first.calls) == 4
    second = _Launcher()
    rc, _, _ = _run(tmp_path, root, cpath, "--wait", "0", "--parallel", "4", launcher=second)
    assert rc == 4
    assert second.calls == []


def test_run_dead_worker_becomes_degraded(tmp_path):
    root, cpath, corpus = _run_env(tmp_path)
    cache = _cache_of(root, cpath, corpus)
    cache.mkdir(parents=True)
    for case in corpus["cases"][1:]:
        pe.write_record(cache, _ok_record(case))
    child = subprocess.Popen(["true"])
    child.wait()
    key = f"{corpus['cases'][0]['id']}-r1"
    (cache / f"{key}.pending").write_text(json.dumps({"pid": child.pid, "started": NOW}))
    launcher = _Launcher()
    rc, out, err = _run(tmp_path, root, cpath, launcher=launcher)
    assert rc == 3, (out, err)
    rec = json.loads((cache / f"{key}.degraded.json").read_text())
    assert rec["detail"] == "worker died"
    assert not (cache / f"{key}.pending").exists()
    assert launcher.calls == []
    assert "degraded=1" in out


@pytest.mark.parametrize("bad", [["--runs", "0"], ["--runs", "4"], ["--cases", "nope"],
                                 ["--wait", "-1"], ["--parallel", "5"], ["--bogus"]])
def test_run_rejects_bad_flags(tmp_path, bad):
    root, cpath, _ = _run_env(tmp_path)
    rc = pe.main(["run", "--repo", str(root), "--corpus", str(cpath), "--dry-run", *bad])
    assert rc == 2


def test_run_base_ref_uses_ref_hash(tmp_path):
    root, cpath, corpus = _run_env(tmp_path)
    _commit(root, "chore: skill edit", NOW, {".claude/skills/plan/SKILL.md": "skill v2\n"})
    head1 = _git_out(root, "rev-parse", "HEAD~1").strip()
    after = _Launcher()
    _run(tmp_path, root, cpath, "--wait", "0", "--parallel", "1", launcher=after)
    before = _Launcher()
    _run(tmp_path, root, cpath, "--wait", "0", "--parallel", "1", "--base", "HEAD~1",
         launcher=before)
    assert Path(after.calls[0]["cache_dir"]).name != Path(before.calls[0]["cache_dir"]).name
    assert before.calls[0]["source_ref"] == head1
    argv = pe.worker_argv(before.calls[0])
    assert argv[argv.index("--source-ref") + 1] == head1
    assert "--source-root" not in argv
    assert "--source-ref" not in pe.worker_argv(after.calls[0])


def test_compare_worse_and_mismatch(capsys):
    base = "plan-eval v=aaaaaaaaaaaa c=bbbbbbbbbbbb runs=4 mech=4/4 files=8/10 tier=3/4 " \
           "no_plan=0 degraded=0 leaked=0 cost_usd=10.00"
    drop = base.replace("tier=3/4", "tier=2/4")
    assert pe.main(["compare", "--before", base, "--after", drop]) == 1
    assert capsys.readouterr().out.strip() == "worse"
    assert pe.main(["compare", "--before", base, "--after", base]) == 0
    assert capsys.readouterr().out.strip() == "not-worse"
    other = base.replace("runs=4 mech=4/4", "runs=3 mech=3/3").replace("tier=3/4", "tier=3/3")
    assert pe.main(["compare", "--before", base, "--after", other]) == 2


def test_smoke_inprocess_tmp_repo(tmp_path, capsys):
    root, cpath, _ = _run_env(tmp_path)
    rc = pe.main(["smoke", "--repo", str(root), "--corpus", str(cpath),
                  "--source-root", str(REPO_ROOT)])
    out = capsys.readouterr().out
    assert rc == 0, out
    assert out.startswith("smoke ok plan-eval ")


SYSTEM_PYTHON = "/usr/bin/python3"


@pytest.mark.skipif(not os.path.exists(SYSTEM_PYTHON), reason="no system python3")
def test_module_runs_under_system_python(tmp_path):
    """The macOS system Python is 3.9: a runtime `X | Y` or a `match` breaks this."""
    env = {"PATH": "/usr/bin:/bin",
           "PYTHONPATH": str(REPO_ROOT / "tools/postplan-harness")}
    r = subprocess.run([SYSTEM_PYTHON, "-m", "harness.plan_eval", "compare",
                        "--before", "x", "--after", "y"],
                       capture_output=True, text=True, env=env, cwd=str(tmp_path))
    assert r.returncode == 2, r.stderr
    assert "malformed score line" in r.stderr


def test_smoke_detached_survives_launcher_kill(tmp_path, capsys):
    root, cpath, _ = _run_env(tmp_path)
    rc = pe.main(["smoke", "--detached", "--repo", str(root), "--corpus", str(cpath),
                  "--source-root", str(REPO_ROOT)])
    out = capsys.readouterr().out
    assert rc == 0, out
    assert out.strip() == "smoke-detached ok"
