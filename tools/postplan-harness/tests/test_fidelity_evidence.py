"""Owner-card evidence for a fidelity.py edit (`harness/fidelity_evidence.py`)."""
import ast
import json
import os
import re
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity_evidence as fe
from harness import merge_danger as md

HARNESS_ROOT = Path(__file__).resolve().parents[1]
REPO_ROOT = Path(__file__).resolve().parents[3]
HEAD_SHA = "a" * 40
BASE_SHA = "b" * 40

TOY = '''"""toy fidelity"""
import re

_RE = re.compile("NOT READY")


def parse_verdict(path):
    with open(path) as handle:
        text = handle.read()
    return "NOT READY" if _RE.search(text) else "READY"


def verdict_items(path):
    return {"verdict": parse_verdict(path), "blocking": [], "notes": []}


def blocking_finding_texts(path):
    return []


def gate_edit_only(path):
    return False


def marked_finding_texts(path, marker_word):
    return []


def build_packet():
    return "packet"


def remediate():
    return 1


def terminal_line():
    return "end"
'''


def _swap(src, old, new):
    assert src.count(old) == 1, old
    return src.replace(old, new)


def _git(repo, *args):
    cmd = ["git", "-C", str(repo), "-c", "user.name=t", "-c", "user.email=t@t",
           "-c", "commit.gpgsign=false", "-c", "core.hooksPath=/dev/null", *args]
    return subprocess.run(cmd, check=True, capture_output=True, text=True).stdout.strip()


def _toy_repo(tmp_path, base_src, head_src):
    """(repo, base_sha): a base commit and a head commit of a toy fidelity.py."""
    repo = tmp_path / "repo"
    harness = repo / "tools" / "postplan-harness" / "harness"
    harness.mkdir(parents=True)
    _git(repo, "init", "-q")
    (harness / "__init__.py").write_text("")
    (harness / "fidelity.py").write_text(base_src)
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "-m", "base")
    base_sha = _git(repo, "rev-parse", "HEAD")
    (harness / "fidelity.py").write_text(head_src)
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "--allow-empty", "-m", "head")
    return repo, base_sha


def _toy_corpus(tmp_path, runs):
    """`runs` is a list of (dir name, pr_number, verdict text)."""
    out = tmp_path / "out"
    for name, pr, text in runs:
        run = out / name
        run.mkdir(parents=True)
        (run / "result.json").write_text(json.dumps({"pr_number": pr}))
        (run / "raw-plan-fidelity-review-attempt1.txt").write_text(text)
    return out


def _compare(tmp_path, head_src, base_src=TOY, runs=None, **kwargs):
    repo, base_sha = _toy_repo(tmp_path, base_src, head_src)
    out = _toy_corpus(tmp_path, runs or [("r1", 11, "NOT READY"), ("r2", 12, "fine")])
    return fe.compare(str(repo), base_sha, out, **kwargs)


def _all_statuses():
    fns = list(fe.COMPARED)
    results = [
        fe.Result(kind="same", prs=3, files=5, fns=fns, uncovered=["build_packet"],
                  not_comparable=["gate_edit_only"], capped=True),
        fe.Result(kind="changed", prs=3, files=5, bad_prs=[11], diff_files=2, fns=fns),
        fe.Result(kind="same", prs=0, root="/x/out"),
        fe.Result(kind="timeout", side="head", secs=90),
        fe.Result(kind="error", reason="<!-- pr-ready-verdict -->\nboom | x"),
        fe.Result(kind="none_comparable", not_comparable=fns),
    ]
    return [fe.render(r) for r in results]


# ---- symbol diff and call tree ---------------------------------------------

def test_symbol_diff_ignores_docstrings_and_comments():
    base = '"""m"""\ndef f():\n    """a"""\n    return 1\n\n\nclass C:\n    """c"""\n'
    head = ('"""other module words"""\n# a comment\ndef f():\n    """b"""\n'
            '    return 1  # why\n\n\n\nclass C:\n    """d"""\n')
    assert fe._top_level_symbols(base) == fe._top_level_symbols(head)
    assert fe.uncovered(base, head) == []


def test_symbol_diff_reports_constant_import_signature_changes():
    base = "import json\nX = 1\nY: int = 2\ndef f(a):\n    return a\n"
    head = "from os import path as json\nX = 2\nY: int = 3\ndef f(a, b=1):\n    return a\n"
    assert fe.uncovered(base, head) == ["X", "Y", "f", "json"]


