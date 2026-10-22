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
    (Path(d) / path).parent.mkdir(parents=True, exist_ok=True)
    (Path(d) / path).write_text(body)
    sh(d, "add", "-A")
    sh(d, "commit", "-q", "-m", message)


def _commit_symlink(d, path, target, message):
    """Replace `path` with a symlink to `target` (a regular-file -> symlink type change)."""
    p = Path(d) / path
    if p.is_symlink() or p.exists():
        p.unlink()
    os.symlink(target, p)
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


def merge_resolving(repo, resolver):
    r = sh(repo, "-c", "rerere.enabled=false", "merge", "--no-edit", "origin/master", check=False)
    if r.returncode != 0:
        for path, body in resolver.items():
            (Path(repo) / path).write_text(body)
            sh(repo, "add", path)
        sh(repo, "-c", "core.editor=true", "commit", "--no-edit")


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


def test_type_change_file_to_symlink_against_own_head_is_equivalent(repo, key):
    """PR #3105: `git diff` emits a type change as a delete+create pair on one path.
    The delete half must not read as a lost deletion when HEAD holds the new type."""
    _commit_symlink(repo, "g.txt", "f.txt", "g.txt becomes a symlink")
    capture_pre(repo, key)
    pre = Path(f"/tmp/pr-ready-diff-pre-{key}.patch").read_text()
    assert pre.count("diff --git a/g.txt b/g.txt") == 2  # delete half + create half
    assert "deleted file mode 100644" in pre and "new file mode 120000" in pre
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: deletion lost, still at HEAD: g.txt" not in out
    assert "CHECKED: files=2 added=1 deleted=5" in out
    assert last_line(out) == "TREE-EQUIVALENT"


def test_type_change_symlink_to_file_against_own_head_is_equivalent(repo, key):
    """The reverse pair: origin/master holds a symlink, the branch turns it into a file."""
    sh(repo, "checkout", "-q", "master")
    _commit_symlink(repo, "h.lnk", "f.txt", "seed a symlink on master")
    sh(repo, "push", "-q", "origin", "master")
    sh(repo, "checkout", "-q", "feat")
    sh(repo, "fetch", "-q", "origin")
    sh(repo, "reset", "-q", "--hard", "origin/master")
    (Path(repo) / "h.lnk").unlink()  # write_text would follow the link into f.txt
    _commit(repo, "h.lnk", text(["H1", "H2"]), "h.lnk becomes a regular file")
    capture_pre(repo, key)
    pre = Path(f"/tmp/pr-ready-diff-pre-{key}.patch").read_text()
    assert "deleted file mode 120000" in pre and "new file mode 100644" in pre
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST:" not in out
    assert "CHECKED: files=2 added=2 deleted=1" in out
    assert last_line(out) == "TREE-EQUIVALENT"


def test_type_change_resolved_to_old_regular_file_diverges(repo, key):
    """Negative: the resolver kept the old regular file. HEAD mode 100644 differs from the
    create header's 120000, so the delete half is a lost deletion and the create half's
    added line is missing. Both LOST lines must print."""
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "unrelated edit keeps POST non-empty")
    _commit_symlink(repo, "g.txt", "f.txt", "g.txt becomes a symlink")
    capture_pre(repo, key)
    (Path(repo) / "g.txt").unlink()
    _commit(repo, "g.txt", text(BASE_G), "resolution kept the regular file")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: deletion lost, still at HEAD: g.txt" in out
    assert "LOST: g.txt: +f.txt" in out
    assert last_line(out) == DIVERGED


def test_type_change_pair_with_retargeted_symlink_diverges(repo, key):
    """Mode equality alone is not enough: HEAD is a symlink (delete half honoured) but it
    points elsewhere, so the create half's content check still reports the lost line."""
    _commit(repo, "f.txt", text(f_with(b=["B1"])), "unrelated edit keeps POST non-empty")
    _commit_symlink(repo, "g.txt", "f.txt", "g.txt becomes a symlink")
    capture_pre(repo, key)
    _commit_symlink(repo, "g.txt", "elsewhere.txt", "resolution retargeted the link")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "deletion lost" not in out
    assert "LOST: g.txt: +f.txt" in out
    assert last_line(out) == DIVERGED


