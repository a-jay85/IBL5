"""Shared retry for network-transient git/gh failures in the post-plan harness.

Only errors whose text carries a known transient signature are retried. Every
other error is raised on the first attempt, exactly as before this module.
"""
from __future__ import annotations

import logging
import re
import time
from datetime import datetime, timezone
from typing import Callable, Optional, Sequence, TypeVar

from .state import PRECOMMIT_HANG, SUBPROCESS_TIMEOUT, HarnessError

T = TypeVar("T")
R = TypeVar("R")
log = logging.getLogger("harness.netretry")

RETRY_DELAYS: tuple[float, ...] = (5.0, 20.0, 60.0)  # three retries after attempt 1
OUTAGE_LATCH_SECONDS = 300.0
# The four fetch markers moved from adapters/gitad.py, plus the Go TLS timeout.
# Bare "Could not read from remote repository" and "Could not resolve host" are
# auth/config/DNS failures and stay non-retryable.
TRANSIENT_MARKERS: tuple[str, ...] = (
    "kex_exchange_identification",
    "Operation timed out",
    "Connection timed out",
    "Connection reset by peer",
    "TLS handshake timeout",
)
# Go net/http shape printed by gh: <Verb> "https://host/path": EOF
# A bare "EOF" never matches; the quoted URL and the ": EOF" suffix are required.
HTTP_EOF_RE = re.compile(r'\b[A-Z][a-z]+ "https?://[^"\s]+": EOF\b')
_HARNESS_TIMEOUT_RE = re.compile(r"exceeded \d+(?:\.\d+)?s\b")  # LiveGh._gh timeout text
# A harness timeout already spent its whole budget, and retrying it multiplies wall time
# past the fleet's 90 min (4 attempts plus 85 s of sleeps, per push-lease attempt).
_NEVER_RETRY_KINDS = frozenset({"local-gate", SUBPROCESS_TIMEOUT, PRECOMMIT_HANG})
# GitHub primary rate limit, GraphQL or REST shape. The secondary (abuse) limit
# reads "exceeded a secondary rate limit" and is deliberately not matched.
RATE_LIMIT_RE = re.compile(r"\bAPI rate limit (?:already )?exceeded\b")
RATE_LIMIT_MAX_WAIT = 900.0   # 15 minutes: the whole added wait for one call
RATE_LIMIT_MARGIN = 2.0       # reset is whole seconds; wake just after it

GH_RETRY_SAFE_SUBCOMMANDS = frozenset({
    ("repo", "view"), ("pr", "view"), ("pr", "list"), ("pr", "checks"),
    ("issue", "list"), ("run", "view"), ("run", "list"),
    ("pr", "edit"), ("pr", "merge"),
})
_GH_API_WRITE_EXACT = frozenset({"--method", "-X", "--input", "-f", "-F",
                                 "--field", "--raw-field"})
_GH_API_WRITE_PREFIX = ("--method=", "--input=", "--field=", "--raw-field=",
                        "-X", "-f", "-F")

_latch_until = 0.0


def reset_outage_latch() -> None:
    global _latch_until
    _latch_until = 0.0


def outage_latched() -> bool:
    return time.monotonic() < _latch_until


def _trip_latch() -> None:
    global _latch_until
    _latch_until = time.monotonic() + OUTAGE_LATCH_SECONDS


def match_transient(text: str) -> Optional[str]:
    """Return the first TRANSIENT_MARKERS substring found, else HTTP_EOF_RE's
    match text, else None."""
    for marker in TRANSIENT_MARKERS:
        if marker in text:
            return marker
    m = HTTP_EOF_RE.search(text)
    return m.group(0) if m else None


def match_rate_limit(text: str) -> Optional[str]:
    m = RATE_LIMIT_RE.search(text)
    return m.group(0) if m else None


def _error_marker(e: HarnessError) -> Optional[str]:
    if e.kind in _NEVER_RETRY_KINDS or _HARNESS_TIMEOUT_RE.search(e.detail):
        return None
    return match_transient(f"{e.detail}\n{e.output}")


def gh_retry_safe(args: Sequence[str]) -> bool:
    """True for allowlisted read/idempotent subcommands, or for an `api` call
    with no write flag. Everything else is False."""
    args = list(args)
    if len(args) >= 2 and (args[0], args[1]) in GH_RETRY_SAFE_SUBCOMMANDS:
        return True
    if args and args[0] == "api":
        for a in args[1:]:
            if a in _GH_API_WRITE_EXACT or a.startswith(_GH_API_WRITE_PREFIX):
                return False
        return True
    return False


