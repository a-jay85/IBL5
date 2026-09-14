"""Typed validation for every retained LLM call's output. Deterministic code
validates what the model returns; invalid output is a typed failure, never
silently accepted."""
from __future__ import annotations

import re

from .state import Classification, HarnessError

FINDING_KEYS = {"path", "line", "body"}
MANUAL_CATEGORIES = {"cli-executable", "phpunit", "api-test", "e2e",
                     "visual-regression", "truly-manual"}
COMMIT_TYPES = {"feat", "fix", "refactor", "perf", "test", "docs", "build", "ci", "chore"}


def unwrap_findings_envelope(data):
    """Decoration layer: unwrap a {"findings": [...]} envelope; pass everything else through.

    Tolerance lives here, never in validate_findings — a dict whose "findings" value is not a
    list, or a dict with any other key, is returned unchanged so the strict validator still
    rejects it. This is the only shape the layer knows how to unwrap.
    """
    if isinstance(data, dict) and isinstance(data.get("findings"), list):
        return data["findings"]
    return data


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


def validate_manual_recheck(data) -> None:
    """[{n, hold: true} | {n, probe: [str, ...]}] — Phase 6 re-check output."""
    if not isinstance(data, list):
        raise HarnessError("schema", "manual recheck must be a JSON array")
    for i, item in enumerate(data):
        if not isinstance(item, dict) or "n" not in item:
            raise HarnessError("schema", f"recheck[{i}] must be a dict with 'n'")
        if not isinstance(item["n"], int):
            raise HarnessError("schema", f"recheck[{i}].n must be int")
        has_hold = "hold" in item
        has_probe = "probe" in item
        if has_hold and has_probe:
            raise HarnessError("schema", f"recheck[{i}] must have hold OR probe, not both")
        if not has_hold and not has_probe:
            raise HarnessError("schema", f"recheck[{i}] must have hold or probe")
        if has_hold and item["hold"] is not True:
            raise HarnessError("schema", f"recheck[{i}].hold must be true")
        if has_probe:
            probe = item["probe"]
            if not isinstance(probe, list) or not probe:
                raise HarnessError("schema", f"recheck[{i}].probe must be a non-empty list")
            if not all(isinstance(s, str) for s in probe):
                raise HarnessError("schema", f"recheck[{i}].probe elements must be strings")


def validate_pr_copy(data) -> None:
    """{type, title, commit_subject, summary_md} — commit/PR copy generation.

    `title` is the PR title; `commit_subject` is the git commit subject. They are separate
    artifacts and both must start with `type`.
    """
    if not isinstance(data, dict):
        raise HarnessError("schema", "pr copy must be a JSON object")
    for k in ("type", "title", "summary_md", "commit_subject"):
        if k not in data or not isinstance(data[k], str):
            raise HarnessError("schema", f"pr copy missing string field {k!r}")
    if data["type"] not in COMMIT_TYPES:
        raise HarnessError("schema", f"type {data['type']!r} not a conventional-commit type")
    if not data["title"].lower().startswith(data["type"]):
        raise HarnessError("schema", "title must start with its conventional-commit type")
    if not data["commit_subject"].lower().startswith(data["type"]):
        raise HarnessError("schema", "commit_subject must start with its conventional-commit type")


def coerce_commit_subject(subject: str, cls: Classification) -> str:
    """Decoration layer: re-type a commit subject against what the diff actually contains.

    Never raises — the caller always gets a usable subject back. An unparseable subject is
    returned byte-identical (that is `validate_pr_copy`'s problem, not this function's), and a
    classification with no *_only flag set leaves the subject unchanged.

    The flag check order is load-bearing: `count_non_code = count_md + count_lock +
    count_snapshot` in classify.py, so a docs-only set also satisfies `non_code_only`. Checking
    `docs_only` first is what makes `chore:` + docs-only coerce to `docs:` rather than being
    waved through as an already-allowed `chore` on the `non_code_only` branch.
    """
    m = re.match(r"^([a-z]+)(\([^)]*\))?(!)?:", subject)
    if not m:
        return subject
    parsed, scope, bang = m.group(1), m.group(2) or "", m.group(3) or ""
    if cls.docs_only:
        allowed, coerce_to = {"docs"}, "docs"
    elif cls.test_only:
        allowed, coerce_to = {"test", "chore"}, "test"
    elif cls.non_code_only:
        allowed, coerce_to = {"chore", "docs", "build", "ci"}, "chore"
    else:
        return subject
    if parsed in allowed:
        return subject
    return coerce_to + scope + bang + subject[m.end() - 1:]


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
