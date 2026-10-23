"""Phase 4D verifier votes: scored-row contract, verdict schema, vote aggregation,
verifier fan-out, and the vote record.

The first four tests are characterization tests: they pin the properties of 4D that
reviewcache, statefile and fidelity consume, and they held before the text-only scorer
was replaced.
"""
from __future__ import annotations

import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import schemas
from harness.review import (CODE_THRESHOLD, SEC_THRESHOLD, VERIFIER_ALLOWED_TOOLS,
                            VERIFIER_COUNT, VERIFIER_DENIED_TOOLS, VERIFIER_MAX_BUDGET_USD,
                            VERIFIER_MAX_TURNS, VERIFIER_MODEL, VERIFIER_PACKET_BUDGET,
                            VERIFIER_PURPOSES, VERIFIER_TIMEOUT, ReviewPhase, _diff_by_path,
                            aggregate_votes, chunk_findings, verifier_prompt)
from harness.state import Classification, Finding, HarnessError, LlmCallRecord, PlanInfo, UsageLedger

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


class _DualLlm:
    """Toolless `call` serves the review agents; `call_tooled` serves the verifiers.
    Canned replies are keyed by purpose."""

    def __init__(self, canned: dict):
        self.canned = canned
        self.ledger = UsageLedger()
        self.tooled_calls: list[dict] = []          # kwargs of every call_tooled, in order
        self.out_dir = None
        self.workdir = None

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        data = self.canned[purpose]
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fake:{model}"))
        return data

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools=(), **kw):
        self.tooled_calls.append({"purpose": purpose, "model": model, "cwd": cwd,
                                  "allowed_tools": tuple(allowed_tools), "prompt": prompt, **kw})
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        val = self.canned[purpose]
        if isinstance(val, dict) and "raise" in val:
            raise HarnessError(val["raise"]["kind"], val["raise"].get("detail", ""))
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fake:{model}"))
        return val


class _RecordingGh:
    def __init__(self):
        self.findings_posts: list[tuple] = []      # (pr, sha, title, findings)
        self.summary_posts: list[tuple] = []       # (pr, title, text)

    def post_review_findings(self, pr, sha, title, findings):
        self.findings_posts.append((pr, sha, title, list(findings)))

    def post_review_summary(self, pr, title, text):
        self.summary_posts.append((pr, title, text))


def _cls(**kwargs) -> Classification:
    """Classification enabling agent A plus security (B, C, D off)."""
    c = Classification()
    c.has_php = True
    c.has_modified = True
    c.lines_php_changed = 10
    c.has_comments_in_diff = False
    c.non_code_only = False
    c.engine_only = False
    c.has_e2e_specs = False
    c.filtered_diff = ("diff --git a/ibl5/classes/TeamRepository.php b/ibl5/classes/TeamRepository.php\n"
                       "--- a/ibl5/classes/TeamRepository.php\n"
                       "+++ b/ibl5/classes/TeamRepository.php\n"
                       "@@ -85,3 +85,4 @@\n"
                       "+        $rows = $this->fetchAll('SELECT * FROM ibl_box_scores');\n")
    c.files = ["ibl5/classes/TeamRepository.php"]
    for k, v in kwargs.items():
        setattr(c, k, v)
    return c


META = {"number": 42, "title": "t", "headRefOid": "abc123"}

ONE_REAL = '[{"n": 1, "verdict": "real", "reason": "unbounded fetchAll confirmed at line 88"}]'
CANNED = {
    "review-agent-a": [{"path": "ibl5/classes/TeamRepository.php", "line": 88, "body": "unbounded fetchAll"}],
    "security-audit": [],
    "verify-findings-1": ONE_REAL, "verify-findings-2": ONE_REAL, "verify-findings-3": ONE_REAL,
}


def _all_real(count): return json.dumps([{"n": i, "verdict": "real", "reason": "verified"} for i in range(1, count + 1)])


def _vote(n, verdict, reason="r"):
    return {"n": n, "verdict": verdict, "reason": reason}


def _reply(*votes):
    return json.dumps(list(votes))


def _seats(reply, **override):
    """The same raw reply for all three seats; `override` maps a seat number (as `s2=`) to another reply."""
    out = {p: reply for p in VERIFIER_PURPOSES}
    for k, v in override.items():
        out[f"verify-findings-{k[1:]}"] = v
    return out


