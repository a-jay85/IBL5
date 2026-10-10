"""Unit tests for the conflict escalation ladder (harness/conflict_ladder.py) and the
Phase 1/4 plumbing it relies on (`_run_proof`, `resolve_one` kwargs, `extract_stages`
revs, `RunResult.conflict_rung`).

Every ladder test builds a real temp repo, merges master into the branch by hand, and
commits so HEAD has parents (pre, master). `prove` is a stub closure over the worktree.
"""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import uuid
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import conflict_ladder
from harness.adapters.gitad import LiveGit
from harness.adapters.llm import MODEL_MAP, FixtureLlm
from harness.conflict import extract_stages, resolve_one
from harness.conflict_ladder import (
    GENERATED_FILES,
    OPUS_MAX_FILES,
    GeneratedFile,
    LadderContext,
    RungAttempt,
    ladder_failure_reason,
    make_opus_rung,
    make_regen_rung,
    make_session_rung,
    parse_lost_lines,
    run_ladder,
)
from harness.rebase_cause import CAUSE_TREE_PROOF, classify_rebase_block
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger
from test_conflict_resolver import _make_temp_file, _StubLlm, _StubRun

_GIT_ENV = {
    "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
    "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t",
}


# -- fixtures and helpers -------------------------------------------------------

@pytest.fixture()
def key():
    k = f"ladder-{uuid.uuid4().hex[:10]}"
    yield k
    shutil.rmtree(f"/tmp/postplan-conflict-stages-{k}", ignore_errors=True)
    try:
        os.remove(f"/tmp/postplan-lostwork-{k}.sh")
    except OSError:
        pass


def _git(d, *args, check=True):
    r = subprocess.run(["git", "-C", str(d), *args], capture_output=True, text=True,
                       env={**os.environ, **_GIT_ENV})
    if check and r.returncode != 0:
        raise AssertionError(f"git {args} failed: {r.stderr or r.stdout}")
    return r


def _write(d, files):
    for rel, text in files.items():
        full = Path(d) / rel
        full.parent.mkdir(parents=True, exist_ok=True)
        full.write_text(text)


def _build_merge_repo(d, base, feat, master, resolved):
    """Real repo whose HEAD is a hand-built merge commit with parents (pre, master).
    Returns (pre_sha, master_sha, merge_sha). The merge must genuinely conflict."""
    d = Path(d)
    d.mkdir(parents=True, exist_ok=True)
    subprocess.run(["git", "init", "-b", "master", str(d)], check=True, capture_output=True)
    _git(d, "config", "user.email", "t@t")
    _git(d, "config", "user.name", "t")
    _git(d, "config", "commit.gpgsign", "false")
    _write(d, base)
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "base")
    _git(d, "checkout", "-b", "feat")
    _write(d, feat)
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "feat")
    pre = _git(d, "rev-parse", "HEAD").stdout.strip()
    _git(d, "checkout", "master")
    _write(d, master)
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "master")
    master_sha = _git(d, "rev-parse", "HEAD").stdout.strip()
    _git(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _git(d, "checkout", "feat")
    merged = _git(d, "merge", "master", "--no-edit", check=False)
    assert merged.returncode != 0, "fixture merge must conflict"
    _write(d, resolved)
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "merge master (first pass)")
    parents = _git(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]
    assert parents == [pre, master_sha]
    return pre, master_sha, _git(d, "rev-parse", "HEAD").stdout.strip()


def _ctx(d, key, pre, master, conflicted, prove, llm=None):
    g = LiveGit(str(d))
    return LadderContext(
        llm=llm, run=g._run, run_out=g._run_out, prove=prove, worktree=str(d),
        key=key, master_sha=master, pre_rebase_sha=pre,
        conflicted_files=tuple(conflicted))


def _head(d):
    return _git(d, "rev-parse", "HEAD").stdout.strip()


