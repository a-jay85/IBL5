"""Real-subprocess gh PATH shim: allowlisted reads retry transient failures, and
pr create / pr comment / reviews POST retry only when their effect did not land."""
from __future__ import annotations

import json
import os
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import netretry
from harness.adapters.ghad import LiveGh
from harness.state import Finding, HarnessError

EOF_MSG = 'Post "https://api.github.com/graphql": EOF'
TLS_MSG = "net/http: TLS handshake timeout"

SHIM = f"""#!{sys.executable}
import json, os, sys
args = sys.argv[1:]
key = " ".join(args[:2])
with open(os.environ["GH_SHIM_LOG"], "a") as f:
    f.write(key + "\\n")
sp = os.environ["GH_SHIM_STATE"]
with open(sp) as f:
    state = json.load(f)


def save():
    with open(sp, "w") as f:
        json.dump(state, f)


def fail(msg):
    sys.stderr.write(msg + "\\n")
    sys.exit(1)


def opt(name):
    return args[args.index(name) + 1] if name in args else None


msg = os.environ.get("GH_SHIM_MSG", "")
if key == os.environ.get("GH_SHIM_ALWAYS_FAIL"):
    fail(os.environ.get("GH_SHIM_ALWAYS_MSG", msg))
if key == os.environ.get("GH_SHIM_FAIL_ONCE") and key not in state["failed_once"]:
    state["failed_once"].append(key)
    save()
    fail(msg)
land_then_fail = (key == os.environ.get("GH_SHIM_LAND_THEN_FAIL")
                  and key not in state["failed_once"])
out = ""
if key == "repo view":
    out = "o/r"
elif key == "pr list":
    out = json.dumps(state["open_prs"])
elif key == "pr create":
    out = "https://github.com/o/r/pull/4242"
    state["open_prs"].append({{"number": 4242, "url": out}})
elif key == "pr comment":
    state["comments"].append(opt("--body"))
elif args[0] == "api" and args[1].endswith("/reviews"):
    state["reviews"].append(json.loads(sys.stdin.read()).get("body"))
elif key == "pr view" and "--json" in args:
    field = opt("--json")
    out = json.dumps({{field: [{{"body": b}} for b in state.get(field, [])]}})
elif key == "issue create":
    out = "https://github.com/a-jay85/IBL5-backlog/issues/9"
if land_then_fail:
    state["failed_once"].append(key)
    save()
    fail(msg)
save()
print(out)
"""


@pytest.fixture
def sleeps(monkeypatch):
    recorded = []
    monkeypatch.setattr(netretry.time, "sleep", recorded.append)
    return recorded


@pytest.fixture
def shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    gh = bindir / "gh"
    gh.write_text(SHIM)
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "gh-calls.log"
    log.write_text("")
    state = tmp_path / "gh-state.json"
    state.write_text(json.dumps({"open_prs": [], "comments": [], "reviews": [],
                                 "failed_once": []}))
    monkeypatch.setenv("PATH", f"{bindir}{os.pathsep}{os.environ['PATH']}")
    monkeypatch.setenv("GH_SHIM_LOG", str(log))
    monkeypatch.setenv("GH_SHIM_STATE", str(state))
    for var in ("GH_SHIM_FAIL_ONCE", "GH_SHIM_LAND_THEN_FAIL", "GH_SHIM_ALWAYS_FAIL",
                "GH_SHIM_ALWAYS_MSG", "GH_SHIM_MSG"):
        monkeypatch.delenv(var, raising=False)
    g = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    return g, log, state


def _lines(log, key):
    return sum(1 for line in log.read_text().splitlines() if line == key)


def _seed(state, **kw):
    data = json.loads(state.read_text())
    data.update(kw)
    state.write_text(json.dumps(data))


@pytest.mark.parametrize("msg", [EOF_MSG, TLS_MSG])
def test_gh_read_retries_each_signature_once(shim, monkeypatch, sleeps, msg):
    g, log, _ = shim
    monkeypatch.setenv("GH_SHIM_FAIL_ONCE", "repo view")
    monkeypatch.setenv("GH_SHIM_MSG", msg)
    assert g._repo() == "o/r"
    assert _lines(log, "repo view") == 2
    assert sleeps == [5.0]


