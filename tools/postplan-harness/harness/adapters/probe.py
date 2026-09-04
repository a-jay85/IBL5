"""Phase 6 probe adapter — attempt-then-demote for truly-manual rows.

Mirrors the LiveVerify / ReplayVerify seam in verify.py. A probe runs the
concrete check command advertised by the re-check LLM call and reports whether
it succeeded. A row is only demoted when the probe returns (True, _) — any
other outcome keeps it held.

Security contract: only a narrow allowlist of argv[0] values is accepted, and
every element is validated against a strict character class before any subprocess
is spawned. Interpreters and process launchers (bash, sh, python3, env, xargs,
find, etc.) are absent from the allowlist by design — that absence is what makes
the allowlist a whitelist rather than a denylist.
"""
from __future__ import annotations

import os
import re
import subprocess
from typing import Optional

ALLOWED_ARGV0 = re.compile(r"^bin/(check|test)-[a-z0-9-]+$")
_ALLOWED_LITERALS = {"pytest", "grep"}

_SAFE_ELEM = re.compile(r"^[A-Za-z0-9._/=@:\[\]+-]+$")
_BAD_FLAG = re.compile(r"^(-c|-e|--eval|--exec|-p$|--plugin)")


def _validate(argv: list[str]) -> Optional[str]:
    """Return a rejection reason string, or None when argv is safe to run."""
    if not argv:
        return "empty argv"
    if len(argv) > 8:
        return f"argv too long ({len(argv)} > 8)"
    for i, elem in enumerate(argv):
        if len(elem) > 200:
            return f"argv[{i}] too long ({len(elem)} chars)"
        if not _SAFE_ELEM.match(elem):
            return f"argv[{i}] contains disallowed characters: {elem!r}"
        if elem.startswith("/"):
            return f"argv[{i}] is absolute path: {elem!r}"
        if ".." in elem:
            return f"argv[{i}] contains ..: {elem!r}"
        if _BAD_FLAG.match(elem):
            return f"argv[{i}] is a disallowed flag: {elem!r}"
    argv0 = argv[0]
    if argv0 not in _ALLOWED_LITERALS and not ALLOWED_ARGV0.match(argv0):
        return f"argv[0] not in allowlist: {argv0!r}"
    return None


_SCRUBBED_KEYS = {"PYTHONPATH", "PYTHONSTARTUP", "BASH_ENV", "ENV"}
_KEPT_KEYS = {"PATH", "HOME", "LANG", "PYTHONHASHSEED"}


def _clean_env() -> dict[str, str]:
    env = {k: v for k, v in os.environ.items() if k in _KEPT_KEYS}
    return env


class LiveProbe:
    """Run a validated check command in the repo root."""

    def __init__(self, repo_root: Optional[str] = None):
        self.repo_root = repo_root

    def run(self, argv: list[str]) -> tuple[bool, str]:
        """Return (success, detail). Never raises — any exception is caught."""
        reason = _validate(argv)
        if reason:
            return False, reason
        try:
            proc = subprocess.run(
                argv,
                cwd=self.repo_root,
                shell=False,
                timeout=120,
                capture_output=True,
                text=True,
                env=_clean_env(),
            )
            tail = (proc.stderr or "")[-400:]
            return proc.returncode == 0, tail
        except subprocess.TimeoutExpired:
            return False, "timeout after 120s"
        except Exception as exc:  # noqa: BLE001
            return False, str(exc)


class FixtureProbe:
    """Replay probe results from a scenario fixture dict.

    Fixture schema: ``{"probes": {" ".join(argv): true/false, ...}}``.
    A missing key returns (False, "no recorded probe") so that a fixture
    without probe data keeps all rows held — the safe default.
    """

    def __init__(self, fixture: dict):
        self._table: dict[str, bool] = fixture.get("probes") or {}

    def run(self, argv: list[str]) -> tuple[bool, str]:
        key = " ".join(argv)
        if key not in self._table:
            return False, "no recorded probe"
        return bool(self._table[key]), f"fixture probe {key!r}"