def _a_repo(tmp_path):
    """a.txt conflict; the first-pass merge dropped the branch's `keep-me` line."""
    d = tmp_path / "repo"
    pre, master, merge = _build_merge_repo(
        d, {"a.txt": "base\n"}, {"a.txt": "feature\nkeep-me\n"}, {"a.txt": "master\n"},
        {"a.txt": "master\n"})
    return d, pre, master, merge


def _keepme_prove(d):
    def prove():
        if "keep-me" in (Path(d) / "a.txt").read_text():
            return True, "TREE-EQUIVALENT\n"
        return False, "LOST: a.txt: +keep-me\n"
    return prove


_KEEPME_PROOF = "LOST: a.txt: +keep-me\n"


def _write_keepme(ctx, proof):
    (Path(ctx.worktree) / "a.txt").write_text("master\nkeep-me\n")
    return RungAttempt(True, ("a.txt",))


# -- Phase 1: _run_proof / _prove_tree_equivalent -------------------------------

def _lostwork_script(tmp_path, body):
    p = tmp_path / "lostwork.sh"
    p.write_text("#!/usr/bin/env bash\n" + body)
    p.chmod(0o755)
    return p


_THIRTY_LOST = (
    "for i in $(seq 1 30); do echo \"LOST: f.txt: +line$i "
    "padpadpadpadpadpadpadpadpadpadpadpadpad\"; done\n"
    "echo 'TREE DIVERGED'\nexit 0\n"
)


def test_run_proof_returns_untruncated_stdout(tmp_path):
    git = LiveGit(str(tmp_path))
    ok, out = git._run_proof(_lostwork_script(tmp_path, _THIRTY_LOST), "k")
    assert ok is False
    assert len(out) > 400
    assert "LOST: f.txt: +line30 " in out


def test_run_proof_rc_nonzero_with_equivalent_text_fails(tmp_path):
    git = LiveGit(str(tmp_path))
    ok, out = git._run_proof(
        _lostwork_script(tmp_path, "echo TREE-EQUIVALENT\nexit 1\n"), "k")
    assert ok is False
    assert "TREE-EQUIVALENT" in out
    # positive control: both halves of the conjunction present
    ok, _ = git._run_proof(_lostwork_script(tmp_path, "echo TREE-EQUIVALENT\nexit 0\n"), "k")
    assert ok is True
    # a diverged tree exits 0 without the token
    ok, _ = git._run_proof(_lostwork_script(tmp_path, "echo 'TREE DIVERGED'\nexit 0\n"), "k")
    assert ok is False


def test_prove_tree_equivalent_reason_still_truncated(tmp_path):
    git = LiveGit(str(tmp_path))
    script = _lostwork_script(tmp_path, _THIRTY_LOST)
    _, full = git._run_proof(script, "k")
    ok, reason = git._prove_tree_equivalent(script, "k")
    assert ok is False
    assert reason == "tree proof failed: " + full.strip()[:400]
    assert len(reason) == len("tree proof failed: ") + 400


# -- Phase 1: resolve_one kwargs -------------------------------------------------

def test_resolve_one_forwards_model_and_extra_context(tmp_path):
    path = "a.txt"
    _make_temp_file(str(tmp_path), path, "resolved content\n")
    llm = _StubLlm(["RESOLVED"])
    ok, _ = resolve_one(llm, _StubRun(str(tmp_path)), worktree=str(tmp_path),
                        key="unused-k4", path=path, model="opus",
                        extra_context="LOST: a.txt: +keep-me")
    assert ok is True
    assert llm.calls[0]["model"] == "opus"
    assert "LOST: a.txt: +keep-me" in llm.calls[0]["prompt"]


