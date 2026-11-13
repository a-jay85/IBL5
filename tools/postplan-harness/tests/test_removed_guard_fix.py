"""The condition (20) reason fixer: writer + checker agreement, fail-closed paths, caps, env flag."""
from __future__ import annotations

import pytest

from harness import removed_guard_fix as rgf
from harness import removed_guards as rg

PATH = "ibl5/classes/Season/Season.php"
KEY = "try@ibl5/classes/Season/Season.php#1a2b3c4d"
ROW = (KEY, PATH, "try", "try {")
DIFF = (
    f"diff --git a/{PATH} b/{PATH}\n"
    f"--- a/{PATH}\n+++ b/{PATH}\n"
    "@@ -1,4 +1,2 @@\n"
    "-        try {\n"
    "-        } catch (\\RuntimeException) {\n"
    "+        $this->factory->build();\n"
    " }\n"
)
REASON = "moved into SeasonFactory::build, which keeps the same catch"


class StubLlm:
    """Records `(purpose, model)` per call, runs `validate` on the scripted reply, and lets every
    raise propagate, so the validators stay on the executed path. A reply may be an exception
    instance (raised) or a callable `(prompt) -> reply`."""

    def __init__(self, replies):
        self.replies = replies
        self.calls: list[tuple[str, str]] = []

    def call(self, purpose, model, prompt, validate, **kw):
        self.calls.append((purpose, model))
        reply = self.replies[purpose]
        if isinstance(reply, BaseException):
            raise reply
        if callable(reply):
            reply = reply(prompt)
        validate(reply)
        return reply


def _body(rows, reasons=None):
    return rg.upsert_block("## Summary\n- change\n", rg.render_block(rows, reasons or {}))


def _llm(verdict="safe", check_key=KEY, reason=REASON, write_key=KEY):
    return StubLlm({
        rgf.PURPOSE_WRITE: {"key": write_key, "reason": reason},
        rgf.PURPOSE_CHECK: {"key": check_key, "verdict": verdict, "why": "the hunk shows the move"},
    })


def _run(llm, body=None, rows=(ROW,), diff=DIFF, clock=None):
    logs: list[str] = []
    kw = {"clock": clock} if clock else {}
    res = rgf.run(body=body if body is not None else _body(list(rows)), rows=list(rows),
                  diff=diff, llm=llm, log=logs.append, **kw)
    return res, logs


def _bullet_reason(body, key):
    for line in body.split("\n"):
        m = rg._BULLET_RE.match(line)
        if m and m.group(1) == key:
            return m.group(2)
    raise AssertionError(f"no bullet for {key}")


def test_approve_writes_marked_reason():
    res, _ = _run(_llm())
    assert res["status"] == "changed"
    assert res["filled"] == [KEY] and res["kept"] == []
    reason = _bullet_reason(res["body"], KEY)
    assert reason.startswith(rg.HARNESS_MARKER)
    assert reason == rg.HARNESS_MARKER + REASON
    assert "  removed: `try {`" in res["body"]


def test_approve_clears_classify_holds():
    body = _body([ROW])
    assert rg.classify_body(body, [KEY]) == [f"removed-guards:UNFILLED:{KEY}"]
    res, _ = _run(_llm(), body=body)
    holds = rg.classify_body(res["body"], [KEY])
    assert not [h for h in holds if "UNFILLED" in h or "UNJUSTIFIED" in h]
    assert holds == []


def test_short_reason_is_fillable():
    body = _body([ROW], {KEY: "Moved to b.php"})
    assert rg.classify_body(body, [KEY]) == [f"removed-guards:UNJUSTIFIED:{KEY}"]
    res, _ = _run(_llm(), body=body)
    assert res["filled"] == [KEY]
    assert rg.classify_body(res["body"], [KEY]) == []


@pytest.mark.parametrize("verdict", ["unsure", "unsafe"])
def test_checker_doubt_keeps_placeholder(verdict):
    body = _body([ROW])
    res, logs = _run(_llm(verdict=verdict), body=body)
    assert res["status"] == "no-change"
    assert res["body"] == body and res["filled"] == []
    assert res["kept"] == [(KEY, f"checker-{verdict}")]
    assert f"removed-guard-fix: kept {KEY} (checker-{verdict})" in logs


def test_checker_key_mismatch_keeps_placeholder():
    body = _body([ROW])
    res, _ = _run(_llm(check_key="catch@ibl5/classes/Season/Season.php#5e6f7a8b"), body=body)
    assert res["status"] == "no-change"
    assert res["body"] == body and res["filled"] == []
    assert res["kept"] == [(KEY, "checker-safe")]


@pytest.mark.parametrize("which,exc", [
    (rgf.PURPOSE_WRITE, RuntimeError("writer boom")),
    (rgf.PURPOSE_CHECK, RuntimeError("checker boom")),
    (rgf.PURPOSE_CHECK, TimeoutError("checker timed out")),
], ids=["writer_raise", "checker_raise", "timeout"])
def test_llm_error_keeps_placeholder(which, exc):
    llm = _llm()
    llm.replies[which] = exc
    body = _body([ROW])
    res, _ = _run(llm, body=body)
    assert res["status"] == "no-change"
    assert res["body"] == body and res["filled"] == []
    assert len(res["kept"]) == 1
    key, why = res["kept"][0]
    assert key == KEY and why.startswith("llm-error: ")