def _canned(*, a=(), security=(), **seat_replies):
    """Agent findings given as (path, line, body) tuples; seat replies keyed by purpose."""
    canned = {
        "review-agent-a": [{"path": p, "line": ln, "body": b} for p, ln, b in a],
        "security-audit": [{"path": p, "line": ln, "body": b} for p, ln, b in security],
    }
    canned.update(seat_replies)
    return canned


def _phase_run(tmp_path, canned=None, *, cls=None, lines=None, out_dir=None, **phase_kw):
    llm = _DualLlm(dict(canned or CANNED))
    llm.out_dir = out_dir
    gh = _RecordingGh()
    phase_kw.setdefault("worktree", str(tmp_path))
    phase = ReviewPhase(llm, gh, log=lines.append if lines is not None else None, **phase_kw)
    result = phase.run(META, cls or _cls(), PlanInfo())
    return phase, result, llm, gh


def _run(tmp_path, canned=None, **phase_kw):
    _phase, result, llm, gh = _phase_run(tmp_path, canned, **phase_kw)
    return result, llm, gh


def _f(source="code-review", agent="A", path="ibl5/a.php", line=1, body="issue"):
    return Finding(source=source, agent=agent, path=path, line=line, body=body)


def _key(f: Finding) -> tuple:
    return (f.source, f.agent, f.path, f.line, f.body)


# ---------------------------------------------------------------------------
# Characterization (pre-impl rows 1-4)
# ---------------------------------------------------------------------------


def test_scored_rows_keep_legacy_key_set(tmp_path):
    (findings, _gates, scored, _degraded), _llm, _gh = _run(tmp_path)
    assert scored, "expected at least one scored row"
    for row in scored:
        assert set(row) == {"source", "agent", "path", "line", "score", "label", "body_head"}
    assert len(scored) == len(findings)


def test_thresholds_are_80_code_and_75_security():
    assert CODE_THRESHOLD == 80
    assert SEC_THRESHOLD == 75


def test_run_posts_only_a_subset_of_agent_findings(tmp_path):
    (findings, _gates, _scored, _degraded), _llm, gh = _run(tmp_path)
    agent_keys = {(s, a, p, ln, b) for (s, a, p, ln, b) in
                  (("code-review", "A", f["path"], f["line"], f["body"])
                   for f in CANNED["review-agent-a"])}
    posted = [f for (_pr, _sha, _title, fs) in gh.findings_posts for f in fs]
    assert posted, "expected the confirmed finding to be posted"
    posted_keys = [_key(f) for f in posted]
    assert len(posted_keys) == len(set(posted_keys)), "a finding was posted twice"
    for k in posted_keys:
        assert k in agent_keys, f"posted finding {k} was not returned by any agent"
    assert {_key(f) for f in findings} <= agent_keys


def test_run_returns_four_tuple(tmp_path):
    result, _llm, _gh = _run(tmp_path)
    assert len(result) == 4
    assert isinstance(result[1], dict)
    assert isinstance(result[2], list)
    assert isinstance(result[3], list)


# ---------------------------------------------------------------------------
# Phase 2: verdict schema
# ---------------------------------------------------------------------------


def test_validate_verdicts_accepts_canonical_reply():
    schemas.validate_verdicts([_vote(1, "real", "read the file"), {"n": 2, "verdict": "not-real"}])
    schemas.validate_verdicts([])          # a verifier handed nothing to judge may return []


BAD_VERDICT_REPLIES = [
    [{"n": 1, "verdict": "maybe", "reason": "x"}],        # unknown verdict
    [{"n": 0, "verdict": "real", "reason": "x"}],         # n must be >= 1
    [{"n": "1", "verdict": "real", "reason": "x"}],       # n must be an int
    [{"n": True, "verdict": "real", "reason": "x"}],      # bool is not a finding number
    [{"verdict": "real", "reason": "x"}],                 # n missing
    [{"n": 1, "verdict": "real", "reason": 5}],           # reason must be a string
    {"n": 1, "verdict": "real"},                          # not a list
]


def test_validate_verdicts_rejects_unknown_verdict_and_bad_n():
    for bad in BAD_VERDICT_REPLIES:
        with pytest.raises(HarnessError) as ei:
            schemas.validate_verdicts(bad)
        assert ei.value.kind == "schema", bad