def test_resolve_one_default_model_is_sonnet(tmp_path):
    path = "a.txt"
    _make_temp_file(str(tmp_path), path, "resolved content\n")
    llm = _StubLlm(["RESOLVED"])
    resolve_one(llm, _StubRun(str(tmp_path)), worktree=str(tmp_path),
                key="unused-k5", path=path)
    assert llm.calls[0]["model"] == "sonnet"
    assert "LOST:" not in llm.calls[0]["prompt"]


def test_resolve_one_max_rounds_one_makes_single_call(tmp_path):
    path = "a.txt"
    _make_temp_file(str(tmp_path), path, "resolved content\n")
    llm = _StubLlm(["I am not sure", "I am not sure", "I am not sure"])
    ok, _ = resolve_one(llm, _StubRun(str(tmp_path)), worktree=str(tmp_path),
                        key="unused-k6", path=path, max_rounds=1)
    assert ok is False
    assert len(llm.calls) == 1


# -- Phase 2: ladder core ---------------------------------------------------------

def test_parse_lost_lines_splits_path_and_line():
    out = (
        "LOST: docker-compose.ci.yml: +      GOOGLE_OAUTH_CLIENT_ID: x\n"
        "LOST: file missing at HEAD: b.txt\n"
        "LOST: deletion lost, still at HEAD: c.txt\n"
        "TREE DIVERGED\n"
    )
    lost, structural = parse_lost_lines(out)
    assert lost == {"docker-compose.ci.yml": ["+      GOOGLE_OAUTH_CLIENT_ID: x"]}
    assert structural == ["LOST: file missing at HEAD: b.txt",
                          "LOST: deletion lost, still at HEAD: c.txt"]


def test_ladder_stops_at_first_passing_rung(tmp_path, key):
    d, pre, master, _ = _a_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d))
    ran_last = []

    def skip(c, p):
        return RungAttempt(False, (), "nothing to do")

    def still_lost(c, p):
        (Path(c.worktree) / "a.txt").write_text("master\nother\n")
        return RungAttempt(True, ("a.txt",))

    def never(c, p):
        ran_last.append(True)
        raise AssertionError("must not run")

    out = run_ladder(ctx, _KEEPME_PROOF, (
        ("skipper", skip), ("weak", still_lost), ("fixer", _write_keepme), ("never", never)))
    assert out.rung == "fixer"
    assert ran_last == []
    assert out.trail[0] == "skipper: skipped (nothing to do)"
    assert out.trail[1].startswith("weak:")
    assert out.trail[-1] == "fixer: passed"
    assert len(out.trail) == 3


def test_guard_rejects_surviving_markers(tmp_path, key):
    d, pre, master, merge = _a_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d))

    def marker_rung(c, p):
        (Path(c.worktree) / "a.txt").write_text("master\nkeep-me\n<<<<<<< ours\n")
        return RungAttempt(True, ("a.txt",))

    out = run_ladder(ctx, _KEEPME_PROOF, (("marker", marker_rung),))
    assert out.rung == ""
    assert "markers" in out.trail[0]
    assert _head(d) == merge


def test_guard_rejects_rewritten_parents(tmp_path, key):
    d, pre, master, merge = _a_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d))

    def rewriter(c, p):
        c.run("reset", "--soft", "HEAD~1")
        (Path(c.worktree) / "a.txt").write_text("master\nkeep-me\n")
        c.run("add", "-A")
        c.run("commit", "-m", "rewritten on its own")
        return RungAttempt(True, ("a.txt",))

    out = run_ladder(ctx, _KEEPME_PROOF, (("rewriter", rewriter),))
    assert out.rung == ""
    assert "parents" in out.trail[0]
    assert _head(d) == merge
    assert "keep-me" not in (Path(d) / "a.txt").read_text()


