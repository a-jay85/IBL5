"""Label decisions: classify, diff line maps, nit cap, ordering, body marker.

Each test names the mutation it exists to kill.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import labels
from harness.state import Finding

TH = 80


def _f(**over):
    base = dict(source="code-review", agent="A", path="a.py", line=10, body="b", score=85)
    base.update(over)
    return Finding(**base)


def test_classify_threshold_finding_with_nit_hint_is_important():
    """Mutation: consult severity_hint before the threshold check => fails."""
    f = _f(score=90, severity_hint="nit")
    assert labels.classify(f, TH, {}) == "important"


def test_classify_preexisting_requires_reviewer_and_scorer_and_unchanged_line():
    """Mutation: drop any one conjunct => the matching case fails."""
    changed = {"a.py": {5}}
    both = _f(line=10, preexisting_hint=True, scorer_preexisting=True)
    assert labels.classify(both, TH, changed) == "pre-existing"
    reviewer_only = _f(line=10, preexisting_hint=True, scorer_preexisting=False)
    assert labels.classify(reviewer_only, TH, changed) == "important"
    scorer_only = _f(line=10, preexisting_hint=False, scorer_preexisting=True)
    assert labels.classify(scorer_only, TH, changed) == "important"
    on_added = _f(line=5, preexisting_hint=True, scorer_preexisting=True)
    assert labels.classify(on_added, TH, changed) == "important"


def test_classify_line_zero_never_preexisting():
    """Mutation: remove `f.line > 0` => fails."""
    f = _f(line=0, preexisting_hint=True, scorer_preexisting=True)
    assert labels.classify(f, TH, {"a.py": {5}}) == "important"


def test_classify_new_file_never_preexisting():
    """Mutation: drop the `--- /dev/null` tracking => an anchor past the hunk reads pre-existing."""
    diff = ("diff --git a/a.py b/a.py\nnew file mode 100644\n--- /dev/null\n+++ b/a.py\n"
            "@@ -0,0 +1,2 @@\n+x\n+y\n")
    changed = labels.changed_right_lines(diff)
    f = _f(line=55, preexisting_hint=True, scorer_preexisting=True)
    assert labels.classify(f, TH, changed) == "important"


def test_classify_security_never_nit():
    """Mutation: delete the source check => fails."""
    f = _f(source="security-audit", score=70, severity_hint="nit")
    assert labels.classify(f, TH, {}) is None


def test_classify_nit_band_boundaries():
    """Mutation: change `>=` to `>` on NIT_FLOOR => the 60 case fails."""
    def label(score):
        return labels.classify(_f(score=score, severity_hint="nit"), TH, {})
    assert label(59) is None
    assert label(60) == "nit"
    assert label(79) == "nit"
    assert label(80) == "important"


def test_classify_below_threshold_without_nit_hint_is_dropped():
    """Mutation: default severity_hint to nit => fails."""
    assert labels.classify(_f(score=70), TH, {}) is None
    assert labels.classify(_f(score=70, severity_hint="bogus"), TH, {}) is None


DIFF = """\
diff --git a/one.py b/one.py
index 111..222 100644
--- a/one.py
+++ b/one.py
@@ -1,4 +1,4 @@
 ctx1
-old2
+new2
 ctx3
 ctx4
@@ -20,2 +20,3 @@
 ctx20
+add21
 ctx22
\\ No newline at end of file
diff --git a/two.py b/two.py
--- a/two.py
+++ b/two.py
@@ -0,0 +1,2 @@
+first
+second
"""


def test_changed_right_lines_tracks_hunks_and_skips_deletions():
    """Mutation: advance `right` on `-` lines => fails."""
    changed = labels.changed_right_lines(DIFF)
    assert changed == {"one.py": {2, 21}, "two.py": {1, 2}}
    visible = labels.visible_right_lines(DIFF)
    assert visible == {"one.py": {1, 2, 3, 4, 20, 21, 22}, "two.py": {1, 2}}
    for path, lines in changed.items():
        assert lines <= visible[path]


def test_nit_cap_keeps_top_five_by_score_and_all_non_nits():
    """Mutation: set NIT_CAP = 6 => fails."""
    nit_scores = [60, 75, 61, 79, 70, 62, 65]
    items = [_f(path=f"n{i}.py", score=s, label="nit") for i, s in enumerate(nit_scores)]
    items += [_f(path="i1.py", label="important"), _f(path="i2.py", label="important"),
              _f(path="p.py", label="pre-existing")]
    kept = labels.apply_nit_cap(items)
    assert len(kept) == 8
    kept_nits = sorted((f.score for f in kept if f.label == "nit"), reverse=True)
    assert kept_nits == [79, 75, 70, 65, 62]
    assert [f.path for f in kept if f.label != "nit"] == ["i1.py", "i2.py", "p.py"]


def test_sort_by_severity_order_and_score_tiebreak():
    """Mutation: swap SEVERITY_ORDER for nit and pre-existing => fails."""
    items = [
        _f(path="pre.py", label="pre-existing", score=99),
        _f(path="nit.py", label="nit", score=79),
        _f(path="imp_lo.py", label="important", score=81),
        _f(path="imp_hi.py", label="important", score=95),
        _f(path="imp_tie.py", label="important", score=95),
    ]
    assert [f.path for f in labels.sort_by_severity(items)] == [
        "imp_hi.py", "imp_tie.py", "imp_lo.py", "nit.py", "pre.py"]


def test_render_body_marker_survives_1000_char_truncation():
    """Mutation: append the marker at the end instead => fails."""
    f = _f(label="pre-existing", score=91, body="x" * 3000,
           bug_class="unquoted-glob", other_instances=["b.py:3", "c.py:9"])
    out = labels.render_body(f)[:1000]
    assert out.startswith("<!-- label: pre-existing -->\n")
    assert "<!-- score: 91 -->" in out
    assert "**Pre-existing**" in out
    assert "bug class: `unquoted-glob`" in out
    assert "other instances: `b.py:3`, `c.py:9`" in out
    assert len(labels.render_body(f)) <= 1000
    assert labels.parse_label(out) == "pre-existing"


def test_render_body_caps_header_with_many_other_instances():
    """Mutation: leave the header uncapped => total passes 1000 and the body vanishes."""
    f = _f(label="important", score=91, body="y" * 3000, bug_class="unbounded-fetch",
           other_instances=[f"ibl5/classes/Some/Long/Path{i}.php:{i}" for i in range(20)])
    out = labels.render_body(f)
    assert len(out) <= 1000
    assert out.startswith("<!-- label: important -->\n<!-- score: 91 -->\n**Important**")
    assert "yyyy" in out
    assert labels.parse_label(out) == "important"


def test_parse_label_rejects_marker_not_on_first_line_and_unknown_label():
    """Mutation: use re.search instead of \\A => fails."""
    assert labels.parse_label("<!-- label: nit -->\nbody") == "nit"
    assert labels.parse_label("intro\n<!-- label: nit -->\nbody") is None
    assert labels.parse_label("<!-- label: blocker -->\nbody") is None
    assert labels.parse_label("") is None
    assert labels.parse_label(None) is None


def test_classify_path_absent_from_diff_is_important():
    """A path missing from the diff never reads changed[path]; it stays important.

    Mutation: use `changed.get(f.path, set())` for the unchanged-line check => fails."""
    f = _f(path="not_in_diff.py", line=10, preexisting_hint=True, scorer_preexisting=True)
    assert labels.classify(f, TH, {"a.py": {5}}) == "important"