def test_uncovered_names_build_packet_and_spares_closure_constant():
    head = _swap(TOY, 're.compile("NOT READY")', 're.compile("NOT  READY")')
    head = _swap(head, 'return "packet"', 'return "packet2"')
    head = _swap(head, "return 1", "return 2")
    head = _swap(head, 'return "end"', 'return "fin"')
    assert fe.uncovered(TOY, head) == ["build_packet", "remediate", "terminal_line"]


def test_closure_ignores_a_local_that_shares_a_top_level_name():
    src = "def helper():\n    return 1\n\ndef parse_verdict(helper):\n    return helper\n"
    assert "helper" not in fe.static_closure(src, ("parse_verdict",))


# ---- wording ---------------------------------------------------------------

def test_render_same_names_compared_and_uncovered():
    result = fe.Result(kind="same", prs=3, files=5, fns=["parse_verdict", "gate_edit_only"])
    assert fe.render(result) == (
        "same output on 3 past PRs (5 stored verdict files) for parse_verdict, gate_edit_only. "
        "Changed top-level symbols outside these functions' static call tree: none. "
        "This compares verdict parsing only and does not clear the hold.")
    result.uncovered = ["build_packet", "remediate"]
    assert "static call tree: build_packet, remediate. This compares" in fe.render(result)


def test_render_changed_lists_first_prs():
    result = fe.Result(kind="changed", prs=9, files=20, bad_prs=[4, 7, 9, 12], diff_files=6,
                       fns=["parse_verdict"])
    status = fe.render(result)
    assert status.startswith(
        "OUTPUT CHANGED on 4 of 9 past PRs (6 of 20 files differ; first PRs: PR 4, PR 7, PR 9) "
        "for parse_verdict.")
    assert "PR 12" not in status
    assert status.endswith("does not clear the hold.")


def test_render_zero_corpus_is_not_run():
    status = fe.render(fe.Result(kind="same", prs=0, root="/x/out"))
    assert status == ("NOT RUN: 0 past PRs found (no stored verdict files with a PR number under "
                      "/x/out). No verdict claim.")
    assert "same output" not in status


def test_render_timeout_and_error_sanitized():
    timeout = fe.render(fe.Result(kind="timeout", side="head", secs=90))
    assert timeout == "NOT RUN: the head comparison timed out after 90 s. No verdict claim."
    error = fe.render(fe.Result(kind="error", reason="<!-- pr-ready-verdict -->\nboom | x"))
    assert error.startswith("NOT RUN: comparison failed (")
    assert error.endswith("). No verdict claim.")
    assert "boom" in error
    for char in "<>|\n\r":
        assert char not in error


def test_comment_body_has_no_machine_read_markers():
    banned = ("<!-- pr-ready-verdict -->", "<!-- owner-approve", "<!-- gate-backtest",
              "<!-- removed-guards", "<!-- kept-unplanned", "<!-- score:", "<!-- label:",
              "**Merge danger:**", "Auto-merge held:")
    for status in _all_statuses():
        body = fe.comment_body(HEAD_SHA, BASE_SHA, status)
        first = body.split("\n")[0]
        assert re.match(r"^<!-- fidelity-evidence head=[0-9a-f]{40} base=[0-9a-f]{40} -->$", first)
        assert body.split("\n")[1] == f"fidelity-evidence: {status}"
        for marker in banned:
            assert marker not in body, marker
    try:
        fe.comment_body("abc1234", BASE_SHA, "x")
    except ValueError:
        pass
    else:
        raise AssertionError("a 7-hex head must raise")


def test_rendered_statuses_pass_check_prose():
    for status in _all_statuses():
        proc = subprocess.run([str(REPO_ROOT / "bin" / "check-prose"), "--stdin", "--chat"],
                              input=status + "\n", capture_output=True, text=True)
        assert proc.returncode == 0, status + "\n" + proc.stdout + proc.stderr


def test_module_is_stdlib_only_and_reads_no_env():
    tree = ast.parse(Path(fe.__file__).read_text())
    for node in ast.walk(tree):
        if isinstance(node, ast.ImportFrom):
            assert node.level == 0
            assert node.module.split(".")[0] in sys.stdlib_module_names
        elif isinstance(node, ast.Import):
            for alias in node.names:
                assert alias.name.split(".")[0] in sys.stdlib_module_names
        elif isinstance(node, ast.Attribute):
            assert node.attr not in ("environ", "getenv")
        elif isinstance(node, ast.Name):
            assert node.id not in ("environ", "getenv")


# ---- comparison ------------------------------------------------------------