def test_ladder_no_rung_passes_reports_full_trail(tmp_path, key):
    d, pre, master, merge = _a_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d))

    def weak_one(c, p):
        (Path(c.worktree) / "a.txt").write_text("master\none\n")
        return RungAttempt(True, ("a.txt",))

    def weak_two(c, p):
        (Path(c.worktree) / "a.txt").write_text("master\ntwo\n")
        return RungAttempt(True, ("a.txt",))

    out = run_ladder(ctx, _KEEPME_PROOF, (("one", weak_one), ("two", weak_two)))
    assert out.rung == ""
    assert len(out.trail) == 2
    assert out.trail[0].startswith("one:")
    assert out.trail[1].startswith("two:")
    assert "LOST: a.txt: +keep-me" in out.proof_out
    assert _head(d) == merge


def test_ladder_usage_limit_stops_remaining_rungs(tmp_path, key):
    d, pre, master, merge = _a_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d))
    called = []

    def limited(c, p):
        raise HarnessError("llm-usage-limit", "limit")

    def second(c, p):
        called.append(True)
        return RungAttempt(False, (), "x")

    out = run_ladder(ctx, _KEEPME_PROOF, (("first", limited), ("second", second)))
    assert called == []
    assert out.rung == ""
    assert out.trail[0].startswith("first: llm-usage-limit")
    assert len(out.trail) == 1
    assert _head(d) == merge


def test_ladder_carries_forward_partial_progress(tmp_path, key):
    d, pre, master, _ = _a_repo(tmp_path)

    def prove():
        out, missing = [], 0
        for rel, want in (("fa.txt", "A"), ("fb.txt", "B")):
            f = Path(d) / rel
            if not f.exists() or want not in f.read_text():
                out.append(f"LOST: {rel}: +{want}")
                missing += 1
        if missing:
            return False, "\n".join(out) + "\n"
        return True, "TREE-EQUIVALENT\n"

    ctx = _ctx(d, key, pre, master, ("a.txt",), prove)
    seen = {}

    def rung_a(c, p):
        (Path(c.worktree) / "fa.txt").write_text("A\n")
        return RungAttempt(True, ("fa.txt",))

    def rung_b(c, p):
        seen["fa_present"] = (Path(c.worktree) / "fa.txt").exists()
        (Path(c.worktree) / "fb.txt").write_text("B\n")
        return RungAttempt(True, ("fb.txt",))

    out = run_ladder(ctx, "LOST: fa.txt: +A\nLOST: fb.txt: +B\n",
                     (("A", rung_a), ("B", rung_b)))
    assert out.rung == "B"
    assert seen["fa_present"] is True
    assert out.trail == ("A: partial (1 LOST left)", "B: passed")
    assert "fa.txt" in out.resolved_files and "fb.txt" in out.resolved_files
    assert _git(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:] \
        == [pre, master]


# -- Phase 3: regen rung ---------------------------------------------------------

_MAKE_COUNTS = (
    "#!/usr/bin/env bash\n"
    "touch gen/.ran\n"
    "python3 - <<'EOF'\n"
    "import json\n"
    "ids = sorted(set(l.strip() for l in open('src/ids.txt') if l.strip()))\n"
    "open('gen/counts.json', 'w').write(json.dumps(ids) + '\\n')\n"
    "EOF\n"
)


def _counts(ids):
    return json.dumps(sorted(set(ids))) + "\n"


class _ForbiddenLlm:
    def __init__(self):
        self.calls = []

    def call(self, *a, **k):
        self.calls.append(("call", a))
        raise AssertionError("regen rung must not call the LLM")

    def call_tooled(self, *a, **k):
        self.calls.append(("call_tooled", a))
        raise AssertionError("regen rung must not call the LLM")


def _regen_repo(tmp_path):
    d = tmp_path / "regen"
    pre, master, merge = _build_merge_repo(
        d,
        {"src/ids.txt": "a\nc\nz\n", "gen/make-counts.sh": _MAKE_COUNTS,
         "gen/counts.json": _counts("acz"), "src/other.txt": "o\n"},
        {"src/ids.txt": "a\nb\nc\nz\n", "gen/counts.json": _counts("abcz")},
        {"src/ids.txt": "a\nc\nz\nm\n", "gen/counts.json": _counts("acmz")},
        {"gen/counts.json": _counts("acmz")})
    return d, pre, master, merge


