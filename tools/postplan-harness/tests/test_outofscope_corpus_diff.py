"""Corpus diff for the out-of-scope deferral extractor.

The two local-corpus tests run only with POSTPLAN_CORPUS_DIFF=1 and a local plan corpus;
CI sets neither, so they skip there. The false-positive-rate test reads only the committed
fixture, so it always runs.

The fixture was reviewed by hand against every hit. REJECTION_RE arms were added against
labelled false positives during that review, so the measured rate is a tuned-on-sample rate.
"""
import datetime
import glob
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.outofscope import extract_deferral_hits  # noqa: E402

CORPUS_SCAN_DATE = datetime.date(2026, 10, 1)
FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       "fixtures", "outofscope", "corpus_hits.tsv")
FP_BAR = 0.15
MIN_REVIEWED = 40


def _read_fixture():
    headers, rows = [], []
    with open(FIXTURE, encoding="utf-8") as fh:
        for line in fh:
            line = line.rstrip("\n")
            if line.startswith("#"):
                headers.append(line)
            elif line:
                stem, line_no, key, label, text = line.split("\t", 4)
                rows.append((stem, int(line_no), key, label, text))
    return headers, rows


def _corpus_paths():
    plans_dir = os.environ.get("PLANS_DIR") or os.path.expanduser("~/claude-plans")
    if not os.path.isdir(plans_dir):
        pytest.skip(f"SKIP: no plan corpus at {plans_dir}")
    paths = [p for p in sorted(glob.glob(os.path.join(plans_dir, "*.md")))
             if not p.endswith("-shared-context.md")]
    _, rows = _read_fixture()
    stems = {os.path.basename(p)[:-3] for p in paths}
    if not ({r[0] for r in rows} & stems):
        pytest.skip(f"SKIP: {plans_dir} holds none of the {CORPUS_SCAN_DATE} reference plans")
    return paths


def _pre_scan(path):
    return datetime.date.fromtimestamp(os.path.getmtime(path)) < CORPUS_SCAN_DATE


def test_local_corpus_hit_set_matches_scan():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    paths = _corpus_paths()
    pre_scan = {os.path.basename(p)[:-3] for p in paths if _pre_scan(p)}
    expected = {(r[0], r[2]) for r in _read_fixture()[1] if r[0] in pre_scan}
    actual, new_since_scan = set(), set()
    for p in paths:
        stem = os.path.basename(p)[:-3]
        with open(p, encoding="utf-8") as fh:
            hits = extract_deferral_hits(fh.read(), stem)
        if stem in pre_scan:
            actual |= {(stem, h.key) for h in hits}
        elif hits:
            new_since_scan.add(stem)
    print(f"scanned={len(paths)} hit_plans={len({s for s, _ in actual})} hits={len(actual)} "
          f"new-since-scan={sorted(new_since_scan)}")
    assert actual - expected == set(), f"unexpected hits on pre-scan plans: {sorted(actual - expected)}"
    assert expected - actual == set(), f"expected hits missing: {sorted(expected - actual)}"


def test_local_corpus_rejection_preamble_not_hit():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    paths = _corpus_paths()
    target = [p for p in paths if os.path.basename(p) == "auto-promote-master-to-production.md"]
    if not target:
        pytest.skip("SKIP: auto-promote-master-to-production.md not in the corpus")
    with open(target[0], encoding="utf-8") as fh:
        hits = extract_deferral_hits(fh.read(), "auto-promote-master-to-production")
    assert all("resolved decision" not in h.text.lower() for h in hits)


def test_corpus_fixture_false_positive_rate_within_bound():
    headers, rows = _read_fixture()
    assert len(headers) >= 2
    meta = dict(kv.split("=") for kv in headers[1].lstrip("# ").split())
    reviewed, false_positives = int(meta["reviewed"]), int(meta["false_positives"])
    assert reviewed >= MIN_REVIEWED
    labelled = [r for r in rows if r[3] in ("D", "R", "B")]
    assert len(labelled) == reviewed
    assert sum(1 for r in labelled if r[3] in ("R", "B")) == false_positives
    assert false_positives / reviewed <= FP_BAR