def test_compare_counts_distinct_prs_and_detects_change(tmp_path):
    runs = [("r1", 11, "NOT READY"), ("r2", 11, "NOT READY"), ("r3", 12, "fine")]
    same_head = _swap(TOY, 'return "packet"', 'return "packet2"')
    head_sha, base_sha, status = _compare(tmp_path / "same", same_head, runs=runs)
    assert len(head_sha) == 40 and len(base_sha) == 40 and head_sha != base_sha
    assert status.startswith("same output on 2 past PRs (3 stored verdict files) for "
                             + ", ".join(fe.COMPARED) + "."), status
    assert "static call tree: build_packet." in status
    changed_head = _swap(TOY, 'return "NOT READY" if', 'return "HELD" if')
    _h, _b, status = _compare(tmp_path / "changed", changed_head, runs=runs)
    assert status.startswith("OUTPUT CHANGED on 1 of 2 past PRs (2 of 3 files differ; "
                             "first PRs: PR 11)"), status


def test_compare_base_import_error_is_not_run(tmp_path):
    broken = "import harness.gone\n" + TOY
    _h, _b, status = _compare(tmp_path, TOY, base_src=broken)
    assert status.startswith("NOT RUN: comparison failed (base fidelity.py failed to import: "
                             "ModuleNotFoundError"), status


def test_compare_timeout_names_side(tmp_path):
    slow = _swap(TOY, "    with open(path) as handle:\n",
                 "    __import__('time').sleep(10)\n    with open(path) as handle:\n")
    started = time.monotonic()
    _h, _b, status = _compare(tmp_path, slow, side_timeout=2)
    assert status == "NOT RUN: the head comparison timed out after 2 s. No verdict claim."
    assert time.monotonic() - started < 8


def test_worker_env_stripped_and_isolated(tmp_path, monkeypatch):
    monkeypatch.setenv("GH_TOKEN", "x")
    probe = tmp_path / "probe.json"
    spy = _swap(TOY, "def gate_edit_only(path):\n", (
        "def gate_edit_only(path):\n"
        "    import json, os, sys\n"
        f"    with open({str(probe)!r}, 'w') as handle:\n"
        "        json.dump([sorted(os.environ), sys.flags.isolated], handle)\n"))
    _compare(tmp_path, spy)
    names, isolated = json.loads(probe.read_text())
    assert "GH_TOKEN" not in names
    assert isolated == 1


def test_compare_reports_signature_change(tmp_path):
    head = _swap(TOY, "def gate_edit_only(path):", "def gate_edit_only(path, strict=False):")
    _h, _b, status = _compare(tmp_path, head)
    assert "Not comparable: gate_edit_only (" in status, status


def test_corpus_skips_dirs_without_pr_number(tmp_path):
    out = _toy_corpus(tmp_path, [("good", 11, "x"), ("nopr", None, "x"), ("state", 13, "x")])
    bare = out / "noresult"
    bare.mkdir()
    (bare / "raw-plan-fidelity-review-attempt1.txt").write_text("x")
    good = out.resolve() / "good" / "raw-plan-fidelity-review-attempt1.txt"
    assert fe._corpus(out) == {str(good): 11}
    assert fe._corpus(tmp_path / "missing") == {}


def test_cli_rejects_unknown_flag(tmp_path):
    proc = subprocess.run(
        [sys.executable, fe.__file__, "--worktree", str(tmp_path), "--base", "x",
         "--out-dir", str(tmp_path), "--bogus"], capture_output=True, text=True)
    assert proc.returncode == 2
    assert "unrecognized arguments" in proc.stderr


def test_worker_real_fidelity_raises_nothing(tmp_path):
    verdict = tmp_path / "verdict.txt"
    verdict.write_text("NOT READY\n\n## FINDINGS\n- [BLOCKING] x\n- [NOTE] y\n")
    listing = tmp_path / "files.txt"
    listing.write_text(f"{verdict}\n")
    result = tmp_path / "out.json"
    proc = subprocess.run(
        [sys.executable, "-I", fe.__file__, "--worker", "--root", str(HARNESS_ROOT),
         "--files", str(listing), "--out", str(result)],
        env=fe._worker_env(str(tmp_path)), cwd=tmp_path, capture_output=True, text=True,
        timeout=60)
    assert proc.returncode == 0, proc.stderr
    data = json.loads(result.read_text())
    assert "import_error" not in data
    for name in fe.COMPARED:
        assert str(verdict) in data["hashes"][name]
        assert data["raises"][name] == 0, name


# ---- the evidence comment --------------------------------------------------