def _counts_prove(d):
    def prove():
        ids = (Path(d) / "src/ids.txt").read_text().split()
        want = _counts(ids)
        have = (Path(d) / "gen/counts.json").read_text()
        if have == want:
            return True, "TREE-EQUIVALENT\n"
        return False, "LOST: gen/counts.json: +" + want.strip() + "\n"
    return prove


_COUNTS_REGISTRY = (GeneratedFile("gen/counts.json", ("bash", "gen/make-counts.sh")),)


def test_regen_rung_resolves_generated_conflict_without_llm(tmp_path, key):
    d, pre, master, _ = _regen_repo(tmp_path)
    llm = _ForbiddenLlm()
    ctx = _ctx(d, key, pre, master, ("gen/counts.json",), _counts_prove(d), llm=llm)
    out = run_ladder(ctx, "LOST: gen/counts.json: +[\"a\", \"b\"]\n",
                     (make_regen_rung(_COUNTS_REGISTRY),))
    assert out.rung == "regen"
    merged_ids = (Path(d) / "src/ids.txt").read_text().split()
    assert "b" in merged_ids and "m" in merged_ids
    assert (Path(d) / "gen/counts.json").read_text() == _counts(merged_ids)
    assert llm.calls == []


def test_regen_rung_skips_when_nothing_generated_is_involved(tmp_path, key):
    d, pre, master, merge = _regen_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("src/other.txt",), _counts_prove(d))
    out = run_ladder(ctx, "LOST: src/other.txt: +x\n",
                     (make_regen_rung(_COUNTS_REGISTRY),))
    assert out.rung == ""
    assert out.trail[0].startswith("regen: skipped")
    assert not (Path(d) / "gen/.ran").exists()
    assert _head(d) == merge


def test_regen_rung_copy_only_when_requirement_missing(tmp_path, key):
    d, pre, master, _ = _regen_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("gen/counts.json",), _counts_prove(d))
    registry = (GeneratedFile("gen/counts.json", ("bash", "gen/make-counts.sh"),
                              requires=("vendor/missing",)),)
    _, fn = make_regen_rung(registry)
    attempt = fn(ctx, "LOST: gen/counts.json: +x\n")
    assert attempt.applied is True
    assert "master copy only" in attempt.note
    master_copy = _git(d, "show", f"{master}:gen/counts.json").stdout
    assert (Path(d) / "gen/counts.json").read_text() == master_copy
    assert not (Path(d) / "gen/.ran").exists()


def test_regen_rung_generator_failure_resets_tree(tmp_path, key):
    d, pre, master, merge = _regen_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, ("gen/counts.json",), _counts_prove(d))
    registry = (GeneratedFile("gen/counts.json", ("bash", "-c", "exit 3")),)
    out = run_ladder(ctx, "LOST: gen/counts.json: +x\n", (make_regen_rung(registry),))
    assert out.rung == ""
    assert "exit 3" in out.trail[0]
    assert _head(d) == merge
    assert _git(d, "status", "--porcelain").stdout == ""
    assert (Path(d) / "gen/counts.json").read_text() == _counts("acmz")


def test_generated_registry_matches_repo():
    root = Path(subprocess.run(
        ["git", "rev-parse", "--show-toplevel"], cwd=Path(__file__).resolve().parent,
        capture_output=True, text=True, check=True).stdout.strip())
    for g in GENERATED_FILES:
        assert (root / g.path).exists(), g.path
        if g.command is not None:
            assert (root / g.command[0]).exists(), g.command[0]
    assert (root / "ibl5/bin/check-baseline-drift").exists()
    by_path = {g.path: g for g in GENERATED_FILES}
    for copy_only in ("ibl5/docs/schema/current-schema.sql", "ibl5/coverage-baseline.json"):
        assert by_path[copy_only].command is None
        assert by_path[copy_only].why_copy_only


