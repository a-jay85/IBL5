"""Real-git replay of the #2438 and #2593 conflict shapes through the escalation ladder.

Every scenario runs the real `LiveGit._rebase_onto` and the real, unchanged
`.claude/review-shared/scripts/lostwork.sh`, copied byte-for-byte into the fixture repo's
master commit. Only the model is faked (`_ScriptedLlm`). Nothing here stubs the proof.
"""
from __future__ import annotations

import glob
import json
import os
import shutil
import subprocess
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import conflict_ladder
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import LiveGit
from harness.adapters.llm import UsageLedger, _tooled_argv
from harness.conflict_ladder import (
    GeneratedFile,
    make_opus_rung,
    make_regen_rung,
    make_session_rung,
)
from harness.state import HarnessError, TerminalState

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
_LOSTWORK_REL = os.path.join(".claude", "review-shared", "scripts", "lostwork.sh")
_CLEAN_REVIEW = "CONFLICT-REVIEW=CLEAN\n"


def _real_lostwork_path() -> str:
    top = subprocess.run(
        ["git", "-C", os.path.dirname(os.path.abspath(__file__)), "rev-parse", "--show-toplevel"],
        check=True, capture_output=True, text=True,
    ).stdout.strip()
    return os.path.join(top, _LOSTWORK_REL)


# --- git helpers ---------------------------------------------------------------------

def _git(d, *args, check=True):
    return subprocess.run(["git", "-C", str(d), *args], check=check,
                          capture_output=True, text=True, env=_ENV)


def _rev(d, ref):
    return _git(d, "rev-parse", ref).stdout.strip()


def _write(d, rel, text):
    path = os.path.join(str(d), rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w") as fh:
        fh.write(text)


def _commit_all(d, msg):
    _git(d, "add", "-A")
    _git(d, "commit", "-q", "-m", msg)


def _show(d, ref_path):
    return _git(d, "show", ref_path).stdout


def _parents(d):
    return _git(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]


def _has_merge_head(d):
    return _git(d, "rev-parse", "-q", "--verify", "MERGE_HEAD", check=False).returncode == 0


# --- fake model ----------------------------------------------------------------------

class _ScriptedLlm:
    """Answers `call_tooled` from a dict of callables keyed by `(purpose, model)` or by
    `purpose` alone. A callable takes `(cwd, prompt)`, may write files under `cwd`, and
    returns the reply text. Every call is recorded as `(purpose, model, prompt)`.

    `first_pass_calls` holds the `conflict-resolve:*` calls made before the ladder runs,
    `ladder_calls` everything after. `first_pass_done` flips once the last first-pass
    `conflict-resolve:*` reply has gone out. `conflict-review` is answered with the clean
    verdict and kept out of both lists: `_record_resolution` runs it after any success."""

    def __init__(self, handlers, *, first_pass_resolves=1):
        self.ledger = UsageLedger()
        self.handlers = handlers
        self.calls: list[tuple[str, str, str]] = []
        self.first_pass_calls: list[tuple[str, str, str]] = []
        self.ladder_calls: list[tuple[str, str, str]] = []
        self.tooled_argvs: list[tuple[str, list[str]]] = []
        self.first_pass_done = False
        self._first_pass_left = first_pass_resolves

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools=(), denied_tools=(),
                    add_dirs=(), agent=None, append_system_prompt=None,
                    setting_sources="user,project", max_turns=None, max_budget_usd=None, **_):
        record = (purpose, model, prompt)
        self.calls.append(record)
        self.tooled_argvs.append((purpose, _tooled_argv(
            model, agent=agent, allowed_tools=allowed_tools, denied_tools=denied_tools,
            add_dirs=add_dirs, append_system_prompt=append_system_prompt,
            setting_sources=setting_sources, max_turns=max_turns,
            max_budget_usd=max_budget_usd)))
        if purpose == "conflict-review":
            return _CLEAN_REVIEW
        handler = self.handlers.get((purpose, model)) or self.handlers.get(purpose)
        if handler is None:
            raise HarnessError("llm-fixture-missing", f"{purpose} ({model})")
        first_pass = purpose.startswith("conflict-resolve:") and not self.first_pass_done
        (self.first_pass_calls if first_pass else self.ladder_calls).append(record)
        reply = handler(cwd, prompt)
        if first_pass:
            self._first_pass_left -= 1
            if self._first_pass_left <= 0:
                self.first_pass_done = True
        return reply

    def ladder_models(self):
        return [m for _p, m, _pr in self.ladder_calls]

    def ladder_purposes(self):
        return [p for p, _m, _pr in self.ladder_calls]