def test_validate_check_rejects_envelope():
    with pytest.raises(ValueError):
        rgf.validate_check({"reasons": [{"key": KEY, "verdict": "safe", "why": "x"}]})
    with pytest.raises(ValueError):
        rgf.validate_check({"result": {"key": KEY, "verdict": "safe", "why": "x"}})
    with pytest.raises(ValueError):
        rgf.validate_check({"key": KEY, "verdict": "approve", "why": "x"})
    with pytest.raises(ValueError):
        rgf.validate_check({"key": KEY, "verdict": "safe", "why": "x", "extra": 1})
    assert rgf.validate_check({"key": KEY, "verdict": "safe", "why": "x"})["verdict"] == "safe"


def test_validate_write_rejects_envelope():
    with pytest.raises(ValueError):
        rgf.validate_write({"reasons": [{"key": KEY, "reason": REASON}]})
    with pytest.raises(ValueError):
        rgf.validate_write({"key": KEY, "reason": None})
    assert rgf.validate_write({"key": KEY, "reason": REASON})["reason"] == REASON


def test_envelope_checker_output_never_approves():
    llm = _llm()
    llm.replies[rgf.PURPOSE_CHECK] = {"reasons": [{"key": KEY, "verdict": "safe", "why": "x"}]}
    body = _body([ROW])
    res, _ = _run(llm, body=body)
    assert res["status"] == "no-change"
    assert res["filled"] == []
    assert res["body"] == body
    assert _bullet_reason(res["body"], KEY) == rg.PLACEHOLDER


@pytest.mark.parametrize("reason", ["n/a", "", "<one-line reason required>"])
def test_writer_filler_keeps_placeholder(reason):
    llm = _llm(reason=reason)
    body = _body([ROW])
    res, _ = _run(llm, body=body)
    assert res["status"] == "no-change" and res["body"] == body
    assert res["kept"] == [(KEY, "writer-declined")]
    assert [p for p, _ in llm.calls] == [rgf.PURPOSE_WRITE]


def test_writer_key_mismatch_never_calls_checker():
    llm = _llm(write_key="catch@ibl5/classes/Season/Season.php#5e6f7a8b")
    res, _ = _run(llm)
    assert res["kept"] == [(KEY, "writer-declined")]
    assert [p for p, _ in llm.calls] == [rgf.PURPOSE_WRITE]


def _many_rows(n):
    return [(f"try@{PATH}#{i:08x}", PATH, "try", "try {") for i in range(n)]


def _echo_llm():
    """Writer and checker echo the key named in the prompt, so every bullet can be approved."""
    import re

    def key_of(prompt):
        return re.search(r"GUARD KEY: (\S+)", prompt).group(1)
    return StubLlm({
        rgf.PURPOSE_WRITE: lambda p: {"key": key_of(p), "reason": REASON},
        rgf.PURPOSE_CHECK: lambda p: {"key": key_of(p), "verdict": "safe", "why": "moved"},
    })


def test_max_items_cap():
    rows = _many_rows(7)
    llm = _echo_llm()
    res, _ = _run(llm, rows=rows)
    writes = [c for c in llm.calls if c[0] == rgf.PURPOSE_WRITE]
    assert len(writes) == 6 == rgf.MAX_ITEMS
    over = [k for k, why in res["kept"] if why == "over-cap"]
    assert over == [rows[6][0]]
    assert res["filled"] == [r[0] for r in rows[:6]]


def test_deadline_keeps_remaining():
    rows = _many_rows(3)
    ticks = iter([0, 0] + [rgf.DEADLINE_S + 1] * 10)
    llm = _echo_llm()
    res, _ = _run(llm, rows=rows, clock=lambda: next(ticks))
    assert res["filled"] == [rows[0][0]]
    assert res["kept"] == [(rows[1][0], "deadline"), (rows[2][0], "deadline")]
    assert len([c for c in llm.calls if c[0] == rgf.PURPOSE_WRITE]) == 1


def test_no_targets_makes_no_llm_call():
    body = _body([ROW], {KEY: REASON})
    llm = _llm()
    res, _ = _run(llm, body=body)
    assert res == {"status": "no-change", "body": body, "filled": [], "kept": []}
    assert llm.calls == []


def test_missing_diff_section_keeps_placeholder():
    llm = _llm()
    res, _ = _run(llm, diff="diff --git a/other.php b/other.php\n")
    assert res["kept"] == [(KEY, "no-diff")] and llm.calls == []


def test_diff_too_large_keeps_placeholder():
    llm = _llm()
    big = DIFF + "-        // pad\n" * (rgf.DIFF_CHARS // 10)
    res, _ = _run(llm, diff=big)
    assert res["kept"] == [(KEY, "diff-too-large")] and llm.calls == []


def test_enabled_unset():
    assert rgf.enabled({}) is True


def test_enabled_empty():
    assert rgf.enabled({rgf.ENV_FLAG: ""}) is True


def test_enabled_zero_disables():
    assert rgf.enabled({rgf.ENV_FLAG: "0"}) is False
    assert rgf.enabled({rgf.ENV_FLAG: "1"}) is True


def test_models_writer_sonnet_checker_opus():
    llm = _llm()
    _run(llm)
    assert llm.calls == [(rgf.PURPOSE_WRITE, "sonnet"), (rgf.PURPOSE_CHECK, "opus")]
    assert rgf.WRITER_MODEL == "sonnet" and rgf.CHECKER_MODEL == "opus"