def call(fn: Callable[[], T], *, label: str,
         landed: Optional[Callable[[], tuple[bool, Optional[T]]]] = None,
         sleep: Optional[Callable[[float], None]] = None,
         delays: Sequence[float] = RETRY_DELAYS) -> T:
    try:
        return fn()
    except HarnessError as first:
        original = first
        marker = _error_marker(first)
        if marker is None or outage_latched():
            raise

    nap = sleep if sleep is not None else time.sleep

    def _check_landed() -> tuple[bool, Optional[T]]:
        if landed is None:
            return False, None
        try:
            return landed()
        except Exception:
            log.warning("netretry %s: landed check failed; giving up", label)
            raise original

    for n, delay in enumerate(delays, start=1):
        done, value = _check_landed()
        if done:
            return value  # type: ignore[return-value]
        log.warning("netretry %s: transient %r, retry %d/%d in %gs",
                    label, marker, n, len(delays), delay)
        nap(delay)
        try:
            return fn()
        except HarnessError as e:
            marker = _error_marker(e)
            if marker is None:
                raise
    done, value = _check_landed()
    if done:
        return value  # type: ignore[return-value]
    _trip_latch()
    log.warning("netretry %s: %d retries exhausted; single-attempt mode for %gs",
                label, len(delays), OUTAGE_LATCH_SECONDS)
    raise original


def _utc(epoch: float) -> str:
    return datetime.fromtimestamp(epoch, tz=timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")


def _rate_limited(e: HarnessError, note: str) -> HarnessError:
    return HarnessError(e.kind, f"{e.detail} [GitHub API rate limit: {note}]",
                        cmd=e.cmd, output=e.output)


def call_rate_limited(fn: Callable[[], T], *, label: str,
                      landed: Optional[Callable[[], tuple[bool, Optional[T]]]],
                      reset_at: Callable[[], float],
                      sleep: Optional[Callable[[float], None]] = None,
                      now: Optional[Callable[[], float]] = None,
                      max_wait: float = RATE_LIMIT_MAX_WAIT) -> T:
    """Run fn; on a GitHub primary rate-limit error wait once until the bucket
    resets (bounded by max_wait), check landed(), and retry fn exactly once.

    Independent of the transient outage latch: never reads or trips it."""
    try:
        return fn()
    except HarnessError as first:
        if (first.kind in _NEVER_RETRY_KINDS
                or match_rate_limit(f"{first.detail}\n{first.output}") is None):
            raise
        err = first
    if landed is None:
        raise _rate_limited(err, "no landed check for this call, so it was not retried")
    try:
        reset = float(reset_at())
    except Exception:
        raise _rate_limited(err, "reset time unreadable, so it was not retried") from None
    # Resolved here, not earlier: tests replace netretry.time with a namespace
    # that has no time(), and non-rate-limit errors must never reach this line.
    clock = now if now is not None else time.time
    wait = max(0.0, reset - clock()) + RATE_LIMIT_MARGIN
    if wait > max_wait:
        raise _rate_limited(
            err, f"resets at {_utc(reset)}, {wait:.0f}s away, over the "
                 f"{max_wait:.0f}s bound, so it was not retried")
    log.warning("netretry %s: GitHub API rate limit, waiting %.0fs until %s, then one retry",
                label, wait, _utc(reset))
    (sleep if sleep is not None else time.sleep)(wait)
    try:
        done, value = landed()
    except Exception:
        raise _rate_limited(
            err, f"landed check failed after waiting {wait:.0f}s "
                 f"(reset {_utc(reset)}), so it was not retried") from None
    if done:
        return value  # type: ignore[return-value]
    try:
        return fn()
    except HarnessError as e:
        if match_rate_limit(f"{e.detail}\n{e.output}") is not None:
            raise _rate_limited(
                e, f"still limited after waiting {wait:.0f}s for reset "
                   f"{_utc(reset)}; gave up after one retry") from None
        raise


def _rc_marker(r: object) -> Optional[str]:
    if getattr(r, "returncode", 0) == 0:
        return None
    out = getattr(r, "stdout", "") or ""
    err = getattr(r, "stderr", "") or ""
    return match_transient(f"{out}\n{err}")


def call_rc(fn: Callable[[], R], *, label: str,
            sleep: Optional[Callable[[float], None]] = None,
            delays: Sequence[float] = RETRY_DELAYS) -> R:
    result = fn()
    marker = _rc_marker(result)
    if marker is None or outage_latched():
        return result
    nap = sleep if sleep is not None else time.sleep
    for n, delay in enumerate(delays, start=1):
        log.warning("netretry %s: transient %r, retry %d/%d in %gs",
                    label, marker, n, len(delays), delay)
        nap(delay)
        result = fn()
        marker = _rc_marker(result)
        if marker is None:
            return result
    _trip_latch()
    log.warning("netretry %s: %d retries exhausted; single-attempt mode for %gs",
                label, len(delays), OUTAGE_LATCH_SECONDS)
    return result