def test_unwrap_verdicts_envelope_unwraps_single_list_key():
    inner = [_vote(1, "real")]
    for key in ("verdicts", "votes", "results", "findings", "items"):
        assert schemas.unwrap_verdicts_envelope({key: inner}) == inner
    assert schemas.unwrap_verdicts_envelope(inner) == inner        # a bare list passes through


def test_unwrap_verdicts_boolean_real_alias():
    out = schemas.unwrap_verdicts_envelope([
        {"n": 1, "real": True}, {"n": 2, "real": False},
        {"n": 3, "verdict": True}, {"n": "4", "vote": "Not_Real"},
    ])
    assert [(v["n"], v["verdict"]) for v in out] == [
        (1, "real"), (2, "not-real"), (3, "real"), (4, "not-real")]
    schemas.validate_verdicts(out)         # every folded alias lands inside the schema


def test_unwrap_verdicts_unknown_shape_reaches_validator_unchanged():
    unknown = {"a": [], "b": []}
    assert schemas.unwrap_verdicts_envelope(unknown) is unknown
    ambiguous = {"verdicts": [], "votes": []}                      # two candidate list keys
    assert schemas.unwrap_verdicts_envelope(ambiguous) is ambiguous
    with pytest.raises(HarnessError) as ei:
        schemas.validate_verdicts(unknown)
    assert ei.value.kind == "schema"


# ---------------------------------------------------------------------------
# Phase 3: vote aggregation (aggregate_votes called directly)
# ---------------------------------------------------------------------------


def _sv(verdict, errors=None):
    """One seat's votes over finding 0; None means no vote (abstain)."""
    return {"votes": {} if verdict is None else {0: (verdict, "r")}, "errors": errors or {}}


def test_aggregate_unanimous_not_real_drops():
    rec, = aggregate_votes([_f()], [_sv("not-real"), _sv("not-real"), _sv("not-real")])
    assert rec["kept"] is False
    assert rec["score"] == 0
    assert (rec["valid_real"], rec["valid_not_real"], rec["abstain"]) == (0, 3, 0)
    assert rec["fallback"] is False


def test_aggregate_two_not_real_one_real_keeps_at_87():
    rec, = aggregate_votes([_f()], [_sv("not-real"), _sv("real"), _sv("not-real")])
    assert rec["kept"] is True
    assert rec["score"] == 87                 # 80 + round(20 * 1 / 3)
    assert (rec["valid_real"], rec["valid_not_real"]) == (1, 2)


SCORE_TABLE = [
    ("code-review", 3, 100), ("code-review", 2, 93), ("code-review", 1, 87), ("code-review", 0, 80),
    ("security-audit", 3, 100), ("security-audit", 2, 92), ("security-audit", 1, 83),
    ("security-audit", 0, 75),
]


def test_aggregate_score_table_code_and_security():
    for source, real, expected in SCORE_TABLE:
        # `real` seats vote real; the remaining seats vote not-real, except that zero real votes
        # uses two not-real and one abstention (three not-real would drop the finding).
        seats = [_sv("real")] * real + [_sv("not-real")] * (VERIFIER_COUNT - real)
        if real == 0:
            seats[-1] = _sv(None)
        agent = "security" if source == "security-audit" else "A"
        rec, = aggregate_votes([_f(source=source, agent=agent)], seats)
        assert rec["kept"] is True, (source, real)
        assert rec["score"] == expected, (source, real)
        assert rec["score"] >= (SEC_THRESHOLD if source == "security-audit" else CODE_THRESHOLD)


def test_aggregate_all_abstain_keeps_with_fallback_flag():
    rec, = aggregate_votes([_f()], [_sv(None), _sv(None, errors={0: "llm-timeout"}), _sv(None)])
    assert rec["kept"] is True
    assert rec["fallback"] is True
    assert rec["score"] == CODE_THRESHOLD
    assert rec["abstain"] == 3
    assert [v["verdict"] for v in rec["votes"]] == ["abstain"] * 3
    assert [v["error"] for v in rec["votes"]] == ["missing-vote", "llm-timeout", "missing-vote"]


# ---------------------------------------------------------------------------
# Phase 3: verifier fan-out through ReviewPhase.run
# ---------------------------------------------------------------------------

A_FINDING = ("ibl5/classes/TeamRepository.php", 88, "unbounded fetchAll")