def test_deleted_file_with_unrelated_new_file_diverges(repo, key):
    """A real deletion next to an unrelated new file: the create lookup is keyed by path,
    so n.txt's `M` record must not excuse g.txt's lost deletion."""
    _commit(repo, "n.txt", text(["N1"]), "new file elsewhere")
    sh(repo, "rm", "-q", "g.txt")
    sh(repo, "commit", "-q", "-m", "delete g.txt")
    capture_pre(repo, key)
    _commit(repo, "g.txt", text(BASE_G), "resolution restored g.txt")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: deletion lost, still at HEAD: g.txt" in out
    assert last_line(out) == DIVERGED


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


def lv_doc(date, body):
    return text(["---", "description: x", f"last_verified: {date}", "---"] + body)


def seed_base(repo, path, body):
    """Land `path` on master before any branch work, so it sits at the fork point."""
    advance_master(repo, path, body, "seed base")
    sh(repo, "merge", "-q", "--ff-only", "origin/master")


def last_verified_case(repo, key, branch_date, master_date, resolved):
    # The branch also edits g.txt so the post-merge diff is never empty, even when the
    # resolution of d.md happens to equal master's copy.
    seed_base(repo, "d.md", lv_doc("2026-09-30", ["A1"]))
    _commit(repo, "g.txt", text(BASE_G + ["G-FEAT"]), "feat edits g")
    _commit(repo, "d.md", lv_doc(branch_date, ["A1", "B1"]), "feat bumps d.md")
    capture_pre(repo, key)
    advance_master(repo, "d.md", lv_doc(master_date, ["A1", "M1"]), "master bumps d.md")
    merge_resolving(repo, {"d.md": resolved})
    return run_lostwork(repo, key)


def test_last_verified_newer_on_master_is_equivalent(repo, key):
    rc, out = last_verified_case(
        repo, key, "2026-10-04", "2026-10-05", lv_doc("2026-10-05", ["A1", "B1", "M1"])
    )
    assert rc == 0, out
    assert "TREE-EQUIVALENT" in out


def test_last_verified_equal_both_sides_is_equivalent(repo, key):
    rc, out = last_verified_case(
        repo, key, "2026-10-05", "2026-10-05", lv_doc("2026-10-05", ["A1", "B1", "M1"])
    )
    assert rc == 0, out
    assert "TREE-EQUIVALENT" in out


def test_last_verified_regressed_to_older_is_lost(repo, key):
    rc, out = last_verified_case(
        repo, key, "2026-10-05", "2026-10-04", lv_doc("2026-10-04", ["A1", "B1", "M1"])
    )
    # lostwork.sh exits 0 when it completes a comparison; the verdict is the last line.
    assert rc == 0
    assert "LOST: d.md: +last_verified: 2026-10-05" in out
    assert "TREE DIVERGED" in out
    assert last_line(out) == DIVERGED


def test_last_verified_exemption_keeps_body_line_strict(repo, key):
    rc, out = last_verified_case(
        repo, key, "2026-10-04", "2026-10-05", lv_doc("2026-10-05", ["A1", "M1"])
    )
    assert rc == 0
    assert "LOST: d.md: +B1" in out
    assert "+last_verified" not in out
    assert last_line(out) == DIVERGED


def test_last_verified_outside_frontmatter_is_not_exempt(repo, key):
    # No leading `---`: HEAD carries a newer body `last_verified:` line, but the
    # exemption reads frontmatter only, so the branch's dropped line stays lost.
    seed_base(repo, "d.md", text(["A1", "Z1"]))
    _commit(repo, "g.txt", text(BASE_G + ["G-FEAT"]), "feat edits g")
    _commit(repo, "d.md", text(["A1", "last_verified: 2026-10-04", "Z1"]), "feat adds line")
    capture_pre(repo, key)
    advance_master(
        repo, "d.md", text(["A1", "last_verified: 2026-10-05", "Z1"]), "master adds line"
    )
    merge_resolving(repo, {"d.md": text(["A1", "last_verified: 2026-10-05", "Z1"])})
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: d.md: +last_verified: 2026-10-04" in out
    assert last_line(out) == DIVERGED


