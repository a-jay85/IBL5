"""Tests for harness.approve_deviation (the bin/approve-deviation writer)."""
from __future__ import annotations

import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import approve_deviation as ad
from harness import owner_approvals as oa

DIFF = """\
diff --git a/ibl5/classes/Foo.php b/ibl5/classes/Foo.php
index 111..222 100644
--- a/ibl5/classes/Foo.php
+++ b/ibl5/classes/Foo.php
@@ -3 +3 @@
-    return $this->oldBehaviour();
+    return $this->newBehaviour();
@@ -9,0 +10,2 @@
+    }
+    $this->audit('approved');
"""

DELETE_DIFF = """\
diff --git a/ibl5/classes/Gone.php b/ibl5/classes/Gone.php
deleted file mode 100644
--- a/ibl5/classes/Gone.php
+++ /dev/null
@@ -1,3 +0,0 @@
-final class WatchdogThing {}
-}
-    $watchdog->run();
"""

TRIVIAL_DIFF = """\
diff --git a/x.php b/x.php
--- a/x.php
+++ b/x.php
@@ -1,0 +2,2 @@
+}
+{
"""


class FakeGh:
    """Records every call; serves a mutable list of existing comments."""

    def __init__(self, identity="a-jay85 User", existing=None):
        self.identity = identity
        self.existing = list(existing or [])
        self.calls: list[list[str]] = []

    def __call__(self, args):
        self.calls.append(list(args))
        if args[:2] == ["api", "user"]:
            return self.identity + "\n"
        if "--method" in args:
            method = args[args.index("--method") + 1]
            body = next(a for a in args if a.startswith("body="))[len("body="):]
            if method == "POST":
                cid = 1000 + len(self.existing)
                self.existing.append({"id": cid, "body": body, "login": "a-jay85", "type": "User"})
                return f"{cid}\n"
            if method == "PATCH":
                cid = int(args[3].rsplit("/", 1)[1])
                for row in self.existing:
                    if row["id"] == cid:
                        row["body"] = body
                return ""
        return "".join(json.dumps(r) + "\n" for r in self.existing)

    def writes(self):
        return [c for c in self.calls if "--method" in c]


@pytest.fixture
def diff_file(tmp_path):
    p = tmp_path / "change.diff"
    p.write_text(DIFF)
    return str(p)


def _run(argv, gh, git=None):
    return ad.main(argv, run_gh=gh, run_git=git or (lambda a: ""))


def test_anchors_from_unified_diff_splits_keep_and_gone():
    got = ad.anchors_from_unified_diff(DIFF)
    keep, gone = got["ibl5/classes/Foo.php"]
    assert keep == ["return $this->newBehaviour();", "$this->audit('approved');"]
    assert gone == ["return $this->oldBehaviour();"]


def test_deletion_only_file_keeps_path_from_minus_header():
    got = ad.anchors_from_unified_diff(DELETE_DIFF)
    assert list(got) == ["ibl5/classes/Gone.php"]
    keep, gone = got["ibl5/classes/Gone.php"]
    assert keep == []
    assert gone == ["final class WatchdogThing {}", "$watchdog->run();"]


def test_no_nontrivial_anchor_exits_2(tmp_path, capsys):
    p = tmp_path / "t.diff"
    p.write_text(TRIVIAL_DIFF)
    gh = FakeGh()
    rc = _run(["--pr", "5", "--diff", str(p), "--summary", "s"], gh)
    assert rc == 2
    assert "no non-trivial anchor lines in the approved change" in capsys.readouterr().err
    assert gh.calls == []


def test_record_id_is_content_deterministic():
    a = {"a.php": (["keep one", "keep two"], ["gone one"]), "b.php": ([], ["gone two"])}
    b = {"b.php": ([], ["gone two"]), "a.php": (["keep two", "keep one"], ["gone one"])}
    assert ad.record_id(a) == ad.record_id(b)
    assert ad.record_id(a).startswith("oad-")
    assert ad.record_id(a) != ad.record_id({"a.php": (["keep one"], [])})


def test_rendered_body_round_trips_through_parse_record():
    files = ad.anchors_from_unified_diff(DIFF)
    rid = ad.record_id(files)
    body = ad.render_body(rid, "owner kept the new call", files, "abc1234")
    rec = oa.parse_record(body, 0)
    assert rec.id == rid
    assert rec.summary == "owner kept the new call"
    assert rec.files[0].path == "ibl5/classes/Foo.php"
    assert rec.files[0].keep == tuple(files["ibl5/classes/Foo.php"][0])
    assert rec.files[0].gone == tuple(files["ibl5/classes/Foo.php"][1])
    assert body.splitlines()[0] == oa.APPROVAL_MARKER


def test_non_owner_identity_exits_3_and_posts_nothing(diff_file):
    gh = FakeGh(identity="octocat User")
    rc = _run(["--pr", "5", "--diff", diff_file, "--summary", "s"], gh)
    assert rc == 3
    assert gh.writes() == []


