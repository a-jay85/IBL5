import builtins
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


def test_concurrent_records_stay_parseable(tmp_path, monkeypatch):
    real_open = builtins.open

    def chunked_open(path, mode="r", *a, **kw):
        fh = real_open(path, mode, *a, **kw)
        return _ChunkedWriter(fh) if "a" in mode else fh

    monkeypatch.setattr(ghad, "open", chunked_open, raising=False)

    gh = RecordingGh(str(tmp_path))
    threads, per_thread = 4, 10
    body = "x" * 2_000
    barrier = threading.Barrier(threads)

    def worker(n):
        barrier.wait()
        for i in range(per_thread):
            gh.record("pr_comment", body=body, thread=n, i=i)

    pool = [threading.Thread(target=worker, args=(n,)) for n in range(threads)]
    for t in pool:
        t.start()
    for t in pool:
        t.join()

    records = gh.actions()
    assert len(records) == threads * per_thread
    assert all(r["body"] == body for r in records)