def test_verifier_error_counts_as_abstain_and_keeps(tmp_path):
    raise_timeout = {"raise": {"kind": "llm-timeout"}}
    canned = _canned(a=[A_FINDING], **_seats(raise_timeout))
    phase, (surviving, _g, scored, _d), llm, _gh = _phase_run(tmp_path, canned)
    assert len(llm.tooled_calls) == VERIFIER_COUNT
    assert len(surviving) == 1                          # a verifier outage never drops a finding
    assert scored[0]["score"] == CODE_THRESHOLD
    rec = phase.last_votes[0]
    assert rec["fallback"] is True and rec["abstain"] == 3
    assert {v["error"] for v in rec["votes"]} == {"llm-timeout"}


def test_fixture_missing_stays_terminal(tmp_path):
    canned = _canned(a=[A_FINDING], **_seats(ONE_REAL))
    del canned["verify-findings-2"]
    with pytest.raises(HarnessError) as ei:
        _phase_run(tmp_path, canned)
    assert ei.value.kind == "llm-fixture-missing"


def test_missing_n_in_reply_is_abstain_not_drop(tmp_path):
    # Every seat judges finding 1 only (not-real); finding 2 is never mentioned.
    canned = _canned(a=[A_FINDING, ("ibl5/b.php", 2, "second issue")],
                     **_seats(_reply(_vote(1, "not-real"))))
    phase, (surviving, _g, scored, _d), _llm, _gh = _phase_run(tmp_path, canned)
    first, second = phase.last_votes
    assert first["kept"] is False and first["score"] == 0
    assert second["kept"] is True and second["abstain"] == 3 and second["fallback"] is True
    assert [f.body for f in surviving] == ["second issue"]
    assert [r["score"] for r in scored] == [0, CODE_THRESHOLD]


def test_unparseable_reply_is_abstain(tmp_path):
    prose = "I could not read the file, so I have no verdict."
    canned = _canned(a=[A_FINDING], **_seats(prose, s3=_reply(_vote(1, "not-real"))))
    phase, (surviving, _g, _s, _d), _llm, _gh = _phase_run(tmp_path, canned)
    assert len(surviving) == 1                          # abstentions plus one not-real never drop
    rec = phase.last_votes[0]
    assert (rec["valid_real"], rec["valid_not_real"], rec["abstain"]) == (0, 1, 2)
    assert rec["votes"][0]["error"].startswith("unparseable")
    assert rec["score"] == CODE_THRESHOLD and rec["fallback"] is True


def test_envelope_wrapped_verdicts_counted(tmp_path):
    wrapped = json.dumps({"verdicts": [_vote(1, "not-real", "mitigated upstream")]})
    canned = _canned(a=[A_FINDING], **_seats(wrapped))
    phase, (surviving, _g, scored, _d), _llm, _gh = _phase_run(tmp_path, canned)
    rec = phase.last_votes[0]
    assert rec["valid_not_real"] == 3 and rec["abstain"] == 0     # the envelope was unwrapped
    assert surviving == []
    assert scored[0]["score"] == 0


def test_unknown_envelope_abstains_and_no_false_safety_summary(tmp_path):
    # An unrecognized envelope is an abstention, not a "nothing found" reply. Both agents have a
    # finding here so a "No issues found" summary could only come from a wrongly dropped finding.
    canned = _canned(a=[A_FINDING], security=[("ibl5/s.php", 3, "idor on trade id")],
                     **_seats('{"a": [], "b": []}',
                              s3=_reply(_vote(1, "not-real"), _vote(2, "not-real"))))
    phase, (surviving, _g, scored, _d), _llm, gh = _phase_run(tmp_path, canned)
    code_rec, sec_rec = phase.last_votes
    assert code_rec["kept"] and code_rec["score"] == 80 and code_rec["fallback"] is True
    assert sec_rec["kept"] and sec_rec["score"] == 75 and sec_rec["fallback"] is True
    assert {v["error"] for v in code_rec["votes"][:2]} == {"schema"}
    assert len(surviving) == 2
    texts = [text for (_pr, _title, text) in gh.summary_posts]
    assert not [t for t in texts if t.startswith(("No issues found", "No security issues found"))]
    assert {title for (_pr, _sha, title, _fs) in gh.findings_posts} == {"Code review", "Security audit"}


