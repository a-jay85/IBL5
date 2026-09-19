"""GitHub adapter — the side-effect gate.

Replay/isolated modes NEVER execute a mutating `gh` command. Every would-be
mutation (pr create / comment / review / edit / merge --auto) is appended as a
typed intent record to <out>/actions.jsonl. Reads are served from fixtures
(replay) or recorded local state (isolated).

LiveGh is the installed mode (README §Installation, approved 2026-07-16): it
executes exactly the eight allowlisted mutations via `gh` — there is no generic
"run a gh command" escape hatch — and still appends every executed action to
actions.jsonl (executed=true) so the audit trail survives the install.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import tempfile
import time

from pathlib import Path

from ..state import HarnessError

# bin/lib/pr-sticky.sh in the harness's own checkout. Not under pr-ready/,
# so no either-location lookup applies.
STICKY_LIB = Path(__file__).resolve().parents[4] / "bin" / "lib" / "pr-sticky.sh"
# bin/lib/pr-armable.sh, pinned the same way: condition (11) must never read the helper
# from the worktree it is judging, or a branch could edit it to clear its own hold.
ARMABLE_LIB = Path(__file__).resolve().parents[4] / "bin" / "lib" / "pr-armable.sh"

POSTPLAN_BADGE_MARKER = "<!-- postplan-status -->"
# Duplicated from fidelity.STICKY_MARKER on purpose: adapters must not import the core
# phase modules. tests/test_fidelity_carryforward.py asserts the two stay equal.
PR_STICKY_MARKER = "<!-- pr-ready-verdict -->"


class RecordingGh:
    MUTATIONS = ("pr_create", "pr_comment", "pr_review_findings", "pr_edit_body",
                 "pr_merge_auto", "label_add", "pr_status_badge",
                 "pr_sticky_verdict", "issue_create")

    def __init__(self, out_dir: str, fixture: dict | None = None):
        self.out_dir = out_dir
        self.fixture = fixture or {}
        os.makedirs(out_dir, exist_ok=True)
        self.actions_path = os.path.join(out_dir, "actions.jsonl")
        self._body_override: str | None = None

    # -- side-effect intents (recorded, never executed) -----------------
    def record(self, action: str, **payload) -> None:
        assert action in self.MUTATIONS, f"unknown mutation {action}"
        with open(self.actions_path, "a") as fh:
            fh.write(json.dumps({"ts": time.time(), "action": action, **payload}) + "\n")

    def pr_create(self, title: str, body: str, base: str) -> int:
        self.record("pr_create", title=title, body=body[:8000], base=base)
        return int(self.fixture.get("pr_number") or 0)

    def unresolved_findings(self, pr: int) -> list[str]:
        return []

    def pr_sticky_body(self, pr: int) -> str | None:
        """Prior Phase 5.5 sticky body from the replay fixture, or None.

        A fixture that carries no `prior_sticky_body` key reads as "no prior sticky",
        which declines carry-forward — so every pre-existing replay fixture keeps the
        full-review path it has today, unchanged.
        """
        return self.fixture.get("prior_sticky_body")

    def pr_edit_body(self, pr: int, body: str) -> None:
        self._body_override = body
        self.record("pr_edit_body", pr=pr, body=body[:8000])

    def pr_merge_auto(self, pr: int) -> None:
        self.record("pr_merge_auto", pr=pr, args="--squash --auto")

    def pr_sticky_verdict(self, pr: int, body: str) -> str:
        # Deliberately UNTRUNCATED: replay tests read the terminal line and the marker,
        # both of which sit at the very end of the body.
        self.record("pr_sticky_verdict", pr=pr, body=body)
        return str(self.fixture.get("sticky_comment_id", "replay-sticky"))

    def post_review_findings(self, pr: int, head_sha: str, title: str, findings: list) -> None:
        self.record("pr_review_findings", pr=pr, head_sha=head_sha, title=title,
                    findings=[{"path": f.path, "line": f.line, "body": f.body[:1000],
                               "score": f.score} for f in findings])

    def post_review_summary(self, pr: int, title: str, body: str) -> None:
        self.record("pr_comment", pr=pr, title=title, body=body[:4000])

    def pr_status_badge(self, pr: int, body: str) -> None:
        self.record("pr_status_badge", pr=pr, body=body[:4000])

    def issue_create(self, title: str, body: str, label: str) -> int | None:
        existing = sum(1 for a in self.actions() if a.get("action") == "issue_create")
        self.record("issue_create", title=title, label=label)
        return existing + 1

    def issue_titles(self, label: str) -> list[str]:
        return []

    # -- reads (fixture-backed) ------------------------------------------
    def pr_exists(self) -> bool:
        return bool(self.fixture.get("pr_number"))

    def pr_meta(self) -> dict:
        return dict(self.fixture.get("pr_meta") or {})

    def pr_title(self) -> str:
        return (self.fixture.get("pr_meta") or {}).get("title") or self.fixture.get("title", "")

    def pr_body(self) -> str:
        if self._body_override is not None:
            return self._body_override
        return (self.fixture.get("pr_meta") or {}).get("body") or self.fixture.get("body", "")

    def pr_labels(self) -> list[str]:
        labels = self.fixture.get("labels") or []
        return [l["name"] if isinstance(l, dict) else str(l) for l in labels]

    def pr_state(self, pr: int | None = None) -> str:
        if pr is not None and pr != self.fixture.get("pr_number"):
            deps = self.fixture.get("dep_states") or {}
            return deps.get(str(pr), "UNKNOWN")
        return self.fixture.get("final_state") or "OPEN"

    def branch_protection_strict(self) -> bool:
        return bool((self.fixture or {}).get("protection_strict", False))

    def merge_state_status(self, pr: int | None = None) -> str:
        return str((self.fixture or {}).get("merge_state_status", "CLEAN"))

    def pr_disable_auto_merge(self, pr: int) -> None:
        self.record("pr_disable_auto_merge", pr=pr)

    def checks_outcome(self) -> dict:
        """Recorded terminal CI outcome: {"exit": 0|8, "failed": [names]}."""
        return dict(self.fixture.get("checks_outcome") or {"exit": 0, "failed": []})

    def pr_number(self) -> int:
        return int(self.fixture.get("pr_number") or 0)

    def actions(self) -> list[dict]:
        if not os.path.exists(self.actions_path):
            return []
        with open(self.actions_path) as fh:
            return [json.loads(l) for l in fh if l.strip()]


class LiveGh(RecordingGh):
    """Installed live adapter. Each of the eight MUTATIONS maps to one fixed `gh`
    invocation built inside its method — the allowlist IS the method set.
    Reads come from live `gh pr view` state. Merge deliberately omits
    --delete-branch: in a multi-worktree clone it errors benignly, and a parent
    merge carrying it permanently closes stacked child PRs."""

    def __init__(self, out_dir: str, worktree: str, branch: str, timeout: int = 120):
        super().__init__(out_dir)
        self.worktree = worktree
        self.branch = branch
        self.timeout = timeout
        self._meta: dict | None = None

    def _gh(self, *args: str, input_text: str | None = None) -> str:
        try:
            proc = subprocess.run(["gh", *args], cwd=self.worktree, input=input_text,
                                  capture_output=True, text=True, timeout=self.timeout)
        except subprocess.TimeoutExpired:
            raise HarnessError("gh", f"gh {' '.join(args[:2])}: exceeded {self.timeout}s")
        if proc.returncode != 0:
            raise HarnessError("gh", f"gh {' '.join(args[:3])}: {proc.stderr.strip()[:400]}")
        return proc.stdout

    def record(self, action: str, **payload) -> None:
        super().record(action, executed=True, **payload)

    def _repo(self) -> str:
        return self._gh("repo", "view", "--json", "nameWithOwner",
                        "-q", ".nameWithOwner").strip()

    # -- mutations: executed, then recorded ------------------------------
    def pr_create(self, title: str, body: str, base: str) -> int:
        out = self._gh("pr", "create", "--title", title, "--body", body,
                       "--base", base, "--head", self.branch)
        m = re.search(r"/pull/(\d+)", out)
        if not m:
            raise HarnessError("gh", f"pr create returned no PR URL: {out[:200]}")
        self._meta = None
        self.record("pr_create", title=title, body=body[:8000], base=base,
                    pr=int(m.group(1)))
        return int(m.group(1))

    def unresolved_findings(self, pr: int) -> list[str]:
        """Condition (11) — unresolved review threads scored >= 80.

        GH_CMD and REPO_SLUG are STRIPPED from the child env so bin/lib/pr-armable.sh
        falls back to its own defaults (`gh` and the repo slug). env=None would INHERIT
        them: a leaked stub answering the condition-(11) GraphQL query with a well-formed
        empty thread list reads as "no unresolved findings", and arming then proceeds on
        fabricated GitHub state. Same strip pr_sticky_verdict does below. Any failure
        returns the API-error sentinel, matching the shell's own contract — a GitHub
        outage must never arm a PR.

        The helper is sourced from the harness's OWN checkout (ARMABLE_LIB), which
        bin/post-plan-now pins to the main checkout (ADR-0092) — the same pin
        pr_sticky_verdict uses. A worktree-relative source would read the branch's copy,
        and a branch that edits bin/lib/pr-armable.sh could clear condition (11) on itself.
        """
        from .llm import _run_reaped
        if not ARMABLE_LIB.exists():
            return ["unresolved-findings-api-error"]
        argv = ["bash", "-c", 'source "$1"; pr_unresolved_findings_hold "$2"', "_",
                str(ARMABLE_LIB), str(pr)]
        env = {k: v for k, v in os.environ.items() if k not in ("GH_CMD", "REPO_SLUG")}
        try:
            proc = _run_reaped(argv, None, 120, self.worktree, env)
        except Exception:
            return ["unresolved-findings-api-error"]
        if proc.returncode != 0:
            return ["unresolved-findings-api-error"]
        return proc.stdout.split()

    def pr_edit_body(self, pr: int, body: str) -> None:
        self._gh("pr", "edit", str(pr), "--body", body)
        self._body_override = body
        self.record("pr_edit_body", pr=pr, body=body[:8000])

    def pr_merge_auto(self, pr: int) -> None:
        self._gh("pr", "merge", str(pr), "--squash", "--auto")
        self.record("pr_merge_auto", pr=pr, args="--squash --auto")

    def pr_sticky_verdict(self, pr: int, body: str) -> str:
        """Upsert the Phase 5.5 sticky verdict comment, then read its id back.

        Sourced from the harness's OWN checkout, which bin/post-plan-now pins to the main
        checkout (ADR-0092), so a branch cannot alter the helper that posts its own verdict.
        GH_CMD is stripped so a leaked stub never stands in for real `gh`. Never raises: a
        failed post degrades to an empty id and arming is deliberately unchanged by it.
        """
        from .llm import _run_reaped
        path = os.path.join(self.out_dir, f"sticky-verdict-{pr}.md")
        with open(path, "w") as fh:
            fh.write(body)
        cid = ""
        env = {k: v for k, v in os.environ.items() if k != "GH_CMD"}
        if STICKY_LIB.exists():
            argv = ["bash", "-c", 'source "$1"; pr_sticky_upsert "$2" "$3" "$(cat "$4")"; pr_sticky_find "$2" "$3"', "_", str(STICKY_LIB), str(pr),
                    PR_STICKY_MARKER, path]
            try:
                proc = _run_reaped(argv, None, self.timeout, self.worktree, env)
                out = (proc.stdout or "").strip()
                if re.match(r"^[0-9]+$", out):
                    cid = out
            except (subprocess.TimeoutExpired, OSError):
                cid = ""
        self.record("pr_sticky_verdict", pr=pr, comment_id=cid, body=body)
        return cid

    def pr_sticky_body(self, pr: int) -> str | None:
        """The PR's current Phase 5.5 sticky comment body, or None.

        Fail-closed on every degraded shape — a gh failure, unparseable JSON, zero
        marked comments, or more than one. Returning None declines carry-forward and
        the full Opus review runs, which is the pre-change behaviour.
        """
        try:
            data = json.loads(self._gh("pr", "view", str(pr), "--json", "comments"))
        except (HarnessError, ValueError, OSError):
            return None
        try:
            bodies = [c.get("body") or "" for c in (data.get("comments") or [])
                      if PR_STICKY_MARKER in (c.get("body") or "")]
        except (AttributeError, TypeError):
            return None
        return bodies[0] if len(bodies) == 1 else None

    def label_add(self, pr: int, label: str) -> None:
        self._gh("pr", "edit", str(pr), "--add-label", label)
        self.record("label_add", pr=pr, label=label)

    def issue_create(self, title: str, body: str, label: str) -> int | None:
        out = self._gh("issue", "create", "--repo", "a-jay85/IBL5-backlog",
                       "--label", label, "--title", title, "--body", body)
        m = re.search(r"/issues/(\d+)", out)
        if not m:
            raise HarnessError("gh", f"issue create returned no issue URL: {out[:200]}")
        n = int(m.group(1))
        self.record("issue_create", title=title, label=label, issue=n)
        return n

    def issue_titles(self, label: str) -> list[str]:
        try:
            out = self._gh("issue", "list", "--repo", "a-jay85/IBL5-backlog",
                           "--state", "all", "--limit", "200", "--json", "title")
            items = json.loads(out)
            return [i.get("title", "") for i in items if i.get("title")]
        except (HarnessError, json.JSONDecodeError):
            return []

    def post_review_findings(self, pr: int, head_sha: str, title: str, findings: list) -> None:
        payload = {"commit_id": head_sha, "event": "COMMENT", "body": title,
                   "comments": [{"path": f.path, "line": f.line, "side": "RIGHT",
                                 "body": f.body[:1000]} for f in findings]}
        try:
            self._gh("api", f"repos/{self._repo()}/pulls/{pr}/reviews",
                     "--method", "POST", "--input", "-",
                     input_text=json.dumps(payload))
        except HarnessError:
            # one out-of-diff line anchor 422s the whole review — degrade to a
            # summary comment; findings still hold arming from memory regardless
            body = "\n".join(f"- `{f.path}:{f.line}` — {f.body}" for f in findings)
            self._gh("pr", "comment", str(pr), "--body", f"## {title}\n\n{body}")
        self.record("pr_review_findings", pr=pr, head_sha=head_sha, title=title,
                    findings=[{"path": f.path, "line": f.line, "body": f.body[:1000],
                               "score": f.score} for f in findings])

    def post_review_summary(self, pr: int, title: str, body: str) -> None:
        self._gh("pr", "comment", str(pr), "--body", f"## {title}\n\n{body}")
        self.record("pr_comment", pr=pr, title=title, body=body[:4000])

    def pr_status_badge(self, pr: int, body: str) -> None:
        try:
            with tempfile.NamedTemporaryFile(mode="w", suffix=".md", delete=False) as f:
                f.write(body)
                fname = f.name
            try:
                existing = self._gh(
                    "api", f"repos/{{owner}}/{{repo}}/issues/{pr}/comments",
                    "--paginate", "--jq",
                    f'.[] | select(.body | contains("{POSTPLAN_BADGE_MARKER}")) | .id',
                )
                existing_id = existing.strip().splitlines()[0] if existing.strip() else ""
            except HarnessError:
                existing_id = ""
            if existing_id:
                self._gh("api", "--method", "PATCH",
                         f"repos/{{owner}}/{{repo}}/issues/comments/{existing_id}",
                         "-F", f"body=@{fname}")
            else:
                self._gh("pr", "comment", str(pr), "--body-file", fname)
            self.record("pr_status_badge", pr=pr, body=body[:4000])
        except HarnessError:
            pass
        finally:
            try:
                os.unlink(fname)
            except Exception:
                pass

    def branch_protection_strict(self) -> bool:
        """Fail closed: an unreadable or null protection config is treated as strict."""
        try:
            out = self._gh("api", f"repos/{self._repo()}/branches/master",
                           "--jq", ".protection.required_status_checks.strict")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return True
        val = (out or "").strip().lower()
        if val in ("true", "false"):
            return val == "true"
        return True

    def merge_state_status(self, pr: int | None = None) -> str:
        """Fail open with "": an unreadable merge state must not trigger a rebase storm."""
        try:
            out = self._gh("pr", "view", str(pr or self.pr_number()),
                           "--json", "mergeStateStatus", "--jq", ".mergeStateStatus")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return ""
        return (out or "").strip()

    def pr_disable_auto_merge(self, pr: int) -> None:
        try:
            self._gh("pr", "merge", str(pr), "--disable-auto")
        except (HarnessError, OSError, subprocess.SubprocessError):
            pass
        self.record("pr_disable_auto_merge", pr=pr)

    # -- reads (live) -----------------------------------------------------
    def _fetch_meta(self) -> dict:
        if self._meta is None:
            try:
                out = self._gh("pr", "view", self.branch, "--json",
                               "number,title,body,headRefOid,labels,state")
                self._meta = json.loads(out)
            except (HarnessError, json.JSONDecodeError):
                self._meta = {}
        return self._meta

    def pr_exists(self) -> bool:
        return self._fetch_meta().get("state") == "OPEN"

    def pr_number(self) -> int:
        return int(self._fetch_meta().get("number") or 0)

    def pr_meta(self) -> dict:
        return dict(self._fetch_meta())

    def pr_title(self) -> str:
        return self._fetch_meta().get("title", "")

    def pr_body(self) -> str:
        if self._body_override is not None:
            return self._body_override
        return self._fetch_meta().get("body", "")

    def pr_labels(self) -> list[str]:
        return [l.get("name", "") for l in self._fetch_meta().get("labels") or []]

    def pr_state(self, pr: int | None = None) -> str:
        if pr is None or pr == self.pr_number():
            self._meta = None                       # re-fetch: state may have moved
            return self._fetch_meta().get("state") or "UNKNOWN"
        try:
            return self._gh("pr", "view", str(pr), "--json", "state",
                            "-q", ".state").strip() or "UNKNOWN"
        except HarnessError:
            return "UNKNOWN"                        # fail-closed for condition (6)