def test_adapted_added_line_is_reported_lost_today(repo, key):
    # Regression shape of bin/test-plan-now (master 1de1f3b81): master rewrites `/tmp/`
    # to `"$RL"/` on a neighbouring line, the resolver carries the same rewrite onto the
    # branch's added line, and the proof reports the adapted line as LOST. This pins the
    # `LOST: <path>: +<body>` and `CHECKED:` text contract adaptations.py parses.
    base = f_with(d=["cp /tmp/d /tmp/d2"])
    seed_base(repo, "f.txt", text(base))
    _commit(repo, "f.txt", text(base + ["run /tmp/new.log"]), "feat adds run line")
    capture_pre(repo, key)
    master_body = f_with(d=['cp /tmp/d "$RL"/d'])
    advance_master(repo, "f.txt", text(master_body), "master rewrites /tmp/ to $RL")
    sh(repo, "reset", "-q", "--hard", "origin/master")
    _commit(repo, "f.txt", text(master_body + ['run "$RL"/new.log']), "adapted copy")
    rc, out = run_lostwork(repo, key)
    assert rc == 0
    assert "LOST: f.txt: +run /tmp/new.log" in out
    assert "CHECKED: files=1 added=1 deleted=0" in out
    assert DIVERGED in out


# --- phpstan-baseline.neon absent-path exemption (ADR-0174 addendum) ---

BASELINE = "ibl5/phpstan-baseline.neon"


def neon_block(msg, ident, count, path):
    return [
        "\t\t-",
        f"\t\t\trawMessage: '{msg}'",
        f"\t\t\tidentifier: {ident}",
        f"\t\t\tcount: {count}",
        f"\t\t\tpath: {path}",
        "",
    ]


def neon(*blocks):
    body = ["parameters:", "\tignoreErrors:"]
    for b in blocks:
        body.extend(b)
    return "\n".join(body) + "\n"


def _commit_rm(d, path, message):
    os.remove(Path(d) / path)
    sh(d, "add", "-A")
    sh(d, "commit", "-q", "-m", message)


X = neon_block("Call to foo() on dead module.", "foo.dead", 1, "modules/Foo/index.php")
Y = neon_block("Call to bar() on live module.", "bar.live", 2, "modules/Bar/index.php")


def baseline_case(repo, key, *, base, branch, head, baseline_path=BASELINE, rm=(), keep_root=()):
    """Seed `base` blocks plus a source file per block path under ibl5/ on origin/master,
    commit `branch` blocks (and delete each `rm` file) on feat, capture PRE, then stand in
    for a rebase whose resolution kept `head` blocks. `keep_root` paths are seeded at the
    repo root instead of under ibl5/ (the prefix fixture). Returns the script's stdout."""
    seed_base(repo, baseline_path, neon(*base))
    for b in base:
        p = b[4].split("path: ", 1)[1]
        target = p if p in keep_root else f"ibl5/{p}"
        seed_base(repo, target, "<?php\n")
    for f in rm:
        _commit_rm(repo, f, f"rm {f}")
    # Sentinel branch change: when the resolution restores master's copy of the baseline,
    # the post diff would otherwise be empty and the script would stop for the wrong reason.
    _commit(repo, "ibl5/branch-note.txt", "work\n", "branch work")
    _commit(repo, baseline_path, neon(*branch), "trim baseline")
    capture_pre(repo, key)
    _commit(repo, baseline_path, neon(*head), "resolution kept master entries")
    return run_lostwork(repo, key)[1]


def test_baseline_block_with_absent_path_is_exempt(repo, key):
    out = baseline_case(repo, key, base=[X, Y], branch=[Y], head=[X, Y], rm=["ibl5/modules/Foo/index.php"])
    assert "LOST:" not in out and last_line(out) == "TREE-EQUIVALENT"


def test_baseline_block_with_present_path_dropped_is_lost(repo, key):
    out = baseline_case(repo, key, base=[X, Y], branch=[Y], head=[X, Y])
    assert "LOST: ibl5/phpstan-baseline.neon: -\t\t\tpath: modules/Foo/index.php" in out
    assert last_line(out) == DIVERGED


def test_baseline_mixed_blocks_only_present_path_lost(repo, key):
    out = baseline_case(repo, key, base=[X, Y], branch=[], head=[X, Y], rm=["ibl5/modules/Foo/index.php"])
    assert "path: modules/Bar/index.php" in out and "path: modules/Foo/index.php" not in out
    assert "bar.live" in out and "foo.dead" not in out
    assert last_line(out) == DIVERGED


def test_baseline_exemption_checks_ibl5_prefix(repo, key):
    # Foo exists at HEAD:modules/Foo/index.php (repo root) and NOT at HEAD:ibl5/modules/Foo/index.php.
    out = baseline_case(repo, key, base=[X, Y], branch=[Y], head=[X, Y], keep_root=["modules/Foo/index.php"])
    assert "LOST:" not in out and last_line(out) == "TREE-EQUIVALENT"


