"""Replay three real hold decisions against the `sql-equivalent` token.

PR #3815 and PR #3870 were pure SQL speed-ups the owner had to merge by hand. With the
new token and ok evidence they reach the seat; without ok evidence they stay held.
PR #3816 changed which API key may write bug-pipeline reports, an authorization change,
so it stays owner-held in every case."""
import pytest

from harness.armable import evaluate, plan_triggers_clearable, second_review_candidates, touches_ui
from harness.planfile import frontmatter_hold_triggers
from harness.state import Classification

from tests.test_armable import inputs
from tests.test_armable_second_review import _PLAN_HOLD, _holds, _seat

# sed -n '1,4p' ~/claude-plans/record-holders-regular-season-batch-filesort.md
PR_3815_FRONTMATTER = "---\nimpl_model: sonnet\nauto_merge: false\n---\n"
# gh pr diff 3815 -R a-jay85/IBL5 --name-only
PR_3815_FILES = [
    "ibl5/classes/RecordHolders/PlayerRecordRepository.php",
    "ibl5/tests/DatabaseIntegration/PlayerRecordRepositoryTest.php",
    "ibl5/tests/RecordHolders/PlayerRecordCandidateQueryTest.php",
    "ibl5/tests/RecordHolders/PlayerRecordRepositoryBatchQueryTest.php",
]

# sed -n '1,4p' ~/claude-plans/team-records-single-game-value-cut.md
PR_3870_FRONTMATTER = "---\nimpl_model: sonnet\nauto_merge: false\n---\n"
# gh pr diff 3870 -R a-jay85/IBL5 --name-only
PR_3870_FILES = [
    "ibl5/classes/RecordHolders/TeamRecordRepository.php",
    "ibl5/tests/DatabaseIntegration/TeamSingleGameBatchCharacterizationTest.php",
    "ibl5/tests/RecordHolders/TeamSingleGameBatchQueryTest.php",
    "ibl5/tests/RecordHolders/TeamSingleGameBranchQueryTest.php",
]

# sed -n '1,4p' ~/claude-plans/bug-pipeline-service-key-guard.md (the #3816 plan). It has
# no hold_trigger: line, which keeps (7) with the owner. Gate 14(b) gives an actor-identity
# change `security-irreducible`, so the replay also checks the token a plan writes today.
PR_3816_FRONTMATTER = "---\nimpl_model: sonnet\nauto_merge: false\n---\n"
PR_3816_GATE_14B_TRIGGERS = ("security-irreducible",)
# gh pr diff 3816 -R a-jay85/IBL5 --name-only
PR_3816_FILES = [
    "ibl5/api.php",
    "ibl5/classes/Api/Controller/EnqueueController.php",
    "ibl5/classes/Api/Controller/SourceDeletedController.php",
    "ibl5/classes/Api/Controller/SourceUpdatedController.php",
    "ibl5/classes/Api/README.md",
    "ibl5/phpstan.neon",
    "ibl5/tests/Api/Controller/EnqueueControllerTest.php",
    "ibl5/tests/Api/Controller/SourceDeletedControllerTest.php",
    "ibl5/tests/Api/Controller/SourceUpdatedControllerTest.php",
    "ibl5/tests/DatabaseIntegration/Api/Controller/EnqueueControllerDatabaseTest.php",
]


def _with_hold_trigger(frontmatter: str, value: str) -> str:
    """The frontmatter with its hold_trigger: line set to `value` (added when absent)."""
    lines = [ln for ln in frontmatter.splitlines() if not ln.startswith("hold_trigger:")]
    lines.insert(len(lines) - 1, f"hold_trigger: {value}")
    return "\n".join(lines) + "\n"


def _classification(files):
    # The UI floor is its own condition, pinned by test_ui_floor_holds_*.
    return Classification(files=[f for f in files if not touches_ui([f])])


def _replay(files, triggers, evidence):
    return evaluate(inputs(**_PLAN_HOLD, plan_hold_triggers=triggers,
                           sql_equivalence_evidence=evidence,
                           classification=_classification(files), **_seat([7])))


def _assert_held(decision):
    assert not decision.armed
    assert 7 in _holds(decision)
    assert decision.second_review_cleared == []


def test_replay_frontmatter_parses_sql_equivalent_token():
    rewritten = _with_hold_trigger(PR_3815_FRONTMATTER, "sql-equivalent")
    assert frontmatter_hold_triggers(rewritten) == ("sql-equivalent",)
    near_miss = _with_hold_trigger(PR_3815_FRONTMATTER, "sql-equivalence")
    assert frontmatter_hold_triggers(near_miss) == ("sql-equivalence",)
    assert plan_triggers_clearable(("sql-equivalence",), "ok") is False


def test_replay_3815_old_triggers_held():
    old = frontmatter_hold_triggers(PR_3815_FRONTMATTER)
    assert old == ()
    decision = _replay(PR_3815_FILES, old, "ok")
    _assert_held(decision)
    assert plan_triggers_clearable(old, "ok") is False


@pytest.mark.parametrize("files", [PR_3815_FILES, PR_3870_FILES], ids=["3815", "3870"])
def test_replay_old_triggers_give_no_seat_candidates(files):
    decision = evaluate(inputs(**_PLAN_HOLD, classification=_classification(files)))
    assert second_review_candidates(inputs(**_PLAN_HOLD, classification=_classification(files)),
                                    decision.holds) == {}


def test_replay_3815_clearable_with_ok_evidence():
    decision = _replay(PR_3815_FILES, ("sql-equivalent",), "ok")
    assert decision.armed
    assert not decision.holds


def test_replay_3870_clearable_with_ok_evidence():
    decision = _replay(PR_3870_FILES, ("sql-equivalent",), "ok")
    assert decision.armed
    assert not decision.holds


@pytest.mark.parametrize("evidence", ["absent", f"stale: {PR_3815_FILES[0]}"])
def test_replay_3815_held_without_evidence(evidence):
    _assert_held(_replay(PR_3815_FILES, ("sql-equivalent",), evidence))


@pytest.mark.parametrize("evidence", ["absent", f"stale: {PR_3870_FILES[0]}"])
def test_replay_3870_held_without_evidence(evidence):
    _assert_held(_replay(PR_3870_FILES, ("sql-equivalent",), evidence))


@pytest.mark.parametrize("triggers", [
    frontmatter_hold_triggers(PR_3816_FRONTMATTER),
    PR_3816_GATE_14B_TRIGGERS,
    (*PR_3816_GATE_14B_TRIGGERS, "sql-equivalent"),
], ids=["as-merged", "gate-14b", "gate-14b-plus-sql-equivalent"])
def test_replay_3816_auth_change_stays_owner_held(triggers):
    _assert_held(_replay(PR_3816_FILES, triggers, "ok"))
