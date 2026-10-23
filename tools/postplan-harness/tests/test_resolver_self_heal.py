"""Real conflict shapes the stacked resolver failed on, replayed with the real lost-work proof.

Each shape under tests/fixtures/resolver_self_heal/ holds the real file versions from one
2026-10-21/22 audit-log failure (SOURCE.txt names the SHAs). `_make_shape_repo` builds a temp
repo from them with the REAL `.claude/review-shared/scripts/lostwork.sh`, so a stub proof
cannot make a test pass. The replay tests pin the fixed behavior for each shape.
"""
import os
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

_REPO_ROOT = os.path.dirname(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
)
_LOSTWORK = os.path.join(
    _REPO_ROOT, ".claude", "review-shared", "scripts", "lostwork.sh"
)
_COLLAPSE = os.path.join(
    _REPO_ROOT, ".claude", "review-shared", "scripts", "collapse-guard.sh"
)
_FIXTURES = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "fixtures", "resolver_self_heal"
)

F1 = "f1_linear_midreplay"
F2 = "f2_lost_frontmatter"
F3 = "f3_declined"

# path: the conflicted file's repo path. commits: the default branch-only commits; a str is a
# fixture name written to `path`, a (relpath, text) tuple is an unrelated commit.
_SHAPES = {
    F1: {
        "path": ".claude/skills/post-plan/SKILL.md",
        "commits": ["c1", "c2", ("docs/unrelated.txt", "unrelated\n")],
    },
    F2: {
        "path": ".claude/skills/post-plan/_pr-body-claims.md",
        "commits": ["branch"],
    },
    F3: {
        "path": ".claude/skills/post-plan/_phase-4-review-audit.md",
        "commits": ["branch"],
    },
}


def _sh(d, *args, check=True):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t",
        "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
        "GIT_EDITOR": "true",
    }
    return subprocess.run(
        ["git", "-C", d, *args], check=check, capture_output=True, text=True, env=env
    )


def _rev(d, ref):
    return subprocess.run(
        ["git", "-C", d, "rev-parse", ref],
        check=True, capture_output=True, text=True,
    ).stdout.strip()


def _cleanup_tmp(key):
    """Remove /tmp files written by the resolver and the proof scripts."""
    patterns = [
        f"/tmp/postplan-conflict-files-{key}.txt",
        f"/tmp/postplan-conflict-files-{key}-autoresolved.txt",
        f"/tmp/postplan-conflict-resolution-{key}.md",
        f"/tmp/postplan-lostwork-{key}.sh",
        f"/tmp/postplan-collapse-guard-{key}.sh",
        f"/tmp/pr-ready-diff-pre-{key}.patch",
        f"/tmp/pr-ready-diff-post-{key}.patch",
        f"/tmp/pr-ready-numstat-pre-{key}.txt",
        f"/tmp/pr-ready-numstat-post-{key}.txt",
        f"/tmp/pr-ready-collapse-guard-{key}-{key}.meta",
        f"/tmp/postplan-conflict-sha-{key}.txt",
    ]
    for p in patterns:
        if os.path.exists(p):
            os.unlink(p)
    for dpath in [
        f"/tmp/postplan-conflict-review-{key}",
        f"/tmp/postplan-conflict-stages-{key}",
    ]:
        if os.path.exists(dpath):
            shutil.rmtree(dpath, ignore_errors=True)


def _cleanup_full(key, branch, d):
    """Standard /tmp files, verdict artifacts, the conflict flag, and the temp repo."""
    from harness.armable import conflict_flag_path
    from harness.conflict import purge_verdict_artifacts

    _cleanup_tmp(key)
    purge_verdict_artifacts(key)
    flag = conflict_flag_path(branch)
    if os.path.exists(flag):
        os.unlink(flag)
    if d and os.path.exists(d):
        shutil.rmtree(d, ignore_errors=True)


@pytest.fixture(autouse=True)
def _no_editor(monkeypatch):
    # A conflicted-merge commit or `rebase --continue` can otherwise block on an editor.
    monkeypatch.setenv("GIT_EDITOR", "true")


def _fx(shape, name):
    """Fixture bytes, byte-exact. `name` is a stem: `base`, `master`, `resolved`, ..."""
    with open(os.path.join(_FIXTURES, shape, f"{name}.txt"), "rb") as fh:
        return fh.read()


