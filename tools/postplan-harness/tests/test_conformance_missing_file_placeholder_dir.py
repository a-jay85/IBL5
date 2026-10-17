"""Conformance tests for the `NNN_` migration placeholder token and full-path directory tokens.

Each test is named for the mutation it kills. Positive pins prove the two tolerances;
negative pins prove every wider reading stays MISSING.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import _resolve, check
from harness.state import PlanInfo


def _plan_with_critical(*paths: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True,
                    critical_files=[(p, "", False) for p in paths])


def _missing(items, needle):
    return any("MISSING-FILE" in i and needle in i for i in items)


_PR3281_FILES = (
    [f"ibl5/classes/TradeSuggestions/{n}.php" for n in (
        "TradeSuggestionService", "TradeSuggestionRepository", "TradeSuggestionRenderer",
        "TradeCandidate", "TradeScorer", "TeamTradeMode", "TradeModeRepository",
        "SuggestionFilter", "RosterFitEvaluator", "CapFitEvaluator", "README")]
    + [f"ibl5/tests/TradeSuggestions/{n}Test.php" for n in (
        "TradeSuggestionService", "TradeSuggestionRepository", "TradeSuggestionRenderer",
        "TradeScorer", "TeamTradeMode", "SuggestionFilter", "RosterFitEvaluator",
        "CapFitEvaluator")]
    + ["ibl5/migrations/196_team_trade_mode.sql",
       "ibl5/modules/GmHome/index.php",
       "ibl5/classes/GmHome/GmHomeView.php",
       "ibl5/tests/e2e/gm-home/trade-suggestions.spec.ts"]
)
_PR3281_CRITICAL = (
    "ibl5/migrations/NNN_team_trade_mode.sql",
    "ibl5/classes/TradeSuggestions/",
    "ibl5/tests/TradeSuggestions/",
    "ibl5/themes/IBL/style/style.css",
)

_TS_FILES = [
    "ibl5/classes/TradeSuggestions/Service.php",
    "ibl5/tests/TradeSuggestions/ServiceTest.php",
]


def test_full_path_dir_token_resolves_despite_same_named_sibling_dir():
    """Mutation caught: delete the new `if "/" in tok:` block in `_resolve`."""
    items = check(_plan_with_critical("ibl5/classes/TradeSuggestions/"), _TS_FILES)
    assert not _missing(items, "TradeSuggestions")


def test_full_path_dir_token_returns_directory_itself():
    """Mutation caught: `return f` (the first matching file) instead of `return tok`."""
    assert _resolve("ibl5/classes/TradeSuggestions/", _TS_FILES) == "ibl5/classes/TradeSuggestions"


def test_dir_token_resolves_when_changed_list_holds_only_paths_under_it():
    """Mutation caught: guard the directory rule with `os.path.isdir(tok)`.

    A deleted tree lists the same strings, so this stands in for a deletion.
    """
    files = ["ibl5/classes/Gone/A.php", "ibl5/classes/Gone/B.php", "ibl5/tests/Gone/ATest.php"]
    items = check(_plan_with_critical("ibl5/classes/Gone/"), files)
    assert not _missing(items, "ibl5/classes/Gone/")


def test_pr3281_shape_clears_three_false_items_keeps_style_css():
    """Mutation caught: either Phase 2 change reverted, or the dir rule returning `tok`
    for any `"/" in tok` without the `any(...)` test (drops the style.css item)."""
    items = check(_plan_with_critical(*_PR3281_CRITICAL), _PR3281_FILES)
    assert [i for i in items if i.startswith("MISSING-FILE")] == [
        "MISSING-FILE: ibl5/themes/IBL/style/style.css (plan Critical File never appeared in the diff)"
    ]


def test_migration_nnn_placeholder_different_suffix_stays_missing():
    """Mutation caught: drop `n.group("suffix") == suffix` in `_renumbered_migration`."""
    items = check(_plan_with_critical("ibl5/migrations/NNN_add_thing.sql"),
                  ["ibl5/migrations/123_other_thing.sql"])
    assert _missing(items, "NNN_add_thing.sql")


def test_migration_nnn_placeholder_two_same_suffix_hits_stays_missing():
    """Mutation caught: `hits[0] if hits else None` in `_renumbered_migration`."""
    items = check(_plan_with_critical("ibl5/migrations/NNN_add_thing.sql"),
                  ["ibl5/migrations/123_add_thing.sql", "ibl5/migrations/124_add_thing.sql"])
    assert _missing(items, "NNN_add_thing.sql")


def test_migration_lowercase_nnn_gets_no_tolerance():
    """Mutation caught: `re.IGNORECASE` on `_MIGRATION_TOKEN`."""
    items = check(_plan_with_critical("ibl5/migrations/nnn_add_thing.sql"),
                  ["ibl5/migrations/123_add_thing.sql"])
    assert _missing(items, "nnn_add_thing.sql")


def test_migration_four_n_gets_no_tolerance():
    """Mutation caught: `N{3,4}` (the `_MIGRATION_PLACEHOLDER_TOKEN` class) in `_MIGRATION_TOKEN`."""
    items = check(_plan_with_critical("ibl5/migrations/NNNN_add_thing.sql"),
                  ["ibl5/migrations/123_add_thing.sql"])
    assert _missing(items, "NNNN_add_thing.sql")


def test_migration_five_n_gets_no_tolerance():
    """Mutation caught: `N+` in `_MIGRATION_TOKEN`."""
    items = check(_plan_with_critical("ibl5/migrations/NNNNN_add_thing.sql"),
                  ["ibl5/migrations/123_add_thing.sql"])
    assert _missing(items, "NNNNN_add_thing.sql")


def test_adr_nnnn_token_does_not_leak_into_migration_tier():
    """Mutation caught: widen `_MIGRATION_TOKEN`'s prefix to
    `ibl5/(?:migrations|docs/decisions)/` with separator `[_-]`."""
    items = check(_plan_with_critical("ibl5/docs/decisions/NNNN-add-thing.md"),
                  ["ibl5/migrations/123_add-thing.md"])
    assert _missing(items, "NNNN-add-thing.md")


def test_migration_numbered_token_placeholder_changed_file_stays_missing():
    """Mutation caught: use `_MIGRATION_TOKEN` in the changed-file loop of
    `_renumbered_migration` (the changed side must stay `\\d+`)."""
    items = check(_plan_with_critical("ibl5/migrations/123_add_thing.sql"),
                  ["ibl5/migrations/NNN_add_thing.sql"])
    assert _missing(items, "123_add_thing.sql")


def test_short_dir_token_with_two_same_named_dirs_stays_missing():
    """Mutation caught: replace `f.startswith(prefix)` with `("/" + prefix) in ("/" + f)`."""
    items = check(_plan_with_critical("classes/TradeSuggestions/"),
                  ["ibl5/classes/TradeSuggestions/S.php", "ibl5/tests/TradeSuggestions/ST.php"])
    assert _missing(items, "classes/TradeSuggestions")


def test_top_level_dir_token_keeps_tier2_ambiguity():
    """Mutation caught: drop the `"/" in tok` guard (bare `bin/` would resolve on `bin/foo`)."""
    items = check(_plan_with_critical("bin/"), ["bin/foo", "ibl5/bin/foo"])
    assert _missing(items, "bin")


def test_prefix_collision_dir_token_stays_missing():
    """Mutation caught: drop the `+ "/"` from `prefix`.

    `ibl5/classes/Trade` does not match `ibl5/classes/TradeSuggestions/…`.
    """
    items = check(_plan_with_critical("ibl5/classes/Trade"),
                  ["ibl5/classes/TradeSuggestions/S.php"])
    assert _missing(items, "ibl5/classes/Trade")


def test_file_token_is_never_treated_as_its_parent_dir():
    """Mutation caught: apply the directory rule to `PurePosixPath(tok).parent`."""
    items = check(_plan_with_critical("ibl5/migrations/README.md"),
                  ["ibl5/classes/A/README.md", "ibl5/classes/B/README.md",
                   "ibl5/migrations/197_x.sql"])
    assert _missing(items, "ibl5/migrations/README.md")