# -- Phase 4: Opus rung ----------------------------------------------------------

_COMPOSE_BASE = (
    "services:\n"
    "  app:\n"
    "    environment:\n"
    "      DB_HOST: db\n"
    "      APP_ENV: ci\n"
    "  db:\n"
    "    image: mariadb\n"
)
_OAUTH = (
    "      GOOGLE_OAUTH_CLIENT_ID: x",
    "      GOOGLE_OAUTH_CLIENT_SECRET: y",
    "      GOOGLE_OAUTH_REDIRECT_URI: z",
)
_COMPOSE_FEAT = _COMPOSE_BASE.replace(
    "      APP_ENV: ci\n", "      APP_ENV: ci\n" + "\n".join(_OAUTH) + "\n")
_COMPOSE_MASTER = _COMPOSE_BASE.replace("APP_ENV: ci", "APP_ENV: ci2")


def _compose_with(lines):
    return _COMPOSE_MASTER.replace(
        "      APP_ENV: ci2\n", "      APP_ENV: ci2\n" + "".join(l + "\n" for l in lines))


_COMPOSE_PROOF = "".join(f"LOST: docker-compose.ci.yml: +{l}\n" for l in _OAUTH)


def _compose_repo(tmp_path):
    d = tmp_path / "compose"
    pre, master, merge = _build_merge_repo(
        d, {"docker-compose.ci.yml": _COMPOSE_BASE},
        {"docker-compose.ci.yml": _COMPOSE_FEAT},
        {"docker-compose.ci.yml": _COMPOSE_MASTER},
        {"docker-compose.ci.yml": _COMPOSE_MASTER})
    return d, pre, master, merge


def _compose_prove(d):
    def prove():
        have = (Path(d) / "docker-compose.ci.yml").read_text().splitlines()
        if all(l in have for l in _OAUTH):
            return True, "TREE-EQUIVALENT\n"
        return False, _COMPOSE_PROOF
    return prove


class _OpusLlm:
    """Shape of `_WritingLlm`: records (purpose, model, prompt), writes a scripted body."""

    def __init__(self, path=None, content=None, reply="RESOLVED", citation=None, key=None):
        self.calls = []
        self.path, self.content, self.reply = path, content, reply
        self.citation, self.key = citation, key

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        self.calls.append((purpose, model, prompt))
        if self.citation is not None:
            stage = Path(f"/tmp/postplan-conflict-stages-{self.key}")
            stage.mkdir(parents=True, exist_ok=True)
            (stage / "citations.txt").write_text(self.citation)
        if self.content is not None:
            (Path(cwd) / self.path).write_text(self.content)
        return self.reply


def test_extract_stages_reads_commit_revs(tmp_path, key):
    d, pre, master, _ = _a_repo(tmp_path)
    base = _git(d, "merge-base", pre, master).stdout.strip()
    g = LiveGit(str(d))
    stages = extract_stages(revs={1: base, 2: pre, 3: master}, run=g._run, key=key,
                            path="a.txt")
    assert stages[1].read_text() == "base\n"
    assert stages[2].read_text() == "feature\nkeep-me\n"
    assert stages[3].read_text() == "master\n"
    for stage, rev in ((1, base), (2, pre), (3, master)):
        assert stages[stage].read_text() == _git(d, "show", f"{rev}:a.txt").stdout


def test_opus_rung_restores_dropped_lines(tmp_path, key):
    d, pre, master, _ = _compose_repo(tmp_path)
    llm = _OpusLlm("docker-compose.ci.yml", _compose_with(_OAUTH))
    ctx = _ctx(d, key, pre, master, ("docker-compose.ci.yml",), _compose_prove(d), llm=llm)
    out = run_ladder(ctx, _COMPOSE_PROOF, (make_opus_rung(),))
    assert out.rung == "opus-retry"
    assert len(llm.calls) == 1
    purpose, model, prompt = llm.calls[0]
    assert model == "opus"
    for l in _OAUTH:
        assert f"LOST: docker-compose.ci.yml: +{l}" in prompt