def _write_bytes(d, relpath, data):
    full = os.path.join(d, relpath)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "wb") as fh:
        fh.write(data)


def _make_shape_repo(shape, commits=None):
    """Temp repo for one fixture shape, with the REAL lostwork.sh and collapse-guard.sh.

    `base` is the first commit (fixture `base` at the shape's path plus the proof scripts).
    `master` adds fixture `master` and becomes origin/master. The branch forks from base and
    carries `commits` (default: the shape's own list) replayed in order; each entry is either a
    fixture stem written to the shape's path, or a `(relpath, text)` tuple for an unrelated
    commit. `branch.<name>.iblBase` is the base SHA, which `autoresolve_stacked_rebase` needs.

    Returns (d, branch, key, base_sha, master_sha, tip); the branch is checked out at `tip`.
    """
    path = _SHAPES[shape]["path"]
    plan = list(_SHAPES[shape]["commits"] if commits is None else commits)
    branch = f"feature-rsh-{uuid.uuid4().hex[:8]}"
    key = branch

    d = tempfile.mkdtemp(prefix="postplan-rsh-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    _write_bytes(d, path, _fx(shape, "base"))
    scripts_dir = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    base_sha = _rev(d, "HEAD")

    _sh(d, "checkout", "-b", branch)
    for i, item in enumerate(plan, 1):
        if isinstance(item, tuple):
            relpath, text = item
            _write_bytes(d, relpath, text.encode())
            msg = f"branch commit {i}: {relpath}"
        else:
            _write_bytes(d, path, _fx(shape, item))
            msg = f"branch commit {i}: {item}"
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", msg)
    tip = _rev(d, "HEAD")

    _sh(d, "checkout", "master")
    _write_bytes(d, path, _fx(shape, "master"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "master change")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "checkout", branch)
    _sh(d, "config", f"branch.{branch}.iblBase", base_sha)
    return d, branch, key, base_sha, master_sha, tip


def _assert_real_proof(d, proof_path=_LOSTWORK):
    """origin/master's lostwork.sh in `d` must be byte-identical to the repo's real one, so no
    replay test can pass on a stub proof."""
    shown = subprocess.run(
        ["git", "-C", d, "show", "origin/master:.claude/review-shared/scripts/lostwork.sh"],
        check=True, capture_output=True,
    ).stdout
    with open(proof_path, "rb") as fh:
        real = fh.read()
    assert shown == real, "temp repo's lostwork.sh is not the real proof script"


class _ScriptedLlm:
    """Fake LLM that dispatches `conflict-resolve:<path>` calls on `(path, model)`.

    `script` maps `(path, model)` to a list of `(fixture_stem_or_None, reply)` actions consumed
    in order, or to a callable `(path, model, n)` (n counts calls for that pair from 0) that
    returns one such action. An action writes the fixture (when not None) to `cwd/path` and
    returns `reply`. An unscripted pair, or a list run past its end, raises AssertionError so
    an unexpected extra model call fails the test. `conflict-review` calls return
    `review_reply`; any other purpose raises. `calls` holds (label, model, prompt) per call.
    """

    def __init__(self, shape, script, *, review_reply="CONFLICT-REVIEW=CLEAN\n"):
        self.shape = shape
        self.script = script
        self.review_reply = review_reply
        self.calls: list[tuple[str, str, str]] = []
        self._counts: dict[tuple[str, str], int] = {}

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append((purpose, model, prompt))
        if purpose == "conflict-review":
            return self.review_reply
        if not purpose.startswith("conflict-resolve:"):
            raise AssertionError(f"unscripted call: {purpose!r} on {model!r}")
        path = purpose.split(":", 1)[1]
        pair = (path, model)
        if pair not in self.script:
            raise AssertionError(f"unscripted call for {pair!r}")
        n = self._counts.get(pair, 0)
        self._counts[pair] = n + 1
        entry = self.script[pair]
        if callable(entry):
            fixture, reply = entry(path, model, n)
        else:
            if n >= len(entry):
                raise AssertionError(f"script for {pair!r} exhausted at call {n}")
            fixture, reply = entry[n]
        if fixture is not None:
            _write_bytes(cwd, path, _fx(self.shape, fixture))
        return reply

    def resolve_calls(self):
        return [c for c in self.calls if c[0].startswith("conflict-resolve:")]


def _first_plus_line(d, a, b, path):
    out = _sh(d, "diff", f"{a}..{b}", "--", path).stdout
    return next(l for l in out.splitlines() if l.startswith("+") and not l.startswith("+++"))


def _models(llm):
    return [c[1] for c in llm.resolve_calls()]


def test_f1_linear_replay_squashes_once_and_proves():
    shape = F1
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): [("resolved", "RESOLVED")]})
    try:
        _assert_real_proof(d)
        assert _sh(d, "rev-list", "--merges", "--count", f"{master_sha}..{branch}").stdout.strip() == "0"
        result = LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert _sh(d, "rev-list", "--count", f"{master_sha}..HEAD").stdout.strip() == "1"
        assert _rev(d, "HEAD^") == master_sha
        assert len(llm.resolve_calls()) == 1
        assert result.squash_note.startswith("squashed 3 commits (0 merges)"), result.squash_note
        _assert_real_proof(d)
    finally:
        _cleanup_full(key, branch, d)


