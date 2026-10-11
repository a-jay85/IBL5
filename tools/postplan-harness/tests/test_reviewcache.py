"""Tests for harness/reviewcache.py: record I/O, version hash, key and PR matching, model pins."""
from __future__ import annotations

import inspect
import json
import os
import re
import shutil
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.review
from harness import reviewcache as rc

REAL_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SLUG = "s"
TEXTS = ["procedure one", "procedure two"]


def _valid_record(**over):
    doc = {
        "schema_version": 1, "slug": "s", "pr_number": 2345, "run_id": "r1",
        "key": {"diff_id": "a", "plan_hash": "b", "version": "c",
                "pr_title": "t", "file_list": ["x.py"]},
        "per_file_ids": {"x.py": "d"},
        "review": None, "body_check": None, "fidelity": None,
    }
    doc.update(over)
    return doc


def _write_raw(state_dir, text):
    os.makedirs(state_dir, exist_ok=True)
    with open(rc.record_path(str(state_dir), SLUG), "w") as fh:
        fh.write(text)


@pytest.fixture
def hroot(tmp_path):
    root = tmp_path / "hroot"
    (root / "harness").mkdir(parents=True)
    for rel in rc.PROMPT_SOURCE_FILES:
        shutil.copy(os.path.join(REAL_ROOT, rel), root / rel)
    return root


def _vh(root, texts=None):
    return rc.version_hash(str(root), TEXTS if texts is None else texts)


def _key(**over):
    base = dict(diff_id="d1", plan_hash="p1", version="v1", pr_title="t", file_list=["a", "b"])
    base.update(over)
    return rc.make_key(**base)


# --------------------------------------------------------------------------- load / save / clear

def test_load_record_missing_file_returns_none(tmp_path):
    assert rc.load_record(str(tmp_path), SLUG) is None


def test_load_record_bad_json_returns_none(tmp_path):
    _write_raw(tmp_path, "{not json")
    assert rc.load_record(str(tmp_path), SLUG) is None


def test_load_record_schema_mismatch_returns_none(tmp_path):
    _write_raw(tmp_path, json.dumps(_valid_record(schema_version=0)))
    assert rc.load_record(str(tmp_path), SLUG) is None


def test_load_record_missing_required_key_returns_none(tmp_path):
    doc = _valid_record()
    del doc["per_file_ids"]
    _write_raw(tmp_path, json.dumps(doc))
    assert rc.load_record(str(tmp_path), SLUG) is None


def test_load_record_non_int_pr_number_returns_none(tmp_path):
    _write_raw(tmp_path, json.dumps(_valid_record(pr_number="2345")))
    assert rc.load_record(str(tmp_path), SLUG) is None


def test_save_then_load_roundtrip_and_no_tmp_left(tmp_path):
    doc = _valid_record()
    rc.save_record(str(tmp_path), SLUG, doc)
    assert rc.load_record(str(tmp_path), SLUG) == doc
    assert [p.name for p in tmp_path.iterdir() if p.name.endswith(".tmp")] == []
    with open(rc.record_path(str(tmp_path), SLUG)) as fh:
        text = fh.read()
    assert text == json.dumps(doc, indent=1, sort_keys=True)
    top_keys = list(json.loads(text).keys())
    assert top_keys == sorted(top_keys)


def test_save_swallows_oserror(tmp_path):
    not_a_dir = tmp_path / "file"
    not_a_dir.write_text("x")
    rc.save_record(str(not_a_dir), SLUG, _valid_record())  # must not raise
    assert not_a_dir.read_text() == "x"


def test_clear_record_idempotent(tmp_path):
    rc.save_record(str(tmp_path), SLUG, _valid_record())
    rc.clear_record(str(tmp_path), SLUG)
    assert rc.load_record(str(tmp_path), SLUG) is None
    rc.clear_record(str(tmp_path), SLUG)  # second clear: no raise


# --------------------------------------------------------------------------- version_hash

def test_version_hash_ignores_master_sha(hroot):
    first, second = _vh(hroot), _vh(hroot)
    assert first and first == second
    assert "master_sha" not in inspect.signature(rc.version_hash).parameters


def test_version_hash_changes_on_prompt_source_byte_change(hroot):
    before = _vh(hroot)
    with open(hroot / "harness" / "review.py", "ab") as fh:
        fh.write(b"\n#\n")
    assert _vh(hroot) != before


def test_version_hash_changes_on_procedure_text_change(hroot):
    assert _vh(hroot, ["procedure one", "procedure two"]) != _vh(hroot, ["procedure one ", "procedure two"])


def test_version_hash_changes_on_threshold_change(hroot, monkeypatch):
    before = _vh(hroot)
    monkeypatch.setattr(harness.review, "CODE_THRESHOLD", 81)
    assert _vh(hroot) != before


def test_version_hash_empty_when_source_missing(hroot):
    os.remove(hroot / "harness" / "schemas.py")
    assert _vh(hroot) == ""


# --------------------------------------------------------------------------- key / PR matching

@pytest.mark.parametrize("field", ["diff_id", "plan_hash", "version"])
def test_key_matches_requires_nonempty_diff_id_version_plan_hash(field):
    key = _key(**{field: ""})
    assert rc.key_matches({"key": dict(key)}, key) is False


@pytest.mark.parametrize("field,other", [
    ("diff_id", "d2"), ("plan_hash", "p2"), ("version", "v2"),
    ("pr_title", "t2"), ("file_list", ["a", "c"]),
])
def test_key_matches_rejects_any_field_change(field, other):
    assert set(rc.KEY_FIELDS) == {"diff_id", "plan_hash", "version", "pr_title", "file_list"}
    stored = _key()
    assert rc.key_matches({"key": stored}, _key()) is True
    assert rc.key_matches({"key": stored}, _key(**{field: other})) is False


def test_key_matches_file_list_order_insensitive():
    assert rc.key_matches({"key": _key(file_list=["b", "a"])}, _key(file_list=["a", "b"])) is True


def test_pr_matches_rejects_other_pr():
    rec = _valid_record(pr_number=1)
    assert rc.pr_matches(rec, 1) is True
    assert rc.pr_matches(rec, 2) is False


# --------------------------------------------------------------------------- model pins

def test_model_pins_match_source():
    sources = ""
    for rel in ("harness/review.py", "runner.py"):
        with open(os.path.join(REAL_ROOT, rel)) as fh:
            sources += fh.read() + "\n"
    for pin in rc.MODEL_PINS:
        purpose, model = pin.split(":")
        pattern = rf'"{re.escape(purpose)}",\s*"{re.escape(model)}"'
        assert re.search(pattern, sources), f"pin {pin} not found in review.py or runner.py"
