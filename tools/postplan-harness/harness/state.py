"""Typed state, intermediate representations, and terminal states for the compiled post-plan harness."""
from __future__ import annotations

import json
import time
from dataclasses import dataclass, field, asdict
from enum import Enum
from typing import Optional


class Phase5Status(str, Enum):
    PASS = "pass"
    FAIL = "fail"
    SKIPPED = "skipped"


class TerminalState(str, Enum):
    SHIPPED_ARMED = "shipped-armed"          # PR open/updated, auto-merge armed
    SHIPPED_HELD = "shipped-held"            # PR open, auto-merge deliberately NOT armed
    NOTHING_TO_SHIP = "nothing-to-ship"      # clean tree, empty diff vs master
    FAILED = "failed"                        # typed failure aborted the run


class HarnessError(Exception):
    """Typed failure. `kind` is a stable machine-readable failure class."""

    def __init__(self, kind: str, detail: str):
        self.kind = kind
        self.detail = detail
        super().__init__(f"{kind}: {detail}")


@dataclass
class Classification:
    """Phase 3 output — faithful port of _phase-3-classify-diff.md flags."""
    files: list[str] = field(default_factory=list)
    count_total: int = 0
    count_php: int = 0
    count_css: int = 0
    count_md: int = 0
    count_migration: int = 0
    count_test: int = 0
    count_e2e_specs: int = 0
    count_lock: int = 0
    count_snapshot: int = 0
    count_non_code: int = 0
    count_go: int = 0
    count_ibl5: int = 0
    go_touched_count: int = 0
    has_php: bool = False
    has_css: bool = False
    has_migration: bool = False
    has_test: bool = False
    has_e2e_specs: bool = False
    has_go: bool = False
    go_touched: bool = False
    engine_only: bool = False
    golden_changed: bool = False
    docs_only: bool = False
    css_only: bool = False
    migration_only: bool = False
    test_only: bool = False
    non_code_only: bool = False
    has_modified: bool = False
    has_comments_in_diff: bool = False
    lines_php_changed: int = 0
    e2e_spec_modules: list[str] = field(default_factory=list)
    has_e2e_prod_overlap: bool = False
    filtered_diff: str = ""          # migrations/lockfiles/snapshots stripped

    def summary(self) -> str:
        return (
            f"total={self.count_total} php={self.count_php} css={self.count_css} md={self.count_md} "
            f"migration={self.count_migration} test={self.count_test} lock={self.count_lock} snapshot={self.count_snapshot}\n"
            f"DOCS_ONLY={self.docs_only} CSS_ONLY={self.css_only} MIGRATION_ONLY={self.migration_only} "
            f"TEST_ONLY={self.test_only} NON_CODE_ONLY={self.non_code_only}\n"
            f"HAS_PHP={self.has_php} HAS_CSS={self.has_css} HAS_MODIFIED={self.has_modified} "
            f"HAS_COMMENTS_IN_DIFF={self.has_comments_in_diff} LINES_PHP_CHANGED={self.lines_php_changed}\n"
            f"HAS_E2E_SPECS={self.has_e2e_specs} HAS_E2E_PROD_OVERLAP={self.has_e2e_prod_overlap}\n"
            f"HAS_GO={self.has_go} GO_TOUCHED={self.go_touched} ENGINE_ONLY={self.engine_only} "
            f"GOLDEN_CHANGED={self.golden_changed} COUNT_GO={self.count_go}"
        )


@dataclass
class PlanInfo:
    """Phase 1 output — located plan + parsed signals."""
    found: bool = False
    path: str = ""
    auto_merge_false: bool = False       # line-1 frontmatter `auto_merge: false`
    has_matrix: bool = False
    has_security: bool = False
    has_reuse: bool = False
    planned_test_paths: list[str] = field(default_factory=list)
    critical_files: list[tuple] = field(default_factory=list)  # (path, annotation, exempt)
    truly_manual_rows: list[str] = field(default_factory=list)
    security_section: str = ""
    reuse_section: str = ""


@dataclass
class Finding:
    source: str          # "code-review" | "security-audit"
    agent: str           # A/B/C/D/security
    path: str
    line: int
    body: str
    score: Optional[int] = None


@dataclass
class ConditionResult:
    number: int
    name: str
    blocked: bool
    reason: str = ""
    warning: str = ""    # non-blocking surfaced warning (e.g. interactive golden)


@dataclass
class ArmDecision:
    armed: bool
    conditions: list[ConditionResult] = field(default_factory=list)

    @property
    def holds(self) -> list[ConditionResult]:
        return [c for c in self.conditions if c.blocked]


@dataclass
class LlmCallRecord:
    """Usage provenance for one bounded LLM call (provider-reported by claude CLI)."""
    purpose: str
    model: str
    input_tokens: int = 0
    cache_creation_input_tokens: int = 0
    cache_read_input_tokens: int = 0
    output_tokens: int = 0
    duration_ms: int = 0
    cost_usd: float = 0.0
    retries: int = 0
    ok: bool = True

    @property
    def gross_tokens(self) -> int:
        return (self.input_tokens + self.cache_creation_input_tokens
                + self.cache_read_input_tokens + self.output_tokens)

    @property
    def non_cached_tokens(self) -> int:
        # max(input - cache_read, 0) + output; claude CLI reports uncached input in
        # input_tokens and cache writes separately -> non-cached = in + cache_create + out
        return self.input_tokens + self.cache_creation_input_tokens + self.output_tokens


@dataclass
class UsageLedger:
    calls: list[LlmCallRecord] = field(default_factory=list)
    started_at: float = field(default_factory=time.time)
    finished_at: Optional[float] = None

    def add(self, rec: LlmCallRecord) -> None:
        self.calls.append(rec)

    def totals(self) -> dict:
        return {
            "llm_invocations": len(self.calls),
            "gross_tokens": sum(c.gross_tokens for c in self.calls),
            "non_cached_tokens": sum(c.non_cached_tokens for c in self.calls),
            "output_tokens": sum(c.output_tokens for c in self.calls),
            "cost_usd": round(sum(c.cost_usd for c in self.calls), 4),
            "retries": sum(c.retries for c in self.calls),
            "wall_seconds": round((self.finished_at or time.time()) - self.started_at, 1),
        }


@dataclass
class RunResult:
    terminal: TerminalState
    slug: str = ""
    pr_number: Optional[int] = None
    classification: Optional[Classification] = None
    plan: Optional[PlanInfo] = None
    phase5: Optional[str] = None
    unresolved_conformance: list[str] = field(default_factory=list)
    findings: list[Finding] = field(default_factory=list)
    arm: Optional[ArmDecision] = None
    ci_outcome: str = ""
    final_pr_state: str = ""
    retrospective: Optional[dict] = None
    error: Optional[str] = None
    error_kind: Optional[str] = None   # stable HarnessError.kind of a FAILED run (e.g. "rebase-conflict")
    ledger: Optional[UsageLedger] = None
    audit: list[str] = field(default_factory=list)

    def to_json(self) -> str:
        d = asdict(self)
        if self.classification:
            d["classification"].pop("filtered_diff", None)  # keep result.json small
        return json.dumps(d, indent=1, default=str)
