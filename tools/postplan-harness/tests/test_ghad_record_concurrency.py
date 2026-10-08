import builtins
import contextlib
import json
import os
import sys
import threading
import time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters import ghad
from harness.adapters.ghad import RecordingGh


class _ChunkedWriter:
    """File wrapper that splits every write into small yielding chunks, the way a
    long line can reach disk in pieces. Makes an unlocked append race reproducible."""

    def __init__(self, fh):
        self._fh = fh

    def write(self, s):
        for i in range(0, len(s), 64):
            self._fh.write(s[i:i + 64])
            self._fh.flush()
            time.sleep(0)
        return len(s)

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        self._fh.close()


THREADS, PER_THREAD = 4, 10
BODY = "x" * 2_000


def _run_concurrent_records(tmp_path, monkeypatch, lock=None):
    """Append THREADS * PER_THREAD records from racing threads through chunked
    writes. Passing `lock` replaces the RecordingGh append lock."""
    real_open = builtins.open

    def chunked_open(path, mode="r", *a, **kw):
        fh = real_open(path, mode, *a, **kw)
        return _ChunkedWriter(fh) if "a" in mode else fh

    monkeypatch.setattr(ghad, "open", chunked_open, raising=False)

    gh = RecordingGh(str(tmp_path))
    if lock is not None:
        gh._actions_lock = lock
    barrier = threading.Barrier(THREADS)

    def worker(n):
        barrier.wait()
        for i in range(PER_THREAD):
            gh.record("pr_comment", body=BODY, thread=n, i=i)

    pool = [threading.Thread(target=worker, args=(n,)) for n in range(THREADS)]
    for t in pool:
        t.start()
    for t in pool:
        t.join()
    return gh


def test_concurrent_records_stay_parseable(tmp_path, monkeypatch):
    gh = _run_concurrent_records(tmp_path, monkeypatch)

    records = gh.actions()
    assert len(records) == THREADS * PER_THREAD
    assert all(r["body"] == BODY for r in records)


def test_unlocked_records_corrupt_without_lock(tmp_path, monkeypatch):
    """Negative control: with the lock removed the same race corrupts the log,
    which proves the lock in RecordingGh.record is what keeps it parseable."""
    gh = _run_concurrent_records(tmp_path, monkeypatch, lock=contextlib.nullcontext())

    with open(gh.actions_path) as fh:
        lines = fh.read().splitlines()

    def parses(line):
        try:
            return json.loads(line).get("body") == BODY
        except ValueError:
            return False

    intact = [ln for ln in lines if parses(ln)]
    assert len(intact) < THREADS * PER_THREAD
