"""Per-slug state file — persists a checkpoint record after every major phase.

Written atomically via a .pid.tmp sibling so a mid-write crash leaves the
previous file intact.  All writes are fire-and-forget: a failure is logged
but never propagates to the caller.
"""
import hashlib
import json
import os
import re
import time

SCHEMA_VERSION = 1

REVIEW_AGENTS = {
    "A": "review-agent-a",
    "B": "review-agent-b",
    "D": "review-agent-d",
    "security": "security-audit",
}

STATE_KEYS = (
    "schema_version",
    "slug",
    "pr_number",
    "run_dir",
    "terminal",
    "error_kind",
    "head_sha",
    "reviewed_head_sha",
    "phases",
    "review_agents",
    "fidelity",
    "arm",
    "sticky_comment_id",
    "ci",
    "first_seen_at",
    "run_started_at",
    "updated_at",
)


def _now() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())


def safe_slug(slug: str) -> str:
    """Return a filesystem-safe slug with no path separator.

    Characters outside ``[A-Za-z0-9._-]`` are collapsed into a single ``-``.
    Returns ``"unknown"`` when the input is empty, None, or the substituted
    result contains no alphanumeric character (e.g. ``".."`` or ``"/"``).
    This is the same character class as ``bin/post-plan-now``'s ``SAFE_SLUG``,
    so the result can never contain a ``/``.
    """
    result = re.sub(r"[^A-Za-z0-9._-]+", "-", slug or "")
    if not result or not re.search(r"[A-Za-z0-9]", result):
        return "unknown"
    return result


class StateFile:
    """Checkpoint record written after each major phase of a runner.run() call."""

    def __init__(self, path: str, slug: str, git, run_dir: str, log) -> None:
        self.path = path
        self.slug = slug
        self.git = git
        self.run_dir = run_dir
        self.log = log
        self.phases: list = []
        self.review_gates: dict = {}
        self.reviewed_head: str = ""
        self.run_started_at: str = _now()

        try:
            with open(path) as fh:
                data = json.load(fh)
            if not isinstance(data, dict):
                raise ValueError("not a dict")
            self.previous = data
        except FileNotFoundError:
            self.previous = {}
        except Exception:
            self.previous = {}
            log("state: previous file unreadable — rewriting")

    def checkpoint(self, phase: str, res, *, review_gates=None, reviewed_head=None) -> None:
        """Write a state snapshot; never raises — failures are logged only."""
        try:
            if review_gates is not None:
                self.review_gates = review_gates
            if reviewed_head is not None:
                self.reviewed_head = reviewed_head

            try:
                head = self.git.head()
            except Exception:
                head = ""

            self.phases.append({"phase": phase, "at": _now(), "head_sha": head or None})

            ci = dict(self.previous.get("ci") or {})
            if phase == "terminal" and res.ci_outcome and head:
                ci[head] = {"outcome": res.ci_outcome, "recorded_at": _now()}

            if self.review_gates:
                review_agents = {}
                for k in REVIEW_AGENTS:
                    findings_for_k = [
                        f for f in res.scored_findings if f.get("agent") == k
                    ]
                    sha256 = hashlib.sha256(
                        json.dumps(
                            findings_for_k, sort_keys=True, separators=(",", ":")
                        ).encode()
                    ).hexdigest()
                    review_agents[k] = {
                        "ran": bool(self.review_gates.get(k)),
                        "degraded": REVIEW_AGENTS[k] in res.degraded_agents,
                        "findings_sha256": sha256,
                    }
            else:
                review_agents = {}

            terminal = res.terminal.value if phase == "terminal" else None

            fidelity = dict(res.fidelity) if res.fidelity else None

            arm = None
            if res.arm is not None:
                arm = {
                    "armed": res.arm.armed,
                    "holds": [c.number for c in res.arm.holds],
                }

            doc = {
                "schema_version": SCHEMA_VERSION,
                "slug": self.slug,
                "pr_number": res.pr_number,
                "run_dir": self.run_dir,
                "terminal": terminal,
                "error_kind": res.error_kind,
                "head_sha": head or None,
                "reviewed_head_sha": self.reviewed_head or None,
                "phases": list(self.phases),
                "review_agents": review_agents,
                "fidelity": fidelity,
                "arm": arm,
                "sticky_comment_id": res.sticky_comment_id,
                "ci": ci,
                "first_seen_at": self.previous.get("first_seen_at") or self.run_started_at,
                "run_started_at": self.run_started_at,
                "updated_at": _now(),
            }

            dirname = os.path.dirname(self.path)
            os.makedirs(dirname, exist_ok=True)
            tmp = f"{self.path}.{os.getpid()}.tmp"
            try:
                with open(tmp, "w") as fh:
                    fh.write(json.dumps(doc, indent=1, default=str))
                os.replace(tmp, self.path)
            except Exception:
                try:
                    os.remove(tmp)
                except OSError:
                    pass
                raise

        except Exception as e:
            self.log(f"state: write failed at {phase} ({e!r}) — run unaffected")
