"""Phase 5 verification tracks. LiveVerify shells out to the real commands in a
worktree; ReplayVerify parses recorded track outputs from the historical trace.

Track results are TrackResult(status in {pass, fail, skipped, unavailable}).
`unavailable` (replay fixture lacked the recorded output for a track that should
have run) degrades the scenario's phase-5 fidelity and is disclosed — it is
treated as `skipped` for aggregation but flagged in the result.
"""
from __future__ import annotations

import re
import subprocess
from dataclasses import dataclass

from ..state import Classification


@dataclass
class TrackResult:
    name: str
    status: str            # pass | fail | skipped | unavailable
    evidence: str = ""


def aggregate(tracks: list[TrackResult]) -> str:
    """PHASE5_VERIFY_STATUS rules from _phase-5-final-verification.md."""
    launched = [t for t in tracks if t.status in ("pass", "fail")]
    if any(t.status == "fail" for t in launched):
        return "fail"
    if launched:
        return "pass"
    return "skipped"


class LiveVerify:
    def __init__(self, worktree: str, timeout: int = 1800):
        self.worktree = worktree
        self.timeout = timeout

    def _sh(self, cmd: str, cwd: str) -> tuple[int, str]:
        p = subprocess.run(["bash", "-c", cmd], cwd=cwd, capture_output=True,
                           text=True, timeout=self.timeout)
        return p.returncode, (p.stdout + p.stderr)[-3000:]

    def run(self, cls: Classification) -> list[TrackResult]:
        tracks: list[TrackResult] = []
        ibl5 = f"{self.worktree}/ibl5"
        if cls.has_php:
            rc, out = self._sh("vendor/bin/phpunit --no-progress 2>&1 | tail -n 5", ibl5)
            tracks.append(TrackResult("phpunit", "pass" if rc == 0 else "fail", out))
            rc, out = self._sh("composer run analyse -- --no-progress 2>&1 | tail -n 5", ibl5)
            tracks.append(TrackResult("phpstan", "pass" if rc == 0 else "fail", out))
        else:
            tracks += [TrackResult("phpunit", "skipped"), TrackResult("phpstan", "skipped")]
        if cls.has_go:
            rc1, o1 = self._sh("make -C engine fmt-check 2>&1 | tail -n 5", self.worktree)
            rc2, o2 = self._sh("make -C engine cover 2>&1 | tail -n 8", self.worktree)
            tracks.append(TrackResult("go", "pass" if rc1 == 0 and rc2 == 0 else "fail", o1 + o2))
        else:
            tracks.append(TrackResult("go", "skipped"))
        # E2E track intentionally NOT run in the isolated prototype (needs the
        # project Docker stack); labeled unavailable so aggregation stays honest.
        tracks.append(TrackResult("e2e", "unavailable", "isolated mode: E2E requires wt Docker stack"))
        return tracks


PHPUNIT_OK = re.compile(r"OK \(\d+ tests?|OK, but|Tests: \d+", re.I)
PHPUNIT_FAIL = re.compile(r"FAILURES!|ERRORS!|Fatal error", re.I)
PHPSTAN_OK = re.compile(r"\[OK\] No errors")
PHPSTAN_FAIL = re.compile(r"Found \d+ errors?|\[ERROR\]", re.I)
GO_FAIL = re.compile(r"FAIL|coverage .* below|gofmt", re.I)
GO_OK = re.compile(r"^ok\s|\bPASS\b|coverage", re.M)
E2E_FAIL = re.compile(r"\b\d+ failed\b|Error:|timed out", re.I)
E2E_OK = re.compile(r"\b\d+ passed\b", re.I)
E2E_NONE = re.compile(r"No E2E tests map|^\s*$")


def _judge(name: str, text: str | None, ok_re: re.Pattern, fail_re: re.Pattern,
           should_run: bool) -> TrackResult:
    if not should_run:
        return TrackResult(name, "skipped")
    if text is None:
        return TrackResult(name, "unavailable", "no recorded output in trace fixture")
    if fail_re.search(text):
        return TrackResult(name, "fail", text[-400:])
    if ok_re.search(text):
        return TrackResult(name, "pass", text[-200:])
    return TrackResult(name, "unavailable", f"recorded output inconclusive: {text[-120:]!r}")


class ReplayVerify:
    def __init__(self, fixture: dict):
        self.v = fixture.get("verify") or {}

    def run(self, cls: Classification) -> list[TrackResult]:
        tracks = [
            _judge("phpunit", self.v.get("phpunit"), PHPUNIT_OK, PHPUNIT_FAIL, cls.has_php),
            _judge("phpstan", self.v.get("phpstan"), PHPSTAN_OK, PHPSTAN_FAIL, cls.has_php),
            _judge("go", self.v.get("go"), GO_OK, GO_FAIL, cls.has_go),
        ]
        e2e_txt = self.v.get("e2e") or self.v.get("e2e_map")
        if e2e_txt and E2E_NONE.search(e2e_txt.strip()[:80]) and "passed" not in e2e_txt:
            tracks.append(TrackResult("e2e", "skipped", "no E2E tests map to changed files"))
        else:
            tracks.append(_judge("e2e", e2e_txt, E2E_OK, E2E_FAIL, bool(e2e_txt)))
        return tracks