def _writer(rel, content, reply="RESOLVED"):
    def fn(cwd, prompt):
        _write(cwd, rel, content)
        return reply
    return fn


# --- fixture repos -------------------------------------------------------------------

@pytest.fixture
def make_repo(request, tmp_path):
    """Factory for a repo with a bare `origin`, an `origin/master` tracking ref, and the real
    lostwork.sh committed on master. The branch is named `ladder-<test name>`, so the
    `/tmp/...-<key>...` scratch paths never collide across tests."""
    key = f"ladder-{request.node.name}"
    made = []

    def build(base, branch, master):
        origin, wt = tmp_path / "origin.git", tmp_path / "wt"
        subprocess.run(["git", "init", "-q", "--bare", "-b", "master", str(origin)],
                       check=True, capture_output=True)
        subprocess.run(["git", "init", "-q", "-b", "master", str(wt)],
                       check=True, capture_output=True)
        for k, v in (("user.email", "t@t"), ("user.name", "t"),
                     ("commit.gpgsign", "false"), ("core.hooksPath", "/dev/null")):
            _git(wt, "config", k, v)
        _git(wt, "remote", "add", "origin", str(origin))
        for rel, text in base.items():
            _write(wt, rel, text)
        dst = os.path.join(str(wt), _LOSTWORK_REL)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copyfile(_real_lostwork_path(), dst)
        _commit_all(wt, "base")
        _git(wt, "push", "-q", "origin", "master")
        _git(wt, "checkout", "-q", "-b", key)
        for rel, text in branch.items():
            _write(wt, rel, text)
        _commit_all(wt, "feat: branch work")
        pre = _rev(wt, "HEAD")
        _git(wt, "checkout", "-q", "master")
        for rel, text in master.items():
            _write(wt, rel, text)
        _commit_all(wt, "master moves")
        _git(wt, "push", "-q", "origin", "master")
        master_sha = _rev(wt, "origin/master")
        _git(wt, "checkout", "-q", key)
        fx = SimpleNamespace(d=str(wt), key=key, pre=pre, master=master_sha,
                             out=str(tmp_path / "out"))
        made.append(fx)
        return fx

    yield build

    for path in glob.glob(f"/tmp/*{key}*"):
        if os.path.isdir(path):
            shutil.rmtree(path, ignore_errors=True)
        else:
            try:
                os.remove(path)
            except OSError:
                pass


# generated-file shape (#2438)

_GEN_SCRIPT = (
    "#!/usr/bin/env bash\n"
    "set -euo pipefail\n"
    'cd "$(dirname "$0")/.."\n'
    "{\n"
    "  echo '{'\n"
    "  LC_ALL=C sort src/ids.txt | sed 's/.*/  \"&\": 1,/'\n"
    "  echo '  \"end\": 0'\n"
    "  echo '}'\n"
    "} > gen/counts.json\n"
)


def _counts(ids):
    body = "".join(f'  "{i}": 1,\n' for i in sorted(ids))
    return "{\n" + body + '  "end": 0\n}\n'


def _gen_repo(make_repo):
    base_ids = ["id-a", "id-z"]
    base = {"src/ids.txt": "\n".join(base_ids) + "\n",
            "gen/make-counts.sh": _GEN_SCRIPT,
            "gen/counts.json": _counts(base_ids)}
    branch = {"src/ids.txt": "id-m1\n" + "\n".join(base_ids) + "\n",
              "gen/counts.json": _counts(base_ids + ["id-m1"])}
    master = {"src/ids.txt": "\n".join(base_ids) + "\nid-m2\n",
              "gen/counts.json": _counts(base_ids + ["id-m2"])}
    fx = make_repo(base, branch, master)
    fx.all_ids = base_ids + ["id-m1", "id-m2"]
    fx.master_counts = _counts(base_ids + ["id-m2"])
    return fx


# docker-compose shape (#2593)

_COMPOSE = "docker-compose.ci.yml"
_COMPOSE_BASE = (
    "services:\n"
    "  app:\n"
    "    image: app:latest\n"
    "    environment:\n"
    "      APP_ENV: ci\n"
    "      DB_HOST: db\n"
    '      DB_PORT: "3306"\n'
    "    ports:\n"
    '      - "8080:80"\n'
)
_COMPOSE_MASTER = _COMPOSE_BASE.replace(
    "      DB_HOST: db\n", "      DB_HOST: db-ci\n").replace('"3306"', '"3307"')


