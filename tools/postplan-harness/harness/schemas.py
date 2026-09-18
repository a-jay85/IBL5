"""Typed validation for every retained LLM call's output. Deterministic code
validates what the model returns; invalid output is a typed failure, never
silently accepted."""
from __future__ import annotations

import re

from .state import Classification, HarnessError

FINDING_KEYS = {"path", "line", "body"}
FINDING_ALIASES = (
    ("path", ("file",)),
    ("body", ("detail", "description", "message")),
)
MANUAL_CATEGORIES = {"cli-executable", "phpunit", "api-test", "e2e",
                     "visual-regression", "truly-manual"}
HOLD_DISCHARGE_CATEGORIES = {"decision", "cli-executable", "phpunit", "api-test",
                              "e2e", "visual-regression", "truly-manual"}
COMMIT_TYPES = {"feat", "fix", "refactor", "perf", "test", "docs", "build", "ci", "chore"}


def _normalize_finding(item):
    """One finding dict -> exactly {path, line, body}. Anything non-dict passes through.

    Never raises. A shape this cannot repair is handed on for validate_findings to reject.
    """
    if not isinstance(item, dict):
        return item
    out = {}
    for canonical, aliases in FINDING_ALIASES:
        for key in (canonical,) + aliases:
            if key in item:
                out[canonical] = item[key]
                break
    if "line" in item:
        out["line"] = item["line"]
    elif out:
        out["line"] = 0
    return out


def unwrap_findings_envelope(data):
    """Decoration layer: reshape the evidenced reply shapes into a bare findings array.

    Three tolerances, applied in this order:
      1. {"findings": [...]}                -> the inner list (unchanged behavior).
      2. a dict with TWO OR MORE keys whose values are ALL lists -> the concatenation of
         those lists, in key order. This is the sectioned reply an agent emits when it
         echoes the prompt's section headers as JSON keys.
      3. every item of the resulting list -> exactly {path, line, body}, resolving the
         aliases in FINDING_ALIASES and dropping every other key. A finding with no `line`
         gets the int sentinel 0, which passes validate_findings and routes to a file-level
         PR comment in bin/lib/post-review-findings.sh.

    Tolerance lives here, never in validate_findings. What this deliberately does NOT repair:
      * {} and any SINGLE-key dict other than {"findings": ...}. `findings` is the only
        evidenced envelope key, so a lone other key is a wrong envelope, not a section list.
        The two-key floor is what keeps {"not_findings": []} rejected, and it keeps {} from
        flattening into [] -- an empty object must never read downstream as a clean review.
      * a dict with any non-list value, e.g. {"findings": "none"}.
      * a present-but-wrong `line`: "123", None and 12.5 stay as they are and are rejected.
        Coercion there is unevidenced and would invent an anchor the model never gave.
      * list items that are not dicts; they pass through to the strict validator.
      * a finding carrying neither `path` nor `file`, or neither `body` nor any body alias.
      * any alias outside FINDING_ALIASES (`filename`, `text`, `summary`, ...).
    Everything in that list reaches the unchanged strict validator, raises HarnessError
    "llm-invalid-output", and degrades the agent loudly through review.py's existing path.
    """
    if isinstance(data, dict) and isinstance(data.get("findings"), list):
        data = data["findings"]
    elif (isinstance(data, dict) and len(data) >= 2
          and all(isinstance(v, list) for v in data.values())):
        data = [item for value in data.values() for item in value]
    if not isinstance(data, list):
        return data
    return [_normalize_finding(item) for item in data]


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


def validate_hold_discharge(data) -> None:
    """[{n, category, probe?, rationale?}] — Mode B hold-sentence classifier output.

    Each item requires int `n` and `category` in HOLD_DISCHARGE_CATEGORIES.
    `probe` is a non-empty list of str and is required iff category == "cli-executable".
    `decision` entries must carry no `probe` and no `test_hint`.
    Rejects any category outside the closed set — a permissive validator here
    would silently discharge a hallucinated category.
    """
    if not isinstance(data, list):
        raise HarnessError("schema", "hold discharge must be a JSON array")
    for i, item in enumerate(data):
        if not isinstance(item, dict) or "n" not in item or "category" not in item:
            raise HarnessError("schema", f"discharge[{i}] must have n and category")
        if not isinstance(item["n"], int):
            raise HarnessError("schema", f"discharge[{i}].n must be int")
        cat = item["category"]
        if cat not in HOLD_DISCHARGE_CATEGORIES:
            raise HarnessError("schema", f"discharge[{i}].category {cat!r} not in allowed set")
        has_probe = "probe" in item
        has_hint = "test_hint" in item
        is_cli = cat == "cli-executable"
        is_decision = cat == "decision"
        if is_decision and has_probe:
            raise HarnessError("schema", f"discharge[{i}].decision must not have probe")
        if is_decision and has_hint:
            raise HarnessError("schema", f"discharge[{i}].decision must not have test_hint")
        if is_cli and not has_probe:
            raise HarnessError("schema", f"discharge[{i}].cli-executable must have probe")
        if not is_cli and has_probe:
            raise HarnessError("schema",
                                f"discharge[{i}].probe forbidden for category {cat!r}")
        if has_probe:
            probe = item["probe"]
            if not isinstance(probe, list) or not probe:
                raise HarnessError("schema", f"discharge[{i}].probe must be non-empty list")
            if not all(isinstance(s, str) for s in probe):
                raise HarnessError("schema", f"discharge[{i}].probe elements must be strings")


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