def test_pr_create_eof_with_existing_open_pr_does_not_duplicate(shim, monkeypatch, sleeps):
    g, log, state = shim
    monkeypatch.setenv("GH_SHIM_LAND_THEN_FAIL", "pr create")
    monkeypatch.setenv("GH_SHIM_MSG", EOF_MSG)
    assert g.pr_create("chore: t", "body", "master") == 4242
    assert _lines(log, "pr create") == 1
    assert len(json.loads(state.read_text())["open_prs"]) == 1
    assert sleeps == []


def test_pr_create_eof_not_landed_retries_once(shim, monkeypatch, sleeps):
    g, log, _ = shim
    monkeypatch.setenv("GH_SHIM_FAIL_ONCE", "pr create")
    monkeypatch.setenv("GH_SHIM_MSG", EOF_MSG)
    assert g.pr_create("chore: t", "body", "master") == 4242
    assert _lines(log, "pr create") == 2
    assert sleeps == [5.0]


def test_pr_create_snapshot_failure_is_single_attempt(shim, monkeypatch, sleeps):
    g, log, _ = shim
    monkeypatch.setenv("GH_SHIM_ALWAYS_FAIL", "pr list")
    monkeypatch.setenv("GH_SHIM_ALWAYS_MSG", "HTTP 401: Bad credentials")
    monkeypatch.setenv("GH_SHIM_FAIL_ONCE", "pr create")
    monkeypatch.setenv("GH_SHIM_MSG", EOF_MSG)
    with pytest.raises(HarnessError) as ei:
        g.pr_create("chore: t", "body", "master")
    assert ei.value.kind == "gh"
    assert _lines(log, "pr create") == 1
    assert sleeps == []


def test_pr_comment_landed_then_eof_is_not_reposted(shim, monkeypatch, sleeps):
    g, log, state = shim
    _seed(state, comments=["## T\n\nB"])
    monkeypatch.setenv("GH_SHIM_LAND_THEN_FAIL", "pr comment")
    monkeypatch.setenv("GH_SHIM_MSG", EOF_MSG)
    g.post_review_summary(7, "T", "B")
    assert _lines(log, "pr comment") == 1
    assert json.loads(state.read_text())["comments"] == ["## T\n\nB", "## T\n\nB"]
    assert sleeps == []


def test_identical_earlier_comment_does_not_count_as_landed(shim, monkeypatch, sleeps):
    g, log, state = shim
    _seed(state, comments=["## T\n\nB"])
    monkeypatch.setenv("GH_SHIM_FAIL_ONCE", "pr comment")
    monkeypatch.setenv("GH_SHIM_MSG", TLS_MSG)
    g.post_review_summary(7, "T", "B")
    assert _lines(log, "pr comment") == 2
    assert len(json.loads(state.read_text())["comments"]) == 2
    assert sleeps == [5.0]


def test_review_post_landed_then_tls_timeout_is_not_reposted(shim, monkeypatch, sleeps):
    g, log, state = shim
    monkeypatch.setenv("GH_SHIM_LAND_THEN_FAIL", "api repos/o/r/pulls/7/reviews")
    monkeypatch.setenv("GH_SHIM_MSG", TLS_MSG)
    finding = Finding("security-audit", "security", "a.php", 3, "x", 90)
    g.post_review_findings(7, "sha", "T", [finding])
    assert _lines(log, "api repos/o/r/pulls/7/reviews") == 1
    assert _lines(log, "pr comment") == 0
    assert json.loads(state.read_text())["reviews"] == ["T"]
    assert sleeps == []


@pytest.mark.parametrize("key,call", [
    ("pr comment", lambda g: g.post_review_summary(7, "T", "B")),
    ("repo view", lambda g: g._repo()),
])
def test_non_transient_gh_error_is_single_attempt(shim, monkeypatch, sleeps, key, call):
    g, log, _ = shim
    monkeypatch.setenv("GH_SHIM_ALWAYS_FAIL", key)
    monkeypatch.setenv("GH_SHIM_ALWAYS_MSG", "HTTP 422: Unprocessable Entity")
    with pytest.raises(HarnessError) as ei:
        call(g)
    assert ei.value.kind == "gh"
    assert _lines(log, key) == 1
    assert sleeps == []


def test_non_allowlisted_mutation_without_landed_is_single_attempt(shim, monkeypatch, sleeps):
    g, log, _ = shim
    monkeypatch.setenv("GH_SHIM_FAIL_ONCE", "issue create")
    monkeypatch.setenv("GH_SHIM_MSG", EOF_MSG)
    with pytest.raises(HarnessError) as ei:
        g.issue_create("t", "b", "followup")
    assert ei.value.kind == "gh"
    assert _lines(log, "issue create") == 1
    assert sleeps == []