def _oauth_lines():
    pad = "oauth client id passed through to the ci stack for the google login tests " * 2
    return [f"      GOOGLE_OAUTH_CLIENT_ID_{n}: ${{GOOGLE_OAUTH_CLIENT_ID_{n}}}  # {pad}".rstrip()
            for n in (1, 2, 3)]


def _compose_with(base_text, lines):
    marker = "      DB_PORT:"
    head, tail = base_text.split(marker, 1)
    return head + "\n".join(lines) + "\n" + marker + tail


def _compose_repo(make_repo):
    lines = _oauth_lines()
    assert sum(len(l) for l in lines) > 400
    branch_text = _compose_with(_COMPOSE_BASE, lines)
    # The branch also carries an unrelated file so its diff against master stays non-empty
    # once the first pass has dropped the compose lines.
    fx = make_repo({_COMPOSE: _COMPOSE_BASE},
                   {_COMPOSE: branch_text, "notes.txt": "branch notes\n"},
                   {_COMPOSE: _COMPOSE_MASTER})
    fx.lines = lines
    fx.full = _compose_with(_COMPOSE_MASTER, lines)
    fx.partial = _compose_with(_COMPOSE_MASTER, lines[:2])
    fx.dropped = _COMPOSE_MASTER
    return fx


def _compose_llm(fx, *, opus, session=None):
    handlers = {(f"conflict-resolve:{_COMPOSE}", "sonnet"): _writer(_COMPOSE, fx.dropped),
                (f"conflict-resolve:{_COMPOSE}", "opus"): opus}
    if session is not None:
        handlers[("conflict-ladder-session", "opus")] = session
    return _ScriptedLlm(handlers)


# --- runner.run driver ---------------------------------------------------------------

def _drive_runner(fx, llm, monkeypatch, tmp_path):
    """`runner.run` live against the fixture repo with only the model seams stubbed. The
    run stops right after the merge step so the test sees `result.json` without PR work."""
    monkeypatch.setattr(runner, "_pr_copy", lambda *a, **k: ({
        "type": "chore", "title": "chore: ladder", "commit_subject": "chore: ladder",
        "summary_md": "## Summary\n- x\n"}, False))
    monkeypatch.setattr(runner, "_body_check", lambda *a, **k: ({}, False))
    monkeypatch.setattr(runner, "LiveGh", lambda out_dir, worktree, slug: RecordingGh(out_dir))

    # The fixture branch is already committed, so the commit step has nothing to do and the
    # repo has no bin/ gate scripts to run.
    monkeypatch.setattr(runner, "_commit_with_adr_draft", lambda *a, **k: None)
    monkeypatch.setattr(runner, "_commit_with_gate_fix", lambda git, *a, **k: git.head())

    def stop_after_merge(*a, **k):
        raise HarnessError("test-stop", "stopped after the merge step")

    monkeypatch.setattr(runner, "run_meta_checks_local", stop_after_merge)
    (tmp_path / "plans").mkdir(exist_ok=True)
    return runner.run(
        None, fx.out, llm, mode="live", live=True, worktree=fx.d,
        plans_dir=str(tmp_path / "plans"), state_dir=str(tmp_path / "state"))


def _result_json(fx):
    with open(os.path.join(fx.out, "result.json")) as fh:
        return json.load(fh)


# --- scenarios -----------------------------------------------------------------------

def test_live_generated_conflict_resolved_by_regen_without_llm(
        make_repo, monkeypatch, tmp_path):
    """#2438 shape: a hand-merged generated file loses the branch's id; rung 1 regenerates it."""
    fx = _gen_repo(make_repo)
    llm = _ScriptedLlm({
        "conflict-resolve:gen/counts.json": _writer("gen/counts.json", fx.master_counts),
    })
    registry = (GeneratedFile("gen/counts.json", ("bash", "gen/make-counts.sh")),)
    monkeypatch.setattr(
        conflict_ladder, "default_rungs",
        lambda branch: (make_regen_rung(registry=registry), make_opus_rung(),
                        make_session_rung(branch)))

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "test-stop", res.error
    assert [p for p, _m, _pr in llm.first_pass_calls] == ["conflict-resolve:gen/counts.json"]
    assert llm.ladder_calls == []
    assert _result_json(fx)["conflict_rung"] == "regen"
    assert _parents(fx.d) == [fx.pre, fx.master]
    assert _show(fx.d, "HEAD:gen/counts.json") == _counts(fx.all_ids)