def test_bot_type_owner_identity_exits_3(diff_file):
    gh = FakeGh(identity="a-jay85 Bot")
    rc = _run(["--pr", "5", "--diff", diff_file, "--summary", "s"], gh)
    assert rc == 3
    assert gh.writes() == []


def test_first_run_posts_once_second_run_unchanged(diff_file, capsys):
    gh = FakeGh()
    argv = ["--pr", "5", "--diff", diff_file, "--summary", "keep the new call"]
    assert _run(argv, gh) == 0
    assert len(gh.writes()) == 1
    assert "POST" in gh.writes()[0]
    capsys.readouterr()
    assert _run(argv, gh) == 0
    assert len(gh.writes()) == 1
    assert capsys.readouterr().out.startswith("unchanged ")


def test_changed_summary_patches_existing_comment(diff_file):
    gh = FakeGh()
    assert _run(["--pr", "5", "--diff", diff_file, "--summary", "first"], gh) == 0
    assert _run(["--pr", "5", "--diff", diff_file, "--summary", "second"], gh) == 0
    writes = gh.writes()
    assert len(writes) == 2
    assert "POST" in writes[0]
    assert "PATCH" in writes[1]
    assert writes[1][3].endswith(f"issues/comments/{gh.existing[0]['id']}")
    assert "second" in gh.existing[0]["body"]
    assert len(gh.existing) == 1


def test_duplicate_existing_ids_exit_4(diff_file):
    files = ad.anchors_from_unified_diff(DIFF)
    body = ad.render_body(ad.record_id(files), "dup", files, "diff")
    existing = [
        {"id": 1, "body": body, "login": "a-jay85", "type": "User"},
        {"id": 2, "body": body, "login": "a-jay85", "type": "User"},
    ]
    gh = FakeGh(existing=existing)
    rc = _run(["--pr", "5", "--diff", diff_file, "--summary", "dup"], gh)
    assert rc == 4
    assert gh.writes() == []


def test_non_owner_existing_comment_is_not_a_duplicate(diff_file):
    files = ad.anchors_from_unified_diff(DIFF)
    body = ad.render_body(ad.record_id(files), "x", files, "diff")
    gh = FakeGh(existing=[{"id": 1, "body": body, "login": "evil", "type": "User"}])
    assert _run(["--pr", "5", "--diff", diff_file, "--summary", "x"], gh) == 0
    assert len(gh.writes()) == 1


def test_dry_run_posts_nothing(diff_file, capsys):
    gh = FakeGh()
    rc = _run(["--pr", "5", "--diff", diff_file, "--summary", "s", "--dry-run"], gh)
    assert rc == 0
    assert gh.calls == []
    assert oa.APPROVAL_MARKER in capsys.readouterr().out


def test_paths_filter_limits_files(tmp_path, capsys):
    p = tmp_path / "two.diff"
    p.write_text(DIFF + DELETE_DIFF)
    rc = _run(["--pr", "5", "--diff", str(p), "--summary", "s",
               "--paths", "ibl5/classes/Gone.php", "--dry-run"], FakeGh())
    assert rc == 0
    out = capsys.readouterr().out
    assert "Gone.php" in out and "Foo.php" not in out


def test_unknown_flag_exits_2(diff_file):
    assert _run(["--pr", "5", "--diff", diff_file, "--summary", "s", "--bogus"], FakeGh()) == 2


def test_missing_pr_exits_2(diff_file):
    assert _run(["--diff", diff_file, "--summary", "s"], FakeGh()) == 2


def test_commit_mode_uses_git_show_unified_zero(tmp_path):
    def git(*a):
        subprocess.run(["git", "-C", str(tmp_path), *a], check=True, capture_output=True)

    git("init", "-q")
    git("config", "user.email", "t@example.com")
    git("config", "user.name", "t")
    target = tmp_path / "f.txt"
    target.write_text("context line one\ncontext line two\nold value here\ncontext line three\n")
    git("add", ".")
    git("commit", "-qm", "base")
    target.write_text("context line one\ncontext line two\nnew value here\ncontext line three\n")
    git("commit", "-qam", "change")
    sha = subprocess.run(["git", "-C", str(tmp_path), "rev-parse", "HEAD"], check=True,
                         capture_output=True, text=True).stdout.strip()
    seen: list[list[str]] = []

    def run_git(args):
        seen.append(list(args))
        return subprocess.run(["git", "-C", str(tmp_path), *args], check=True,
                              capture_output=True, text=True).stdout

    gh = FakeGh()
    rc = ad.main(["--pr", "5", "--commit", sha, "--summary", "s", "--dry-run"],
                 run_gh=gh, run_git=run_git)
    assert rc == 0
    assert seen[0][0] == "show"
    assert "--unified=0" in seen[0] and "--no-ext-diff" in seen[0] and sha in seen[0]
    files = ad.anchors_from_unified_diff(run_git(seen[0]))
    keep, gone = files["f.txt"]
    assert keep == ["new value here"]
    assert gone == ["old value here"]