def test_linear_squash_decline_restores_pre_squash_head():
    shape = F1
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): lambda p, m, n: (None, "FAILED"),
                               (path, "opus"): lambda p, m, n: (None, "FAILED")})
    try:
        _assert_real_proof(d)
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.kind == "rebase-conflict"
        assert exc.value.detail.startswith(f"resolver declined: {path}")
        assert _rev(d, f"refs/heads/{branch}") == tip
        assert _sh(d, "rev-list", "--count", f"{base_sha}..{branch}").stdout.strip() == "3"
        assert _sh(d, "status", "--porcelain").stdout == ""
    finally:
        _cleanup_full(key, branch, d)


def test_f2_lost_line_fed_to_opus_rung_then_proves():
    shape = F2
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): [("bad", "RESOLVED")],
                               (path, "opus"): [("resolved", "RESOLVED")]})
    try:
        _assert_real_proof(d)
        result = LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        _assert_real_proof(d)
        assert _models(llm) == ["sonnet", "opus"]
        opus_prompt = llm.resolve_calls()[1][2]
        assert '+description: "PR body authoring rules:' in opus_prompt
        parents = _sh(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]
        assert parents == [master_sha]
    finally:
        _cleanup_full(key, branch, d)


def test_f2_still_dropped_after_retry_is_rejected():
    from harness.rebase_cause import CAUSE_TREE_PROOF, classify_rebase_block

    shape = F2
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): [("bad", "RESOLVED")],
                               (path, "opus"): [("bad", "RESOLVED")]})
    try:
        _assert_real_proof(d)
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        detail = exc.value.detail
        assert exc.value.kind == "rebase-conflict"
        assert detail.startswith("tree proof failed: "), detail
        assert f"LOST: {path}: +description:" in detail
        assert " | ladder: adaptation rejected: " in detail, detail
        assert "; opus-retry: rung made no change" in detail, detail
        assert _rev(d, f"refs/heads/{branch}") == tip
        assert _models(llm).count("opus") == 1

        def boom(*a, **kw):
            raise RuntimeError("seam down")

        got = classify_rebase_block((path,), detail, head_sha=tip, branch=branch,
                                    run_git=boom, run_gh=boom)
        assert got.cause == CAUSE_TREE_PROOF
    finally:
        _cleanup_full(key, branch, d)


_F3_SONNET = "the branch rewrites the section master also rewrote; I cannot merge both"


def test_f3_declined_file_resolves_on_opus_retry():
    shape = F3
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): [(None, _F3_SONNET + "\nFAILED")],
                               (path, "opus"): [("resolved", "RESOLVED")]})
    try:
        _assert_real_proof(d)
        branch_plus = _first_plus_line(d, base_sha, tip, path)
        master_plus = _first_plus_line(d, base_sha, master_sha, path)
        result = LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        _assert_real_proof(d)
        assert _models(llm) == ["sonnet", "opus"]
        opus_prompt = llm.resolve_calls()[1][2]
        assert _F3_SONNET in opus_prompt
        assert branch_plus in opus_prompt
        assert master_plus in opus_prompt
    finally:
        _cleanup_full(key, branch, d)


def test_f3_double_decline_names_model_reason():
    shape = F3
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): [(None, _F3_SONNET + "\nFAILED")],
                               (path, "opus"): [(None, "opus: sections contradict\nFAILED")]})
    try:
        _assert_real_proof(d)
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.kind == "rebase-conflict"
        assert exc.value.detail == f"resolver declined: {path} (model: opus: sections contradict)"
        assert _rev(d, f"refs/heads/{branch}") == tip
    finally:
        _cleanup_full(key, branch, d)


