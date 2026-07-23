"""Typed validation for every retained LLM call's output. Deterministic code
validates what the model returns; invalid output is a typed failure, never
silently accepted."""
from __future__ import annotations

from .state import HarnessError

FINDING_KEYS = {"path", "line", "body"}
MANUAL_CATEGORIES = {"cli-executable", "phpunit", "api-test", "e2e",
                     "visual-regression", "truly-manual"}
COMMIT_TYPES = {"feat", "fix", "refactor", "perf", "test", "docs", "build", "ci", "chore"}


def validate_findings(data) -> None:
    """[{path, line, body, agent?}] — code-review / security-audit output."""
    if not isinstance(data, list):
        raise HarnessError("schema", "findings must be a JSON array")
    for i, f in enumerate(data):
        if not isinstance(f, dict) or not FINDING_KEYS.issubset(f):
            raise HarnessError("schema", f"finding[{i}] missing keys {FINDING_KEYS}")
        if not isinstance(f["line"], int):
            raise HarnessError("schema", f"finding[{i}].line must be int")
        if not isinstance(f["path"], str) or not isinstance(f["body"], str):
            raise HarnessError("schema", f"finding[{i}] path/body must be strings")


def validate_scores(data) -> None:
    """[{n, score}] — the rubric scoring call (0-100)."""
    if not isinstance(data, list):
        raise HarnessError("schema", "scores must be a JSON array")
    for i, s in enumerate(data):
        if not isinstance(s, dict) or "n" not in s or "score" not in s:
            raise HarnessError("schema", f"score[{i}] needs n and score")
        if not isinstance(s["score"], int) or not 0 <= s["score"] <= 100:
            raise HarnessError("schema", f"score[{i}].score must be int 0-100")


def validate_manual_classification(data) -> None:
    """[{step, category, rationale}] — Phase 6 QA classification."""
    if not isinstance(data, list):
        raise HarnessError("schema", "classification must be a JSON array")
    for i, s in enumerate(data):
        if not isinstance(s, dict) or "step" not in s or "category" not in s:
            raise HarnessError("schema", f"item[{i}] needs step and category")
        if s["category"] not in MANUAL_CATEGORIES:
            raise HarnessError("schema", f"item[{i}].category {s['category']!r} not in {MANUAL_CATEGORIES}")


def validate_pr_copy(data) -> None:
    """{type, title, summary_md} — commit/PR copy generation."""
    if not isinstance(data, dict):
        raise HarnessError("schema", "pr copy must be a JSON object")
    for k in ("type", "title", "summary_md"):
        if k not in data or not isinstance(data[k], str):
            raise HarnessError("schema", f"pr copy missing string field {k!r}")
    if data["type"] not in COMMIT_TYPES:
        raise HarnessError("schema", f"type {data['type']!r} not a conventional-commit type")
    if not data["title"].lower().startswith(data["type"]):
        raise HarnessError("schema", "title must start with its conventional-commit type")


def validate_safety_verdict(data) -> None:
    """{holds: [str]} — condition (9) bounded verdict; may only ADD holds."""
    if not isinstance(data, dict) or "holds" not in data or not isinstance(data["holds"], list):
        raise HarnessError("schema", "safety verdict must be {holds: [...]}")
    for h in data["holds"]:
        if not isinstance(h, str):
            raise HarnessError("schema", "each hold must be a string")


def validate_retrospective(data) -> None:
    """{save: bool, name?, body?} — Phase 9 typed output."""
    if not isinstance(data, dict) or not isinstance(data.get("save"), bool):
        raise HarnessError("schema", "retrospective must be {save: bool, ...}")
    if data["save"] and not (data.get("name") and data.get("body")):
        raise HarnessError("schema", "save=true requires name and body")
