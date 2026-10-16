"""Drift tests that pin MODEL_MAP to the committed current-models list.

bin/lib/current-models is the single source of truth for live Claude model ids.
These tests fail when MODEL_MAP names an id the list does not carry, or when a
tier drifts off the 5.5 ids.
"""
from __future__ import annotations

import os
import re
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import MODEL_MAP  # noqa: E402

REPO = Path(__file__).resolve().parents[3]
CURRENT = REPO / "bin" / "lib" / "current-models"

_ID_RE = re.compile(r"^claude-(opus|sonnet|haiku|fable)-[0-9]+(-[0-9]+)*$")


def _read_current_models(path: Path) -> list[str]:
    ids: list[str] = []
    for line in Path(path).read_text().splitlines():
        entry = line.split("#", 1)[0].strip()
        if entry:
            ids.append(entry)
    return ids


def test_current_models_list_is_well_formed() -> None:
    ids = _read_current_models(CURRENT)
    assert ids, "bin/lib/current-models lists no ids"
    for model_id in ids:
        assert _ID_RE.fullmatch(model_id), f"malformed model id: {model_id!r}"
    assert len(ids) == len(set(ids)), "duplicate ids in bin/lib/current-models"


def test_current_models_parser_ignores_comments_and_blanks(tmp_path: Path) -> None:
    fixture = tmp_path / "current-models"
    fixture.write_text(
        "# claude-sonnet-4-6 retired\n"
        "\n"
        "claude-opus-5-5   # trailing note\n"
    )
    assert _read_current_models(fixture) == ["claude-opus-5-5"]


def test_model_map_values_are_current_models() -> None:
    current = set(_read_current_models(CURRENT))
    offending = set(MODEL_MAP.values()) - current
    assert not offending, f"MODEL_MAP carries ids absent from current-models: {sorted(offending)}"


def test_model_map_tier_ids_are_5_5() -> None:
    assert MODEL_MAP == {
        "haiku": "claude-haiku-5-5",
        "sonnet": "claude-sonnet-5-5",
        "opus": "claude-opus-5-5",
    }
