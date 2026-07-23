"""GitHub adapter — the side-effect gate.

Replay/isolated modes NEVER execute a mutating `gh` command. Every would-be
mutation (pr create / comment / review / edit / merge --auto) is appended as a
typed intent record to <out>/actions.jsonl. Reads are served from fixtures
(replay) or recorded local state (isolated).

LiveGh is the installed mode (README §Installation, approved 2026-07-16): it
executes exactly the six allowlisted mutations via `gh` — there is no generic
"run a gh command" escape hatch — and still appends every executed action to
actions.jsonl (executed=true) so the audit trail survives the install.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import time

from ..state import HarnessError


class RecordingGh:
    MUTATIONS = ("pr_create", "pr_comment", "pr_review_findings", "pr_edit_body",
                 "pr_merge_auto", "label_add")

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

    def pr_edit_body(self, pr: int, body: str) -> None:
        self._body_override = body
        self.record("pr_edit_body", pr=pr, body=body[:8000])

    def pr_merge_auto(self, pr: int) -> None:
        self.record("pr_merge_auto", pr=pr, args="--squash --auto --delete-branch")

    def post_review_findings(self, pr: int, head_sha: str, title: str, findings: list) -> None:
        self.record("pr_review_findings", pr=pr, head_sha=head_sha, title=title,
                    findings=[{"path": f.path, "line": f.line, "body": f.body[:1000],
                               "score": f.score} for f in findings])

    def post_review_summary(self, pr: int, title: str, body: str) -> None:
        self.record("pr_comment", pr=pr, title=title, body=body[:4000])

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
    """Installed live adapter. Each of the six MUTATIONS maps to one fixed `gh`
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

    def pr_edit_body(self, pr: int, body: str) -> None:
        self._gh("pr", "edit", str(pr), "--body", body)
        self._body_override = body
        self.record("pr_edit_body", pr=pr, body=body[:8000])

    def pr_merge_auto(self, pr: int) -> None:
        self._gh("pr", "merge", str(pr), "--squash", "--auto")
        self.record("pr_merge_auto", pr=pr, args="--squash --auto")

    def label_add(self, pr: int, label: str) -> None:
        self._gh("pr", "edit", str(pr), "--add-label", label)
        self.record("label_add", pr=pr, label=label)

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