def test_out_of_range_n_is_ignored(tmp_path):
    canned = _canned(a=[A_FINDING], **_seats(_reply(_vote(1, "real"), _vote(7, "not-real"),
                                                     _vote(2, "not-real"))))
    phase, (surviving, _g, scored, _d), _llm, _gh = _phase_run(tmp_path, canned)
    rec = phase.last_votes[0]
    assert (rec["valid_real"], rec["valid_not_real"], rec["abstain"]) == (3, 0, 0)
    assert scored[0]["score"] == 100
    assert len(surviving) == 1 and len(phase.last_votes) == 1


def test_verifiers_use_sonnet_tooled_readonly_tools_in_worktree(tmp_path):
    _result, llm, _gh = _run(tmp_path)
    assert sorted(c["purpose"] for c in llm.tooled_calls) == sorted(VERIFIER_PURPOSES)
    assert len(llm.tooled_calls) == VERIFIER_COUNT
    assert VERIFIER_MODEL == "sonnet"
    for call in llm.tooled_calls:
        assert call["model"] == "sonnet"
        assert call["cwd"] == str(tmp_path)
        assert call["allowed_tools"] == ("Read","Grep","Glob") == VERIFIER_ALLOWED_TOOLS  # matrix row 38 literal
        assert not {"Bash", "Write", "Edit"} & set(call["allowed_tools"])
        assert call["denied_tools"] == VERIFIER_DENIED_TOOLS
        assert {"Bash", "Write", "Edit", "Agent"} <= set(call["denied_tools"])
        assert call["max_turns"] == VERIFIER_MAX_TURNS
        assert call["timeout"] == VERIFIER_TIMEOUT
        assert call["max_budget_usd"] == VERIFIER_MAX_BUDGET_USD


def test_prompt_contains_only_handed_findings_and_rejection_rules():
    first = _f(path="ibl5/one.php", line=10, body="first body xyzzy")
    second = _f(path="ibl5/two.php", line=20, body="second body plugh")
    hunks = {"ibl5/two.php": "diff --git a/ibl5/two.php b/ibl5/two.php\n+two hunk",
             "ibl5/one.php": "diff --git a/ibl5/one.php b/ibl5/one.php\n+one hunk"}
    prompt = verifier_prompt([second], hunks)
    assert "1. [code-review/A] ibl5/two.php:20 — second body plugh" in prompt
    assert "xyzzy" not in prompt and "ibl5/one.php" not in prompt        # only the handed finding
    assert "+two hunk" in prompt
    assert "REJECTION RULES" in prompt
    assert "BaseMysqliRepository" in prompt and "Assume PHPStan level max" in prompt
    assert prompt.count("OUTPUT CONTRACT") == 2                           # restated at the end
    assert "not-real" in prompt and "you are verifying, not reviewing" in prompt
    both = verifier_prompt([first, second], hunks)
    assert "2. [code-review/A] ibl5/two.php:20" in both
    # A path with no hunk in the PR diff is called out instead of silently omitted.
    assert "(no hunk for this path in the PR diff)" in verifier_prompt([first], {})


def test_chunking_respects_packet_budget(tmp_path):
    n = 12
    diff = "".join(f"diff --git a/p{i}.php b/p{i}.php\n+{'x' * 14_900}\n" for i in range(n))
    cls = _cls(filtered_diff=diff, files=[f"p{i}.php" for i in range(n)])
    agent_findings = [(f"p{i}.php", 1, f"issue {i}") for i in range(n)]
    findings = [_f(path=p, line=ln, body=b) for p, ln, b in agent_findings]
    hunks = _diff_by_path(diff)
    assert all(len(h.encode()) >= 14_900 for h in hunks.values())

    chunks = chunk_findings(findings, hunks)
    assert len(chunks) >= 2
    for chunk in chunks:
        assert len(verifier_prompt([findings[j] for j in chunk], hunks).encode()) <= VERIFIER_PACKET_BUDGET
    assert [i for chunk in chunks for i in chunk] == list(range(n))

    canned = _canned(a=agent_findings, **_seats(_all_real(n)))
    phase, (surviving, _g, scored, _d), llm, _gh = _phase_run(tmp_path, canned, cls=cls)
    assert len(llm.tooled_calls) == VERIFIER_COUNT * len(chunks)
    assert len(surviving) == n
    assert [r["score"] for r in scored] == [100] * n
    assert all(rec["valid_real"] == 3 for rec in phase.last_votes)