def test_live_dropped_lines_restored_by_opus_retry(make_repo):
    """#2593 shape: the first pass drops three long lines; the Opus retry restores them."""
    fx = _compose_repo(make_repo)
    llm = _compose_llm(fx, opus=_writer(_COMPOSE, fx.full))
    git = LiveGit(fx.d, llm=llm)

    git.rebase_onto()

    assert git.last_conflict_rung == "opus-retry"
    assert [(p, m) for p, m, _pr in llm.ladder_calls] == [
        (f"conflict-resolve:{_COMPOSE}", "opus")]
    prompt = llm.ladder_calls[0][2]
    for line in fx.lines:
        assert f"LOST: {_COMPOSE}: +{line}" in prompt
    head = _show(fx.d, f"HEAD:{_COMPOSE}")
    assert head == fx.full
    assert "DB_HOST: db-ci" in head
    assert _parents(fx.d) == [fx.pre, fx.master]


def test_live_dropped_lines_restored_by_session(make_repo):
    """The Opus retry restores two of three lines; the capped session restores the rest."""
    fx = _compose_repo(make_repo)
    llm = _compose_llm(fx, opus=_writer(_COMPOSE, fx.partial),
                       session=_writer(_COMPOSE, fx.full))
    git = LiveGit(fx.d, llm=llm)

    git.rebase_onto()

    assert git.last_conflict_rung == "session"
    assert llm.ladder_models() == ["opus", "opus"]
    assert llm.ladder_purposes() == [
        f"conflict-resolve:{_COMPOSE}", "conflict-ladder-session"]
    argv = dict(llm.tooled_argvs)["conflict-ladder-session"]
    assert argv[argv.index("--max-budget-usd") + 1] == "5.00"
    assert _show(fx.d, f"HEAD:{_COMPOSE}") == fx.full
    assert _parents(fx.d) == [fx.pre, fx.master]


def test_live_all_rungs_drop_a_line_fails_closed_with_dm_block(
        make_repo, monkeypatch, tmp_path):
    """Every rung still drops a line: the run fails closed, restores the tree and writes the DM block."""
    fx = _compose_repo(make_repo)
    llm = _compose_llm(fx, opus=_writer(_COMPOSE, fx.partial),
                       session=_writer(_COMPOSE, fx.partial))

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "rebase-conflict"
    assert res.error.startswith("rebase-conflict: tree proof failed: "), res.error
    assert "ladder:" in res.error
    assert "opus-retry" in res.error
    assert "session" in res.error
    assert _result_json(fx)["conflict_rung"] == "exhausted"

    rc = runner.exit_code_for(res)
    assert rc == 3
    runner.write_blocked_ship(fx.out, res, rc, fx.d)
    block_path = os.path.join(fx.out, runner.BLOCKED_SHIP_FILE)
    assert os.path.exists(block_path)
    with open(block_path) as fh:
        assert _COMPOSE in fh.read()

    assert _rev(fx.d, "HEAD") == fx.pre
    assert _git(fx.d, "status", "--porcelain").stdout.strip() == ""
    assert not _has_merge_head(fx.d)


def test_live_ladder_harness_error_restores_tree(make_repo, monkeypatch):
    """A HarnessError escaping the ladder still restores the pre-merge tree."""
    fx = _compose_repo(make_repo)
    llm = _compose_llm(fx, opus=_writer(_COMPOSE, fx.full))

    def boom(ctx, proof_out, rungs=None):
        raise HarnessError("git", "boom")

    monkeypatch.setattr(conflict_ladder, "run_ladder", boom)
    git = LiveGit(fx.d, llm=llm)

    with pytest.raises(HarnessError) as exc:
        git.rebase_onto()

    assert exc.value.kind == "rebase-conflict"
    assert "ladder error: git: boom" in exc.value.detail
    assert _rev(fx.d, "HEAD") == fx.pre
    assert not _has_merge_head(fx.d)
    assert _git(fx.d, "status", "--porcelain").stdout.strip() == ""


def test_live_clean_merge_omits_conflict_rung(make_repo, monkeypatch, tmp_path):
    """A branch that merges cleanly leaves `conflict_rung` out of result.json."""
    fx = make_repo({"a.txt": "base\n"}, {"b.txt": "branch\n"}, {"m.txt": "master\n"})
    llm = _ScriptedLlm({})

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "test-stop", res.error
    assert llm.calls == []
    assert len(_parents(fx.d)) == 2
    assert "conflict_rung" not in _result_json(fx)