@pytest.mark.parametrize("shape,commits", [
    (F1, ["c2"]),  # one commit carrying the net change the three F1 commits make
    (F2, ["branch"]),
    (F3, ["branch"]),
])
def test_fixture_resolved_passes_real_proof(shape, commits):
    """`resolved.txt` is a faithful resolution: the real proof accepts it."""
    path = _SHAPES[shape]["path"]
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(shape, commits=commits)
    llm = _ScriptedLlm(shape, {(path, "sonnet"): [("resolved", "RESOLVED")]})
    try:
        _assert_real_proof(d)
        result = LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
    finally:
        _cleanup_full(key, branch, d)


def test_f2_bad_fixture_differs_from_resolved_only_in_description():
    resolved = _fx(F2, "resolved").decode().splitlines()
    bad = _fx(F2, "bad").decode().splitlines()
    assert len(resolved) == len(bad)
    diff = [(a, b) for a, b in zip(resolved, bad) if a != b]
    assert len(diff) == 1
    assert diff[0][0].startswith("description:") and diff[0][1].startswith("description:")


def test_assert_real_proof_rejects_a_changed_proof(tmp_path):
    d, branch, key, base_sha, master_sha, tip = _make_shape_repo(F2)
    try:
        _assert_real_proof(d)
        with open(_LOSTWORK, "rb") as fh:
            mutated = bytearray(fh.read())
        mutated[-1] ^= 0x01
        fake = tmp_path / "lostwork.sh"
        fake.write_bytes(bytes(mutated))
        with pytest.raises(AssertionError):
            _assert_real_proof(d, proof_path=str(fake))
    finally:
        _cleanup_full(key, branch, d)


def test_scripted_llm_rejects_unscripted_pair():
    llm = _ScriptedLlm(F3, {})
    with pytest.raises(AssertionError):
        llm.call_tooled("conflict-resolve:x.md", "opus", "p", cwd="/tmp",
                        allowed_tools=("Read", "Write"))
    assert llm.calls == [("conflict-resolve:x.md", "opus", "p")]


# --- Synthetic multi-file repos for the per-run cost bounds ------------------------------


def _syn_names(n):
    return [f"docs/f{i}.md" for i in range(1, n + 1)]


def _syn_master(name):
    return f"a\nmaster {name}\nc\n"


def _syn_union(name):
    return f"a\nbranch {name}\nmaster {name}\nc\n"


def _make_multi_repo(n):
    """One branch commit editing line 2 of `n` files; master edits the same line differently.

    Returns (d, branch, key, base_sha, master_sha, tip)."""
    names = _syn_names(n)
    branch = f"feature-rsh-{uuid.uuid4().hex[:8]}"
    d = tempfile.mkdtemp(prefix="postplan-rsh-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    for name in names:
        _write_bytes(d, name, b"a\nb\nc\n")
    scripts_dir = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    base_sha = _rev(d, "HEAD")
    _sh(d, "checkout", "-b", branch)
    for name in names:
        _write_bytes(d, name, f"a\nbranch {name}\nc\n".encode())
    # A branch-only file keeps the post-side diff non-empty when every conflict ends as master's copy.
    _write_bytes(d, "docs/branch-only.md", b"branch only\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "branch edits")
    tip = _rev(d, "HEAD")
    _sh(d, "checkout", "master")
    for name in names:
        _write_bytes(d, name, _syn_master(name).encode())
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "master edits")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "checkout", branch)
    _sh(d, "config", f"branch.{branch}.iblBase", base_sha)
    return d, branch, branch, base_sha, master_sha, tip


def _writer(d, content_for, reply="RESOLVED"):
    def act(path, model, n):
        _write_bytes(d, path, content_for(path).encode())
        return None, reply
    return act


def _decline(path, model, n):
    return None, "FAILED"