def test_opus_rung_still_dropping_a_line_fails(tmp_path, key):
    d, pre, master, merge = _compose_repo(tmp_path)
    llm = _OpusLlm("docker-compose.ci.yml", _compose_with(_OAUTH[:2]))
    ctx = _ctx(d, key, pre, master, ("docker-compose.ci.yml",), _compose_prove(d), llm=llm)
    out = run_ladder(ctx, _COMPOSE_PROOF, (make_opus_rung(),))
    assert out.rung == ""
    assert out.trail[0].startswith("opus-retry")
    assert len(llm.calls) == 1
    assert _head(d) == merge


def test_opus_rung_skips_structural_only_losses(tmp_path, key):
    d, pre, master, _ = _compose_repo(tmp_path)
    llm = _OpusLlm()
    ctx = _ctx(d, key, pre, master, ("docker-compose.ci.yml",), _compose_prove(d), llm=llm)
    _, fn = make_opus_rung()
    attempt = fn(ctx, "LOST: file missing at HEAD: x.txt\n")
    assert attempt.applied is False
    assert llm.calls == []


def test_opus_rung_citation_reaches_trail(tmp_path, key):
    d, pre, master, merge = _compose_repo(tmp_path)
    llm = _OpusLlm(
        reply="Master already has them.\nFAILED", key=key,
        citation="CITED: " + _OAUTH[0].strip() + " -> compose/base.yml:12\n")
    ctx = _ctx(d, key, pre, master, ("docker-compose.ci.yml",), _compose_prove(d), llm=llm)
    out = run_ladder(ctx, _COMPOSE_PROOF, (make_opus_rung(),))
    assert out.rung == ""
    assert "compose/base.yml:12" in out.trail[0]
    assert _head(d) == merge


def test_opus_rung_caps_file_count(tmp_path, key):
    n = OPUS_MAX_FILES + 1
    files = {f"src/f{i}.txt": f"content {i}\n" for i in range(n)}
    d = tmp_path / "cap"
    pre, master, _ = _build_merge_repo(
        d, {"a.txt": "base\n", **files}, {"a.txt": "feature\n"}, {"a.txt": "master\n"},
        {"a.txt": "master\n"})
    llm = _OpusLlm()
    ctx = _ctx(d, key, pre, master, ("a.txt",), lambda: (False, ""), llm=llm)
    proof = "".join(f"LOST: src/f{i}.txt: +line\n" for i in range(n))
    _, fn = make_opus_rung()
    attempt = fn(ctx, proof)
    assert attempt.applied is False
    assert llm.calls == []
    assert "OPUS_MAX_FILES" in attempt.note


# -- Phase 5: session rung -------------------------------------------------------

class _SessionLlm(FixtureLlm):
    """FixtureLlm that first runs a side effect, standing in for what the session did."""

    def __init__(self, canned, effect=None):
        super().__init__(UsageLedger(), canned)
        self.effect = effect

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        if self.effect:
            self.effect(cwd)
        return super().call_tooled(purpose, model, prompt, cwd=cwd, **kw)


def _flag_value(argv, flag):
    return argv[argv.index(flag) + 1]


def _session_ladder(tmp_path, key, llm_reply, effect):
    d, pre, master, merge = _a_repo(tmp_path)
    llm = _SessionLlm({"conflict-ladder-session": llm_reply}, effect)
    ctx = _ctx(d, key, pre, master, ("a.txt",), _keepme_prove(d), llm=llm)
    return d, merge, llm, ctx


def _edit_keepme(cwd):
    (Path(cwd) / "a.txt").write_text("master\nkeep-me\n")


