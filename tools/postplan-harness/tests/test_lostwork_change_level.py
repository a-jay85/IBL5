"""The change-level lost-work proof in `.claude/review-shared/scripts/lostwork.sh`.

Real-git fixtures, one temp repo per test, no mocks. The harness loads lostwork.sh from
the pinned master SHA, so these tests are the only place the worktree copy runs before it
lands on master; they call `bash <script> <key>` directly with `cwd=<fixture repo>`
instead of going through `GitAdapter`.

Every test captures the PRE patch from the working tree before master moves (mirroring
`capture_lostwork_pre` in `harness/adapters/gitad.py`), builds the post-rebase state, and
reads the script's verdict. The script exits 0 on a verdict and 1 only on a degraded
input, so the verdict string is what gates (the harness substring-matches it).
"""

import glob
import os
import subprocess
from pathlib import Path

import pytest

SCRIPT = (
    Path(__file__).resolve().parents[3]
    / ".claude"
    / "review-shared"
    / "scripts"
    / "lostwork.sh"
)

DIVERGED = "TREE DIVERGED — inspect before pushing"
BASE_F = list("abcdefghij")
BASE_G = ["g1", "g2", "g3", "g4", "g5"]


def sh(d, *args, check=True, env=None):
    full_env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t",
        "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
        **(env or {}),
    }
    return subprocess.run(
        ["git", "-C", str(d), *args],
        check=check,
        capture_output=True,
        text=True,
        env=full_env,
    )


def _commit(d, path, body, message):
    (Path(d) / path).write_text(body)
    sh(d, "add", "-A")
    sh(d, "commit", "-q", "-m", message)


def text(lines):
    return "\n".join(lines) + "\n"


def make_repo(base: Path) -> Path:
    """A repo on `feat` whose origin/master holds f.txt (a..j) and g.txt (g1..g5)."""
    base.mkdir(parents=True, exist_ok=True)
    d = base / "work"
    d.mkdir()
    subprocess.run(["git", "init", "-q", "-b", "master", str(d)], check=True)
    sh(d, "config", "user.email", "t@t")
    sh(d, "config", "user.name", "t")
    sh(d, "config", "commit.gpgsign", "false")
    (d / "f.txt").write_text(text(BASE_F))
    (d / "g.txt").write_text(text(BASE_G))
    sh(d, "add", "-A")
    sh(d, "commit", "-q", "-m", "base")
    origin = base / "origin.git"
    subprocess.run(["git", "clone", "-q", "--bare", str(d), str(origin)], check=True)
    sh(d, "remote", "add", "origin", str(origin))
    sh(d, "push", "-q", "origin", "master")
    sh(d, "fetch", "-q", "origin")
    sh(d, "checkout", "-q", "-b", "feat")
    return d


@pytest.fixture()
def repo(tmp_path):
    return make_repo(tmp_path)


@pytest.fixture()
def key(request):
    k = f"lwcl-{request.node.name}-{os.getpid()}"
    yield k
    for p in (
        f"/tmp/pr-ready-diff-pre-{k}.patch",
        f"/tmp/pr-ready-diff-post-{k}.patch",
        f"/tmp/pr-ready-numstat-pre-{k}.txt",
        f"/tmp/pr-ready-numstat-post-{k}.txt",
    ):
        try:
            os.remove(p)
        except FileNotFoundError:
            pass
    for d in glob.glob(f"/tmp/postplan-lostwork-{k}.*"):
        subprocess.run(["rm", "-rf", d], check=False)


def capture_pre(repo, key):
    """Working-tree diff against the merge-base, taken before fetch/rebase."""
    mb = sh(repo, "merge-base", "origin/master", "HEAD").stdout.strip()
    out = sh(repo, "diff", mb).stdout
    Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_text(out)


def advance_master(repo, path, body, message):
    sh(repo, "checkout", "-q", "master")
    _commit(repo, path, body, message)
    sh(repo, "push", "-q", "origin", "master")
    sh(repo, "checkout", "-q", "feat")
    sh(repo, "fetch", "-q", "origin")


def run_lostwork(repo, key):
    r = subprocess.run(
        ["bash", str(SCRIPT), key], cwd=repo, capture_output=True, text=True
    )
    return r.returncode, r.stdout


def rebase_resolving(repo, resolver):
    r = sh(repo, "rebase", "origin/master", check=False)
    if r.returncode != 0:
        for path, body in resolver.items():
            (Path(repo) / path).write_text(body)
            sh(repo, "add", path)
        sh(repo, "rebase", "--continue", env={"GIT_EDITOR": "true"})