def test_baseline_shared_line_text_in_exempt_block_is_exempt(repo, key):
    X2 = neon_block("Call to foo() on dead module.", "bar.live", 2, "modules/Foo/index.php")
    out = baseline_case(repo, key, base=[X2, Y], branch=[Y], head=[X2, Y], rm=["ibl5/modules/Foo/index.php"])
    assert "LOST:" not in out and last_line(out) == "TREE-EQUIVALENT"


def test_non_baseline_neon_is_not_exempt(repo, key):
    out = baseline_case(repo, key, base=[X, Y], branch=[Y], head=[X, Y],
                        baseline_path="ibl5/phpstan-tests-baseline.neon", rm=["ibl5/modules/Foo/index.php"])
    assert "LOST: ibl5/phpstan-tests-baseline.neon: -\t\t\tpath: modules/Foo/index.php" in out
    assert last_line(out) == DIVERGED


LW_NEON = "eval \"$(sed -n '/^lw_neon() {/,/^}/p' \"$1\")\"; shift; lw_neon \"$@\""


def lw_neon(*args):
    """Run the script's parser function alone: `lw_neon paths <file>` or `lw_neon split ...`."""
    return subprocess.run(["bash", "-c", LW_NEON, "_", str(SCRIPT), *args],
                          capture_output=True, text=True)


def broken(block):
    """The block with its count line unparseable (`count: one`)."""
    return block[:3] + ["\t\t\tcount: one"] + block[4:]


@pytest.mark.parametrize("side", ["head", "master"])
def test_malformed_baseline_falls_back_to_strict(repo, key, side):
    # Foo is deleted, so X would be exempt; the malformed copy disables the exemption.
    base = [broken(X), Y] if side == "master" else [X, Y]
    head = [broken(X), Y] if side == "head" else [X, Y]
    out = baseline_case(repo, key, base=base, branch=[Y], head=head, rm=["ibl5/modules/Foo/index.php"])
    assert "path: modules/Foo/index.php" in out
    assert last_line(out) == DIVERGED


def test_real_baseline_parses_as_blocks():
    real = SCRIPT.parents[3] / "ibl5" / "phpstan-baseline.neon"
    body = real.read_text()
    r = lw_neon("paths", str(real))
    assert r.returncode == 0, r.stderr
    assert len(r.stdout.splitlines()) == sum(1 for l in body.splitlines() if l == "\t\t-") > 0


@pytest.mark.parametrize("bad", [
    "parameters:\n",                                                   # zero blocks
    "parameters:\n    ignoreErrors:\n" + neon(X)[len("parameters:\n\tignoreErrors:\n"):],  # space indent
    neon(broken(X)),                                                   # count not digits
    neon(X[:4] + [""]),                                                # path line missing
    neon(neon_block("m", "i", 1, "../escape.php")),                    # path with ..
    neon(X)[:-1] + "\t\t-\n",                                          # trailing opener, no block
    neon(X).replace("\t\t-\n", "", 1),                                 # block without opener
], ids=["zero blocks", "space indent", "count not digits", "path missing", "dotdot path",
        "trailing opener", "missing opener"])
def test_lw_neon_rejects_malformed_block(tmp_path, bad):
    f = tmp_path / "b.neon"
    f.write_text(bad)
    r = lw_neon("paths", str(f))
    assert r.returncode == 1 and r.stdout.strip() == ""


def test_lw_neon_accepts_multiline_raw_message(tmp_path):
    # PHPStan writes a multi-line message as a `'''` block; the real baseline has three.
    ml = [
        "\t\t-",
        "\t\t\trawMessage: '''",
        "\t\t\t\tCall to deprecated function OpenTable():",
        "\t\t\t\tUse CSS classes directly.",
        "\t\t\t'''",
        "\t\t\tidentifier: function.deprecated",
        "\t\t\tcount: 1",
        "\t\t\tpath: classes/Foo.php",
        "",
    ]
    f = tmp_path / "b.neon"
    f.write_text(neon(ml, X))
    r = lw_neon("paths", str(f))
    assert r.returncode == 0, r.stderr
    assert r.stdout.splitlines() == ["classes/Foo.php", "modules/Foo/index.php"]
    f.write_text(neon(ml[:4] + ml[5:], X))  # unterminated `'''` block swallows the rest
    r = lw_neon("paths", str(f))
    assert r.returncode == 1 and r.stdout.strip() == ""