def test_no_worktree_keeps_all_with_fallback(tmp_path):
    lines = []
    phase, (surviving, _g, scored, _d), llm, gh = _phase_run(tmp_path, lines=lines, worktree=None)
    assert llm.tooled_calls == []                         # nothing to read, so nothing is asked
    assert len(surviving) == 1
    assert scored[0]["score"] == CODE_THRESHOLD
    rec = phase.last_votes[0]
    assert rec["fallback"] is True and rec["abstain"] == 3
    assert {v["error"] for v in rec["votes"]} == {"no-worktree"}
    assert any("no worktree" in ln for ln in lines)
    assert gh.findings_posts


# ---------------------------------------------------------------------------
# Phase 4: security handling and the vote record
# ---------------------------------------------------------------------------


def test_security_rejected_scores_zero_and_kept_scores_75_base(tmp_path):
    # Finding 1 is rejected by all three seats; finding 2 is never mentioned (three abstentions).
    canned = _canned(security=[("ibl5/s.php", 3, "rejected claim"), ("ibl5/t.php", 4, "unjudged claim")],
                     **_seats(_reply(_vote(1, "not-real"))))
    phase, (surviving, _g, scored, _d), _llm, gh = _phase_run(tmp_path, canned)
    assert [r["score"] for r in scored] == [0, SEC_THRESHOLD]
    assert [f.body for f in surviving] == ["unjudged claim"]
    assert phase.last_votes[1]["fallback"] is True
    posted = [f.body for (_pr, _sha, title, fs) in gh.findings_posts if title == "Security audit" for f in fs]
    assert posted == ["unjudged claim"]


def test_votes_file_written_with_one_record_per_finding(tmp_path):
    out = tmp_path / "out"
    canned = _canned(a=[A_FINDING, ("ibl5/b.php", 2, "second issue")], **_seats(_all_real(2)))
    _phase, _result, _llm, _gh = _phase_run(tmp_path, canned, out_dir=str(out))
    doc = json.loads((out / "verifier-votes.json").read_text())
    assert doc["schema_version"] == 1 and doc["verifier_count"] == VERIFIER_COUNT
    assert len(doc["records"]) == 2
    for rec in doc["records"]:
        assert set(rec) == {"n", "source", "agent", "path", "line", "body_head", "votes",
                            "valid_real", "valid_not_real", "abstain", "kept", "score", "fallback",
                            "preexisting"}
        assert len(rec["votes"]) == 3
    assert [r["n"] for r in doc["records"]] == [1, 2]


def test_rejected_security_finding_is_logged_with_reasons(tmp_path):
    lines = []
    canned = _canned(security=[("ibl5/s.php", 3, "claimed sqli")],
                     **{f"verify-findings-{k}": _reply(_vote(1, "not-real", f"r{k}")) for k in (1, 2, 3)})
    _phase, (surviving, *_rest), _llm, gh = _phase_run(tmp_path, canned, lines=lines)
    dropped = [ln for ln in lines if ln.startswith("phase4d verifier: DROPPED security finding ")]
    assert len(dropped) == 1
    assert all(r in dropped[0] for r in ("r1", "r2", "r3")) and "ibl5/s.php:3" in dropped[0]
    assert surviving == []
    assert "Security audit" not in [title for (_pr, _sha, title, _fs) in gh.findings_posts]


def test_scored_rows_unchanged_by_vote_record(tmp_path):
    (_findings, _gates, scored, _degraded), _llm, _gh = _run(tmp_path, out_dir=str(tmp_path / "out"))
    assert scored
    for row in scored:
        assert set(row) == {"source", "agent", "path", "line", "score", "label", "body_head"}
    assert "votes" not in json.dumps(scored, sort_keys=True)


def test_no_out_dir_skips_file_but_still_logs(tmp_path):
    lines = []
    _phase, _result, llm, _gh = _phase_run(tmp_path, lines=lines, out_dir=None)
    assert llm.out_dir is None
    assert not list(tmp_path.rglob("verifier-votes.json"))
    kept = [ln for ln in lines if ln.startswith("phase4d verifier: kept [code-review/A] ")]
    assert len(kept) == 1 and "score=100" in kept[0]