def f_with(**edits):
    """BASE_F with `edits` mapping a line to its replacement (a list of lines)."""
    out = []
    for line in BASE_F:
        out.extend(edits.get(line, [line]))
    return out


def last_line(out):
    return out.rstrip("\n").splitlines()[-1]


def test_master_edit_same_file_nonoverlapping_is_equivalent(repo, key):
    _commit(repo, "f.txt", text(f_with(b=["B1", "B2"])), "feat edit")
    capture_pre(repo, key)
    advance_master(repo, "f.txt", text(f_with(i=["I1"])), "master edit")
    rebase_resolving(repo, {})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "TREE-EQUIVALENT" in out
    assert "CHECKED: files=1 added=2 deleted=1" in out


def test_conflict_resolved_keeping_both_sides_is_equivalent(repo, key):
    _commit(repo, "f.txt", text(f_with(c=["C1", "C2"])), "feat edit")
    capture_pre(repo, key)
    advance_master(repo, "f.txt", text(f_with(d=["D1"])), "master edit")
    resolved = ["a", "b", "C1", "C2", "D1", "e", "f", "g", "h", "i", "j"]
    rebase_resolving(repo, {"f.txt": text(resolved)})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "TREE-EQUIVALENT" in out


def test_master_absorbed_hunk_is_equivalent(repo, key):
    # phase-rank-order-sync-782 shape: master lands the same f.txt hunk, so the post diff
    # has no f.txt row and the numstat row set differs from pre.
    (repo / "f.txt").write_text(text(BASE_F + ["NEW-F"]))
    (repo / "g.txt").write_text(text(BASE_G + ["NEW-G"]))
    sh(repo, "add", "-A")
    sh(repo, "commit", "-q", "-m", "feat edit")
    capture_pre(repo, key)
    advance_master(repo, "f.txt", text(BASE_F + ["NEW-F"]), "master lands NEW-F")
    rebase_resolving(repo, {})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "TREE-EQUIVALENT" in out
    assert "LOST:" not in out


def test_moved_line_is_equivalent(repo, key):
    moved = [x for x in BASE_F if x != "b"] + ["b"]
    _commit(repo, "f.txt", text(moved), "feat move")
    capture_pre(repo, key)
    advance_master(repo, "g.txt", text(BASE_G + ["G6"]), "master edit g")
    rebase_resolving(repo, {})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "TREE-EQUIVALENT" in out


def test_dropped_hunk_diverges(repo, key):
    _commit(repo, "f.txt", text(f_with(b=["B1"], h=["H1"])), "feat two hunks")
    capture_pre(repo, key)
    advance_master(repo, "g.txt", text(BASE_G + ["G6"]), "master edit g")
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "only the first hunk")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: f.txt: +H1" in out
    assert last_line(out) == DIVERGED
    assert "TREE-EQUIVALENT" not in out


def test_dropped_file_diverges(repo, key):
    (repo / "n.txt").write_text("N1\n")
    (repo / "f.txt").write_text(text(f_with(b=["B1"])))
    sh(repo, "add", "-A")
    sh(repo, "commit", "-q", "-m", "feat adds n.txt and edits f.txt")
    capture_pre(repo, key)
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "only the f.txt edit")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: file missing at HEAD: n.txt" in out
    assert DIVERGED in out


def test_dropped_deletion_diverges(repo, key):
    without_e = [x for x in BASE_F if x != "e"] + ["E-NOTE"]
    _commit(repo, "f.txt", text(without_e), "feat deletes e, appends E-NOTE")
    capture_pre(repo, key)
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(BASE_F + ["E-NOTE"]), "only the E-NOTE append")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: f.txt: -e" in out
    assert DIVERGED in out


def test_deleted_file_still_present_diverges(repo, key):
    sh(repo, "rm", "-q", "g.txt")
    sh(repo, "commit", "-q", "-m", "feat deletes g.txt")
    capture_pre(repo, key)
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "unrelated f.txt edit")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: deletion lost, still at HEAD: g.txt" in out
    assert "TREE DIVERGED" in out


