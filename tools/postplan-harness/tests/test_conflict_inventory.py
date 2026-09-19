"""Unit tests for conflict.py inventory and classifier — no git repo, no network."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conflict import (
    ConflictInventory,
    UNRESOLVABLE_EMPTY,
    UNRESOLVABLE_LOCKFILE,
    UNRESOLVABLE_MIGRATION,
    UNRESOLVABLE_STAGES,
    inventory_conflicts,
    parse_unmerged,
)


def _run_with(text: str):
    """Return a stub run() that returns text for ls-files --unmerged."""
    def run(*args, **kwargs):
        if args[0] == "ls-files":
            return text
        return ""
    return run


def test_all_resolvable():
    text = (
        "100644 aabbcc 1\tapp/foo.py\n"
        "100644 aabbcd 2\tapp/foo.py\n"
        "100644 aabbce 3\tapp/foo.py\n"
        "100644 bbbbcc 1\tapp/bar.py\n"
        "100644 bbbbcd 2\tapp/bar.py\n"
        "100644 bbbbce 3\tapp/bar.py\n"
    )
    inv = inventory_conflicts(_run_with(text))
    assert inv.unresolvable_reason is None
    assert inv.files == ("app/bar.py", "app/foo.py")


def test_migration_unresolvable():
    text = (
        "100644 aabbcc 1\tibl5/migrations/0001_foo.sql\n"
        "100644 aabbcd 2\tibl5/migrations/0001_foo.sql\n"
        "100644 aabbce 3\tibl5/migrations/0001_foo.sql\n"
    )
    inv = inventory_conflicts(_run_with(text))
    assert inv.unresolvable_reason is not None
    assert inv.unresolvable_reason.startswith(UNRESOLVABLE_MIGRATION + ":")
    assert inv.files == ()


def test_lockfile_unresolvable():
    text = (
        "100644 aabbcc 1\tcomposer.lock\n"
        "100644 aabbcd 2\tcomposer.lock\n"
        "100644 aabbce 3\tcomposer.lock\n"
    )
    inv = inventory_conflicts(_run_with(text))
    assert inv.unresolvable_reason is not None
    assert inv.unresolvable_reason.startswith(UNRESOLVABLE_LOCKFILE + ":")
    assert inv.files == ()


def test_delete_vs_modify():
    # stages 1 and 2 only — delete/add case
    text = (
        "100644 aabbcc 1\tapp/foo.py\n"
        "100644 aabbcd 2\tapp/foo.py\n"
    )
    inv = inventory_conflicts(_run_with(text))
    assert inv.unresolvable_reason is not None
    assert UNRESOLVABLE_STAGES in inv.unresolvable_reason
    assert inv.files == ()


def test_add_add():
    # stages 2 and 3 only — add/add case
    text = (
        "100644 aabbcd 2\tapp/foo.py\n"
        "100644 aabbce 3\tapp/foo.py\n"
    )
    inv = inventory_conflicts(_run_with(text))
    assert inv.unresolvable_reason is not None
    assert UNRESOLVABLE_STAGES in inv.unresolvable_reason
    assert inv.files == ()


def test_empty_output():
    inv = inventory_conflicts(_run_with(""))
    assert inv.unresolvable_reason == UNRESOLVABLE_EMPTY
    assert inv.files == ()


def test_malformed_line():
    text = "not a tab separated line\n"
    with pytest.raises(ValueError):
        parse_unmerged(text)
