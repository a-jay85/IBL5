"""Unit tests for harness/adaptations.py: LOST-line parsing, master transforms, near-match."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adaptations import (
    DIVERGED_VERDICT,
    MAX_ADAPTED_LINES,
    LostLine,
    NearMatch,
    Transform,
    accept_deterministic,
    cross_check_pre_patch,
    derive_master_transforms,
    near_match,
    parse_lost_lines,
)

RESOLVED = ("bin/a.sh", "docs/b.md")


def proof(lost_lines, added=None, checked=True, verdict=DIVERGED_VERDICT):
    out = [f"LOST: {ln}" for ln in lost_lines]
    if checked:
        n = len(lost_lines) if added is None else added
        out.append(f"CHECKED: files=2 added={n} deleted=0")
    out.append(verdict)
    return "\n".join(out) + "\n"


class _DiffRun:
    """Stub for the conflict.py `run` helper: canned text keyed on the first git arg."""

    def __init__(self, diffs=None, head=None, master=None):
        self.diffs = diffs or {}
        self.head = head or {}
        self.master = master or {}
        self.calls = []

    def __call__(self, *args, check=True):
        self.calls.append((args, check))
        if args[0] == "diff":
            return self.diffs.get(args[-1], "")
        if args[0] == "show":
            rev, path = args[1].split(":", 1)
            table = self.head if rev == "HEAD" else self.master
            return table.get(path, "")
        raise AssertionError(f"unexpected git call: {args}")


def hunk(minus, plus, header="@@ -3 +3 @@"):
    return "\n".join([header, *[f"-{m}" for m in minus], *[f"+{p}" for p in plus]]) + "\n"


DIFF_HEAD = (
    "diff --git a/bin/a.sh b/bin/a.sh\n"
    "index 111..222 100644\n"
    "--- a/bin/a.sh\n"
    "+++ b/bin/a.sh\n"
)


# -- parse_lost_lines ---------------------------------------------------------


def test_parse_lost_lines_accepts_plus_lines_in_resolved_files():
    out = proof(["bin/a.sh: +run /tmp/new.log", "docs/b.md: +see /tmp/x\r"])
    lines, reason = parse_lost_lines(out, RESOLVED)
    assert reason == ""
    assert lines == (
        LostLine("bin/a.sh", "run /tmp/new.log"),
        LostLine("docs/b.md", "see /tmp/x"),
    )


def test_parse_lost_lines_rejects_minus_kind_and_file_missing_kinds():
    for bad in (
        "bin/a.sh: -old line",
        "deletion lost, still at HEAD: bin/a.sh",
        "file missing at HEAD: bin/a.sh",
        "could not read the pre patch",
    ):
        out = proof(["bin/a.sh: +run /tmp/new.log", bad], added=5)
        lines, reason = parse_lost_lines(out, RESOLVED)
        assert lines == (), bad
        assert reason, bad


def test_parse_lost_lines_rejects_path_outside_resolved_files():
    out = proof(["bin/a.sh: +run /tmp/new.log", "other/c.sh: +run /tmp/z"])
    lines, reason = parse_lost_lines(out, RESOLVED)
    assert lines == ()
    assert reason
    # The same line is fine once its file is a resolved file.
    lines, reason = parse_lost_lines(out, RESOLVED + ("other/c.sh",))
    assert reason == ""
    assert len(lines) == 2


def test_parse_lost_lines_prefers_longest_resolved_path():
    out = proof(["a: +x1"], added=1)
    lines, reason = parse_lost_lines(out, ("a",))
    assert reason == "" and lines == (LostLine("a", "x1"),)
    out = proof(["a/b: +x1"], added=1)
    lines, reason = parse_lost_lines(out, ("a", "a/b"))
    assert reason == "" and lines == (LostLine("a/b", "x1"),)


def test_parse_lost_lines_rejects_degraded_proof_without_checked_line():
    lines, reason = parse_lost_lines(proof(["bin/a.sh: +run /tmp/n"], checked=False), RESOLVED)
    assert lines == () and reason
    degraded = proof(["bin/a.sh: +run /tmp/n"], verdict="TREE DIVERGED — proof input degraded")
    lines, reason = parse_lost_lines(degraded, RESOLVED)
    assert lines == () and reason
    lines, reason = parse_lost_lines(proof([]), RESOLVED)
    assert lines == () and reason


def test_parse_lost_lines_rejects_over_cap_and_count_mismatch():
    many = [f"bin/a.sh: +line{i}" for i in range(MAX_ADAPTED_LINES + 1)]
    lines, reason = parse_lost_lines(proof(many), RESOLVED)
    assert lines == () and reason
    ok = many[:MAX_ADAPTED_LINES]
    lines, reason = parse_lost_lines(proof(ok), RESOLVED)
    assert reason == "" and len(lines) == MAX_ADAPTED_LINES
    # More LOST lines than the CHECKED added count.
    lines, reason = parse_lost_lines(proof(["bin/a.sh: +one1", "bin/a.sh: +two2"], added=1), RESOLVED)
    assert lines == () and reason
    # A LOST body that carries a control char or no alphanumeric never parses.
    for body in ("bad\x07char1", "}}}"):
        lines, reason = parse_lost_lines(proof([f"bin/a.sh: +{body}"], added=1), RESOLVED)
        assert lines == () and reason, body


# -- cross_check_pre_patch ----------------------------------------------------


def test_pre_patch_cross_check_rejects_body_absent_from_pre_patch():
    patch = (
        "diff --git a/bin/a.sh b/bin/a.sh\n"
        "--- a/bin/a.sh\n"
        "+++ b/bin/a.sh\n"
        "@@ -1 +1,2 @@\n"
        " keep\n"
        "+run /tmp/new.log\r\n"
        "diff --git a/docs/b.md b/docs/b.md\n"
        "--- a/docs/b.md\n"
        "+++ b/docs/b.md\n"
        "@@ -1 +1,2 @@\n"
        "+see /tmp/x\n"
    ).encode()
    good = (LostLine("bin/a.sh", "run /tmp/new.log"), LostLine("docs/b.md", "see /tmp/x"))
    assert cross_check_pre_patch(patch, good) == ""
    # Body never added at all.
    reason = cross_check_pre_patch(patch, (LostLine("bin/a.sh", "run /tmp/other.log"),))
    assert "run /tmp/other.log" in reason
    # Body added, but to a different path.
    reason = cross_check_pre_patch(patch, (LostLine("docs/b.md", "run /tmp/new.log"),))
    assert "docs/b.md" in reason
    # The `+++` header is never itself a body, and `+++ /dev/null` clears the path.
    gone = b"--- a/x\n+++ /dev/null\n+orphan1\n"
    assert cross_check_pre_patch(gone, (LostLine("x", "orphan1"),)) != ""
    assert cross_check_pre_patch(patch, (LostLine("bin/a.sh", "b/bin/a.sh"),)) != ""


# -- derive_master_transforms -------------------------------------------------


def test_derive_transforms_pairs_equal_runs_and_strips_common_affixes():
    diff = DIFF_HEAD + hunk(
        ["cp /tmp/d /tmp/d2", "echo /var/x done"],
        ['cp /tmp/d "$RL"/d2', "echo /srv/x done"],
    )
    run = _DiffRun(diffs={"bin/a.sh": diff})
    got = derive_master_transforms(run, path="bin/a.sh", merge_base="MB", master_sha="M")
    # Common prefix and suffix are stripped: only the minimal differing window remains.
    assert got == (Transform("/tmp", '"$RL"'), Transform("var", "srv"))
    assert run.calls[0][0] == ("diff", "-U0", "--no-color", "MB", "M", "--", "bin/a.sh")
    # Duplicates collapse, first-seen order kept; file-header lines before @@ are skipped.
    diff2 = (
        DIFF_HEAD
        + hunk(["a /tmp/q z"], ['a "$RL"/q z'])
        + hunk(["b /tmp/q y"], ['b "$RL"/q y'], header="@@ -9 +9 @@")
    )
    got = derive_master_transforms(
        _DiffRun(diffs={"bin/a.sh": diff2}), path="bin/a.sh", merge_base="MB", master_sha="M"
    )
    assert got == (Transform("/tmp", '"$RL"'),)


def test_derive_transforms_skips_unequal_hunks_and_insert_only_pairs():
    unequal = DIFF_HEAD + hunk(["one /tmp/a", "two /tmp/b"], ['one "$RL"/a'])
    insert_only = DIFF_HEAD + "@@ -3,0 +4 @@\n+brand new /tmp/line\n"
    delete_only = DIFF_HEAD + "@@ -3 +2,0 @@\n-gone /tmp/line\n"
    # A pair whose stripped old side has no alphanumeric is dropped; so is a pure append.
    punct_only = DIFF_HEAD + hunk(["foo ;"], ["foo ;;"])
    append_only = DIFF_HEAD + hunk(["foo"], ["foo bar"])
    for diff in (unequal, insert_only, delete_only, punct_only, append_only):
        got = derive_master_transforms(
            _DiffRun(diffs={"p": diff}), path="p", merge_base="MB", master_sha="M"
        )
        assert got == (), diff


# -- near_match ---------------------------------------------------------------

T_TMP = Transform("/tmp/", '"$RL"/')
T_LOG = Transform("new.log", "new.out")


def test_near_match_applies_single_and_chained_transforms_replace_all():
    original = "run /tmp/new.log"
    head = frozenset({'run "$RL"/new.log'})
    got = near_match(original, (T_TMP,), head)
    assert got == NearMatch("", original, 'run "$RL"/new.log', (T_TMP,))
    # A chain of two transforms: neither alone reaches the HEAD line.
    head2 = frozenset({'run "$RL"/new.out'})
    got = near_match(original, (T_TMP, T_LOG), head2)
    assert got is not None
    assert got.adapted == 'run "$RL"/new.out'
    assert got.transforms == (T_TMP, T_LOG)
    # str.replace rewrites every occurrence.
    twice = "cp /tmp/a /tmp/b"
    got = near_match(twice, (T_TMP,), frozenset({'cp "$RL"/a "$RL"/b'}))
    assert got is not None and got.adapted == 'cp "$RL"/a "$RL"/b'


def test_near_match_returns_none_when_no_head_line_matches():
    original = "run /tmp/new.log"
    assert near_match(original, (T_TMP,), frozenset({"unrelated line"})) is None
    assert near_match(original, (), frozenset({original})) is None
    # The original itself sitting at HEAD is not an adaptation.
    assert near_match(original, (Transform("zzz", "yyy"),), frozenset({original})) is None


def test_near_match_rejects_partial_occurrence_rewrite():
    original = "cp /tmp/a /tmp/b"
    partial = frozenset({'cp "$RL"/a /tmp/b'})
    assert near_match(original, (T_TMP,), partial) is None


# -- accept_deterministic -----------------------------------------------------

BASE_DIFF = DIFF_HEAD + hunk(["cp /tmp/d /tmp/d2"], ['cp /tmp/d "$RL"/d2'])
T_D2 = Transform("/tmp", '"$RL"')


def _accept_run(head_text, master_text, diff=BASE_DIFF):
    return _DiffRun(
        diffs={"bin/a.sh": diff}, head={"bin/a.sh": head_text}, master={"bin/a.sh": master_text}
    )


def _call(run, lost):
    return accept_deterministic(run, lost=lost, merge_base="MB", master_sha="M")


def test_accept_deterministic_requires_head_count_to_exceed_master_count():
    lost = (LostLine("bin/a.sh", "run /tmp/d2"),)
    master = 'cp /tmp/d "$RL"/d2\nrun "$RL"/d2\n'
    # Master already held the adapted line once and HEAD holds it once: not the branch's.
    matches, reason = _call(_accept_run(master, master), lost)
    assert matches == () and reason
    # HEAD holds it twice (master's copy plus the branch's own): accepted.
    matches, reason = _call(_accept_run(master + 'run "$RL"/d2\n', master), lost)
    assert reason == ""
    assert matches == (NearMatch("bin/a.sh", "run /tmp/d2", 'run "$RL"/d2', (T_D2,)),)
    # Master lacks the file entirely (check=False yields ""): one HEAD copy suffices.
    run = _accept_run('run "$RL"/d2\n', "")
    matches, reason = _call(run, lost)
    assert reason == "" and len(matches) == 1
    assert (("show", "M:bin/a.sh"), False) in run.calls
    # Two LostLines claiming one adapted line need two surplus HEAD copies.
    twin = (LostLine("bin/a.sh", "run /tmp/d2"), LostLine("bin/a.sh", "run /tmp/d2"))
    matches, reason = _call(_accept_run('run "$RL"/d2\n', ""), twin)
    assert matches == () and reason
    matches, reason = _call(_accept_run('run "$RL"/d2\nrun "$RL"/d2\n', ""), twin)
    assert reason == "" and len(matches) == 2


def test_accept_deterministic_rejects_edit_master_did_not_introduce():
    lost = (LostLine("bin/a.sh", "run /tmp/d2"), LostLine("bin/a.sh", "wholly different1"))
    head = 'run "$RL"/d2\nwholly changed1\n'
    # The first line adapts; the second has no master transform behind its HEAD line.
    matches, reason = _call(_accept_run(head, ""), lost)
    assert matches == ()
    assert reason == "no near-match for bin/a.sh: +wholly different1"
    # With no master edit at all there is nothing to apply, so even a plausible HEAD line fails.
    matches, reason = _call(_accept_run('run "$RL"/d2\n', "", diff=""), lost[:1])
    assert matches == () and reason == "no near-match for bin/a.sh: +run /tmp/d2"


def test_parse_justifications_rejects_missing_keys_and_non_object():
    import json
    from harness.adaptations import ADAPTED_PREFIX, Justification, parse_justifications

    def reply(obj):
        return f"{ADAPTED_PREFIX}{json.dumps(obj)}\nRESOLVED"

    good = {"from": "run /tmp/a", "to": 'run "$RL"/a', "old": "/tmp/", "new": '"$RL"/'}
    bad = [
        ["from", "to", "old", "new"],
        {k: v for k, v in good.items() if k != "new"},
        {**good, "extra": "x"},
        {**good, "old": ""},
        {**good, "to": good["from"]},
    ]
    for obj in bad:
        found, reason = parse_justifications(reply(obj), "bin/a.sh")
        assert found == () and reason, obj
    found, reason = parse_justifications(reply(good), "bin/a.sh")
    assert reason == ""
    assert found == (Justification("bin/a.sh", "run /tmp/a", 'run "$RL"/a', "/tmp/", '"$RL"/'),)
    assert parse_justifications("RESOLVED", "bin/a.sh") == ((), "")


def test_audit_lines_render_every_accepted_adaptation_and_summary():
    from harness.adaptations import AcceptedAdaptation, audit_lines_for_adaptations
    a = AcceptedAdaptation("bin/a", 'x > "/tmp/a"', 'x > "$RL/a"', "/tmp", "$RL")
    b = AcceptedAdaptation("bin/b", "call(old_n)", "call(new_n)", "old_", "new_")
    lines = audit_lines_for_adaptations((a, b))
    assert len(lines) == 3
    for line, e in zip(lines, (a, b)):
        assert line.startswith("adapted line accepted: ")
        assert f"{e.path}: +{e.original} => +{e.adapted} [{e.old} -> {e.new}]" in line
    assert lines[2].startswith("adapted-lines=2 accepted")
    assert audit_lines_for_adaptations(()) == ()
