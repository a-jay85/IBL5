"""`hold_trigger:` frontmatter parsing. Arming reads it to decide whether a
plan-time hold is reviewer-clearable, so a miss must never yield a token."""
from harness.planfile import frontmatter_hold_triggers


def test_hold_triggers_parses_comma_list():
    content = "---\nauto_merge: false\nhold_trigger: security-surface, golden-snapshot\n---\n# T\n"
    assert frontmatter_hold_triggers(content) == ("security-surface", "golden-snapshot")


def test_hold_triggers_absent_returns_empty():
    assert frontmatter_hold_triggers("---\nauto_merge: false\n---\n# T\n") == ()
    assert frontmatter_hold_triggers("# No frontmatter\n") == ()
    assert frontmatter_hold_triggers("") == ()


def test_hold_triggers_near_miss_kept_as_unknown_token():
    content = "---\nauto_merge: false\nhold_trigger: security-surface and ui\n---\n# T\n"
    assert frontmatter_hold_triggers(content) == ("security-surface and ui",)
    body_only = "---\nauto_merge: false\n---\n# T\n\nhold_trigger: ui\n"
    assert frontmatter_hold_triggers(body_only) == ()