def test_both_sides_edited_same_line_blocks(repo, key):
    # Fail-closed ambiguity (documented in the ADR): a conflict resolved to a third
    # value drops the branch's own line, and the proof cannot tell that from a loss.
    advance_master(repo, "f.txt", text(BASE_F + ["count=10"]), "master adds count")
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(BASE_F + ["count=12"]), "feat count=12")
    capture_pre(repo, key)
    advance_master(repo, "f.txt", text(BASE_F + ["count=13"]), "master count=13")
    rebase_resolving(repo, {"f.txt": text(BASE_F + ["count=14"])})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: f.txt: +count=12" in out
    assert DIVERGED in out


def test_empty_post_diff_still_blocks(repo, key):
    # Characterizes the untouched empty-file guard; the wording is not asserted.
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "feat edit")
    capture_pre(repo, key)
    sh(repo, "reset", "-q", "--hard", "origin/master")
    rc, out = run_lostwork(repo, key)
    assert rc != 0
    assert "TREE DIVERGED" in out
    assert "TREE-EQUIVALENT" not in out


def test_quoted_path_fails_closed(repo, key):
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "feat edit")
    patch = (
        'diff --git "a/we\\303\\257rd.txt" "b/we\\303\\257rd.txt"\n'
        '--- "a/we\\303\\257rd.txt"\n'
        '+++ "b/we\\303\\257rd.txt"\n'
        "@@ -1 +1 @@\n"
        "-old content\n"
        "+new content\n"
    )
    Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_text(patch)
    rc, out = run_lostwork(repo, key)
    assert rc == 1
    assert "TREE DIVERGED" in out
    assert "TREE-EQUIVALENT" not in out


def test_insignificant_lines_are_not_checked(repo, key):
    # A `}` or a blank line is never evidence either way (the significance filter).
    _commit(repo, "f.txt", text(BASE_F + ["}", ""]), "feat appends } and a blank")
    capture_pre(repo, key)
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(BASE_F + ["}"]), "only the }")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "TREE-EQUIVALENT" in out
    assert "CHECKED: files=1 added=0 deleted=0" in out


def test_verdict_tokens_are_the_only_last_line(repo, key, tmp_path):
    # Equivalent fixture.
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "feat edit")
    capture_pre(repo, key)
    advance_master(repo, "g.txt", text(BASE_G + ["G6"]), "master edit g")
    rebase_resolving(repo, {})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert last_line(out) == "TREE-EQUIVALENT"

    # Dropped-hunk fixture in a second repo; the same key is reused, sequentially.
    repo2 = make_repo(tmp_path / "second")
    _commit(repo2, "f.txt", text(f_with(b=["B1"], h=["H1"])), "feat two hunks")
    capture_pre(repo2, key)
    advance_master(repo2, "g.txt", text(BASE_G + ["G6"]), "master edit g")
    sh(repo2, "reset", "-q", "--hard", "origin/master")
    _commit(repo2, "f.txt", text(f_with(b=["B1"])), "only the first hunk")
    rc, out = run_lostwork(repo2, key)
    assert rc == 0
    assert last_line(out) == DIVERGED


def test_binary_file_entry_is_equivalent(repo, key):
    # A binary add has no ---/+++ or @@ lines; the entry takes its paths from the
    # `diff --git a/P b/P` line, counts as one file, and carries no line-level evidence.
    (repo / "blob.bin").write_bytes(b"\x00\x01\x02\xff\x00binary\x00")
    sh(repo, "add", "-A")
    sh(repo, "commit", "-q", "-m", "feat adds a binary file")
    capture_pre(repo, key)
    assert "Binary files" in Path(f"/tmp/pr-ready-diff-pre-{key}.patch").read_text()
    advance_master(repo, "g.txt", text(BASE_G + ["G6"]), "master edit g")
    rebase_resolving(repo, {})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "CHECKED: files=1 added=0 deleted=0" in out
    assert last_line(out) == "TREE-EQUIVALENT"
    assert "LOST:" not in out


def test_pure_rename_entry_is_equivalent(repo, key):
    # A 100% rename has no ---/+++ or @@ lines; the entry takes its paths from the
    # rename from/to headers and only needs the new path to exist at HEAD.
    sh(repo, "mv", "g.txt", "g-renamed.txt")
    sh(repo, "commit", "-q", "-m", "feat renames g.txt")
    capture_pre(repo, key)
    assert "rename from g.txt" in Path(f"/tmp/pr-ready-diff-pre-{key}.patch").read_text()
    advance_master(repo, "f.txt", text(f_with(i=["I1"])), "master edit f")
    rebase_resolving(repo, {})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "CHECKED: files=1 added=0 deleted=0" in out
    assert last_line(out) == "TREE-EQUIVALENT"
    assert "LOST:" not in out
