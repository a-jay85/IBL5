"""A harm-none note naming a repo-rooted website path without the ibl5/ prefix is kept."""
import os
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from harness import fidelity
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger


def _extract(tmp_path, detail):
    verdict = tmp_path / "verdict.md"
    verdict.write_text("6d checks\n\nREADY WITH NOTES\n\n- finding one\n\n## DIGEST\nstuff\n")
    notes = [{"title": "Tidy this", "kind": "followup", "harm": "none", "detail": detail}]
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": notes})
    return fidelity.extract_notes(llm, str(verdict))


@pytest.mark.parametrize("path", [
    "classes/Waivers/WaiversView.php", "modules/Waivers/index.php",
    "themes/IBL/style.css", "migrations/001_a.sql", "includes/a.php", "blocks/a.php",
    "language/a.php", "config/a.php", "jslib/a.js", "design/a.md",
])
def test_prefixless_website_path_counts_as_website(tmp_path, path):
    detail = f"bin/check-x flags {path} wrongly."
    assert [n["title"] for n in _extract(tmp_path, detail)] == ["Tidy this"]


@pytest.mark.parametrize("detail", [
    "bin/check-x could assert more.",
    "tools/postplan-harness/harness/a.py could assert more.",
    "tools/classes/a.py could assert more.",
    ".claude/rules/a.md could say more.",
    "ibl5/docs/decisions/0001-a.md could say more.",
])
def test_tooling_paths_stay_tooling(tmp_path, detail):
    assert _extract(tmp_path, detail) == []