def test_decline_retries_capped_per_run():
    from harness.conflict import MAX_DECLINE_RETRIES

    d, branch, key, base_sha, master_sha, tip = _make_multi_repo(4)
    names = _syn_names(4)
    script = {}
    for name in names:
        script[(name, "sonnet")] = _decline
        script[(name, "opus")] = _writer(d, _syn_union)
    llm = _ScriptedLlm(F3, script)
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.detail.startswith(f"resolver declined: {names[2]}"), exc.value.detail
        assert _models(llm).count("opus") == 2 == MAX_DECLINE_RETRIES
        assert _models(llm).count("sonnet") == 3
        assert _rev(d, f"refs/heads/{branch}") == tip
    finally:
        _cleanup_full(key, branch, d)


def test_decline_retry_budget_shared_across_plain_and_stacked():
    d, branch, key, base_sha, master_sha, tip = _make_multi_repo(2)
    first, second = _syn_names(2)
    llm = _ScriptedLlm(F3, {
        (first, "sonnet"): _decline,
        (second, "sonnet"): _decline,
        (first, "opus"): _writer(d, _syn_union),
        (second, "opus"): _decline,
    })
    try:
        g = LiveGit(d, llm=llm)
        with pytest.raises(HarnessError) as plain:
            g.rebase_onto()
        assert plain.value.kind == "rebase-conflict"
        assert _models(llm).count("opus") == 2
        assert _rev(d, f"refs/heads/{branch}") == tip
        with pytest.raises(HarnessError) as stacked:
            g.autoresolve_stacked_rebase()
        assert stacked.value.detail.startswith(f"resolver declined: {first}"), stacked.value.detail
        assert _models(llm).count("opus") == 2
        assert _rev(d, f"refs/heads/{branch}") == tip
    finally:
        _cleanup_full(key, branch, d)


def test_decline_retry_budget_survives_a_second_rebase_onto():
    """runner._refresh_and_reprove calls rebase_onto again on one LiveGit; the cap is per run."""
    from harness.conflict import MAX_DECLINE_RETRIES

    d, branch, key, base_sha, master_sha, tip = _make_multi_repo(4)
    names = _syn_names(4)
    script = {}
    for name in names:
        script[(name, "sonnet")] = _decline
        script[(name, "opus")] = _writer(d, _syn_union)
    llm = _ScriptedLlm(F3, script)
    try:
        g = LiveGit(d, llm=llm)
        for _ in range(2):
            with pytest.raises(HarnessError) as exc:
                g.rebase_onto()
            assert exc.value.kind == "rebase-conflict"
            assert _rev(d, f"refs/heads/{branch}") == tip
        assert _models(llm).count("opus") == 2 == MAX_DECLINE_RETRIES
    finally:
        _cleanup_full(key, branch, d)


def test_lost_rung_skips_over_file_cap():
    from harness.conflict_ladder import OPUS_MAX_FILES

    d, branch, key, base_sha, master_sha, tip = _make_multi_repo(6)
    script = {(name, "sonnet"): _writer(d, _syn_master) for name in _syn_names(6)}
    llm = _ScriptedLlm(F3, script)
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.detail.startswith("tree proof failed: "), exc.value.detail
        assert f"OPUS_MAX_FILES={OPUS_MAX_FILES}" in exc.value.detail, exc.value.detail
        assert _models(llm).count("opus") == 0
        assert _models(llm).count("sonnet") == 6
        assert _rev(d, f"refs/heads/{branch}") == tip
    finally:
        _cleanup_full(key, branch, d)


def test_total_extra_opus_calls_bounded_per_run():
    from harness.conflict import MAX_DECLINE_RETRIES
    from harness.conflict_ladder import OPUS_MAX_FILES

    d, branch, key, base_sha, master_sha, tip = _make_multi_repo(5)
    names = _syn_names(5)
    script = {}
    for i, name in enumerate(names):
        script[(name, "sonnet")] = _decline if i < 2 else _writer(d, _syn_master)
        script[(name, "opus")] = _writer(d, _syn_master)
    llm = _ScriptedLlm(F3, script)
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.detail.startswith("tree proof failed: "), exc.value.detail
        assert _models(llm).count("opus") == 7 == MAX_DECLINE_RETRIES + OPUS_MAX_FILES, exc.value.detail
        assert _models(llm).count("sonnet") == 5
        # Only resolver calls ran: no session, sonnet-fix, or other model call.
        assert all(c[0].startswith("conflict-resolve:") for c in llm.calls), \
            sorted({c[0] for c in llm.calls})
        assert _rev(d, f"refs/heads/{branch}") == tip
    finally:
        _cleanup_full(key, branch, d)