def test_session_rung_argv_caps_and_denies(tmp_path, key):
    d, merge, llm, ctx = _session_ladder(tmp_path, key, "RESOLVED", _edit_keepme)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_session_rung("feat"),))
    assert out.rung == "session"
    assert [p for p, _ in llm.tooled_argvs] == ["conflict-ladder-session"]
    argv = llm.tooled_argvs[0][1]
    assert _flag_value(argv, "--model") == MODEL_MAP["opus"]
    assert _flag_value(argv, "--max-turns") == "80"
    assert _flag_value(argv, "--max-budget-usd") == "5.00"
    denied = _flag_value(argv, "--disallowedTools").split(",")
    for entry in ("Bash(git push:*)", "Bash(gh:*)", "WebFetch"):
        assert entry in denied


def test_session_rung_moved_remote_ref_fails(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        _git(cwd, "update-ref", "refs/remotes/origin/feat", "HEAD~1")

    d, merge, llm, ctx = _session_ladder(tmp_path, key, "RESOLVED", effect)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_session_rung("feat"),))
    assert out.rung == ""
    assert "moved refs/remotes/origin" in out.trail[0]
    assert _head(d) == merge


def test_session_rung_commit_is_rejected_by_parents_guard(tmp_path, key):
    def effect(cwd):
        _edit_keepme(cwd)
        _git(cwd, "add", "-A")
        _git(cwd, "commit", "-m", "session committed on its own")

    d, merge, llm, ctx = _session_ladder(tmp_path, key, "RESOLVED", effect)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_session_rung("feat"),))
    assert out.rung == ""
    assert "parents" in out.trail[0]
    assert _head(d) == merge


def test_session_rung_failed_reply_fails(tmp_path, key):
    d, merge, llm, ctx = _session_ladder(
        tmp_path, key, "I could not finish.\nFAILED", _edit_keepme)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_session_rung("feat"),))
    assert out.rung == ""
    assert out.trail[0].startswith("session:")
    assert _head(d) == merge
    assert "keep-me" not in (Path(d) / "a.txt").read_text()


def test_session_rung_decorated_resolved_fails_closed(tmp_path, key):
    d, merge, llm, ctx = _session_ladder(
        tmp_path, key, "All lines restored.\n**RESOLVED**", _edit_keepme)
    out = run_ladder(ctx, _KEEPME_PROOF, (make_session_rung("feat"),))
    assert out.rung == ""
    assert "did not end with RESOLVED" in out.trail[0]
    assert _head(d) == merge
    assert "keep-me" not in (Path(d) / "a.txt").read_text()


# -- Phase 6: RunResult and failure reason --------------------------------------

def test_run_result_omits_conflict_rung_when_unset():
    data = json.loads(RunResult(terminal=TerminalState.SHIPPED_HELD).to_json())
    assert "conflict_rung" not in data


def test_run_result_writes_conflict_rung_when_set():
    data = json.loads(RunResult(terminal=TerminalState.SHIPPED_HELD,
                                conflict_rung="regen").to_json())
    assert data["conflict_rung"] == "regen"


def test_ladder_failure_reason_keeps_proof_prefix():
    proof = "".join(f"LOST: f.txt: +line {i} padding padding\n" for i in range(30))
    trail = ("regen: skipped (no generated file involved)", "opus-retry: boom")
    reason = ladder_failure_reason(proof, trail)
    assert reason.startswith("tree proof failed: " + proof.strip()[:400])
    assert "; ".join(trail) in reason
    old = f"tree proof failed: {proof.strip()[:400]}"

    def classify(r):
        return classify_rebase_block(
            ("f.txt",), r, head_sha="h", branch="feat",
            run_git=lambda args: (0, ""), run_gh=lambda args: (0, ""))

    assert classify(reason).cause == classify(old).cause == CAUSE_TREE_PROOF
