"""Reader for the run's auto-resolved conflict list (backlog#1771)."""
import os
import sys
import uuid

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from harness import conflict


def _key():
    return f"t1771-{uuid.uuid4().hex}"


def test_read_autoresolved_list_returns_paths_in_order():
    key = _key()
    try:
        with open(conflict.autoresolved_list_path(key), "w", encoding="utf-8") as fh:
            fh.write("b.py\n\n  a.py \n")
        assert conflict.read_autoresolved_list(key) == ["b.py", "a.py"]
    finally:
        conflict.purge_autoresolved_list(key)


def test_read_autoresolved_list_missing_file_is_empty():
    assert conflict.read_autoresolved_list(_key()) == []


def test_autoresolved_list_path_matches_writer_literal():
    key = _key()
    path = conflict.autoresolved_list_path(key)
    assert path == f"/tmp/postplan-conflict-files-{key}-autoresolved.txt"
    try:
        with open(path, "w", encoding="utf-8") as fh:
            fh.write("x.py\n")
        conflict.purge_autoresolved_list(key)
        assert not os.path.exists(path)
    finally:
        conflict.purge_autoresolved_list(key)