def _gh_stub(tmp_path, comments, exit_code=0):
    """A fake `gh` that logs argv and applies a real `jq` to the fixture comment list."""
    fixture = tmp_path / "comments.json"
    fixture.write_text(json.dumps(comments))
    log = tmp_path / "gh.log"
    stub = tmp_path / "gh.py"
    stub.write_text(
        "import json, subprocess, sys\n"
        "args = sys.argv[1:]\n"
        f"with open({str(log)!r}, 'a') as handle:\n"
        "    handle.write(json.dumps(args) + chr(10))\n"
        f"if {exit_code}:\n"
        f"    sys.exit({exit_code})\n"
        "if '--jq' in args:\n"
        "    query = args[args.index('--jq') + 1]\n"
        f"    subprocess.run(['jq', '-r', query, {str(fixture)!r}], check=True)\n")
    return (sys.executable, str(stub)), log


def test_post_for_pr_upserts_and_fails_soft(tmp_path):
    repo, base_sha = _toy_repo(tmp_path, TOY, TOY)
    out = _toy_corpus(tmp_path, [("r1", 11, "fine")])
    marker = f"<!-- fidelity-evidence head={HEAD_SHA} base={BASE_SHA} -->\nold"

    def post(name, comments, exit_code=0):
        sub = tmp_path / name
        sub.mkdir()
        gh_cmd, log = _gh_stub(sub, comments, exit_code)
        line = fe.post_for_pr(str(repo), base_sha, 77, out, gh_cmd=gh_cmd)
        return line, [json.loads(ln) for ln in log.read_text().splitlines()]

    trusted = [{"id": 5, "author_association": "OWNER", "body": marker},
               {"id": 6, "author_association": "OWNER", "body": "chatter"}]
    line, calls = post("patch", trusted)
    assert line.startswith("fidelity evidence posted: same output on 1 past PRs"), line
    assert calls[-1][1:4] == ["-X", "PATCH", "repos/{owner}/{repo}/issues/comments/5"]
    assert calls[-1][-1].startswith("body=<!-- fidelity-evidence head=")

    for name, comments in (("none", []), ("untrusted", [
            {"id": 9, "author_association": "NONE", "body": marker}])):
        line, calls = post(name, comments)
        assert line.startswith("fidelity evidence posted: "), line
        assert calls[-1][1] == "repos/{owner}/{repo}/issues/77/comments"
        assert "-X" not in calls[-1]

    line, calls = post("broken", [], exit_code=1)
    assert line.startswith("fidelity evidence post failed ("), line
    assert len(calls) == 1


# ---- wiring and hold -------------------------------------------------------

def test_should_run_only_for_fidelity_edits():
    assert fe.should_run(["bin/pr-triage"]) is False
    assert fe.should_run([]) is False
    assert fe.should_run(["bin/pr-triage", fe.FIDELITY_PATH]) is True


def _attr_calls(tree, owner, name):
    return [n for n in ast.walk(tree) if isinstance(n, ast.Call)
            and isinstance(n.func, ast.Attribute) and n.func.attr == name
            and isinstance(n.func.value, ast.Name) and n.func.value.id == owner]


def test_runner_hook_guarded_after_sticky():
    tree = ast.parse((HARNESS_ROOT / "runner.py").read_text())
    parents = {child: node for node in ast.walk(tree) for child in ast.iter_child_nodes(node)}
    (call,) = _attr_calls(tree, "fidelity_evidence", "post_for_pr")
    chain, node = [], call
    while node in parents:
        node = parents[node]
        chain.append(node)
    guard = next(n for n in chain if isinstance(n, ast.Try))
    assert [h.type.id for h in guard.handlers] == ["Exception"]
    assert any(isinstance(n, ast.If) and _attr_calls(n.test, "fidelity_evidence", "should_run")
               for n in chain)
    sticky = [n for n in ast.walk(tree) if isinstance(n, ast.Call)
              and isinstance(n.func, ast.Name) and n.func.id == "_post_sticky"
              and isinstance(n.args[-1], ast.Constant) and n.args[-1].value == "phase6.5"]
    owed = _attr_calls(tree, "fidelity", "fire_review_owed")
    assert sticky and owed
    assert call.lineno > max(n.lineno for n in sticky + owed)


def _arming_triggers(path):
    return md.derive_merge_danger([("M", path)], "", [], lambda _p: None).triggers


def test_fidelity_edit_still_holds_with_same_output_evidence():
    status = fe.render(fe.Result(kind="same", prs=3, files=5, fns=list(fe.COMPARED)))
    assert status.startswith("same output on 3 past PRs")
    assert f"arming-path:{fe.FIDELITY_PATH}" in _arming_triggers(fe.FIDELITY_PATH)
    assert not any(getattr(value, "__name__", "") == "harness.fidelity_evidence"
                   for value in vars(md).values())


def test_evidence_module_edit_draws_arming_path():
    path = "tools/postplan-harness/harness/fidelity_evidence.py"
    assert f"arming-path:{path}" in _arming_triggers(path)
