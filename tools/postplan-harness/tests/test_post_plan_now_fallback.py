import os, pathlib, re, shlex, shutil, subprocess

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
PPN = os.path.join(REPO, "bin", "post-plan-now")

_LAUNCHCTL_LIVE = (
    '#!/bin/sh\n'
    'printf "%s\\t%s\\t%s\\n" 12345 0 com.ibl5.postplan-now-wt-feature-20260916-144924-99\n'
)
_LAUNCHCTL_OTHER_SLUG = (
    '#!/bin/sh\n'
    'printf "%s\\t%s\\t%s\\n" 12345 0 com.ibl5.postplan-now-wt-feature-extra-20260916-144924-99\n'
)

def _fb(code):
    r = subprocess.run(
        ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; should_fallback {code}; echo $?'],
        capture_output=True, text=True)
    return r.stdout.strip().splitlines()[-1]

def test_success_and_sentinel_do_not_fall_back():
    assert _fb(0) == "1"   # success → should_fallback returns 1 (false) → no skill fallback
    assert _fb(3) == "1"   # rebase-conflict sentinel → NO fallback (the fix)

def test_generic_failures_fall_back():            # negative path: real failures still degrade
    assert _fb(1) == "0"
    assert _fb(2) == "0"
    assert _fb(130) == "0"

def _bash(script):
    return subprocess.run(["bash", "-c", script], capture_output=True, text=True)


def test_shq_survives_a_second_shell_parse():
    """shq() output must reproduce its input byte-for-byte when a SECOND shell parses it.

    $CMD is re-parsed by `/bin/bash -lc` inside the launchd plist, so anything the
    prompt carries gets a second round of shell evaluation. Grepping the source for
    backticks would not catch this — only a real round-trip does.
    """
    hostile = [
        "plain",
        "has `backticks` here",
        "has $(cmd) and $VAR",
        "it's got a single quote",
        r"a\b backslash",
        "<slug>-N.md",
        "mix: `x` $y 'z' \\w",
    ]
    for s in hostile:
        script = f'source "{PPN}" >/dev/null 2>&1; q=$(shq "$1"); bash -c "printf %s $q"'
        r = subprocess.run(["bash", "-c", script, "_", s], capture_output=True, text=True)
        assert r.stderr == "", f"{s!r}: second parse wrote to stderr: {r.stderr!r}"
        assert r.stdout == s, f"{s!r}: round-tripped to {r.stdout!r}"


def test_plan_blind_prompt_reaches_the_model_intact():
    """The real plan-blind prompt literal, round-tripped through the far-side shell.

    Regression: it carried literal backticks around <slug>-N.md, was embedded as
    `claude -p \\"$PROMPT\\"`, and `/bin/bash -lc` ran the backticks as command
    substitution — logging `slug: No such file or directory` and silently deleting the
    plan-disambiguation clause from the prompt the model received.
    """
    import re
    src = open(PPN).read()
    prompts = re.findall(r'^\s*(PROMPT="(?:[^"\\]|\\.)*")$', src, re.M)
    assert len(prompts) == 2, f"expected the --plan and plan-blind prompts, got {len(prompts)}"
    plan_blind = prompts[1]
    assert "<slug>-N.md" in plan_blind, "plan-blind prompt no longer names the variant rule"

    script = (f'source "{PPN}" >/dev/null 2>&1\n'
              f'{plan_blind}\n'
              'q=$(shq "$PROMPT")\n'
              'bash -c "printf %s $q"\n')
    r = _bash(script)
    assert r.stderr == "", f"far-side shell wrote to stderr: {r.stderr!r}"
    assert "`<slug>-N.md`" in r.stdout, f"backticked span did not survive: {r.stdout!r}"
    assert "never ask questions" in r.stdout


def test_embed_sites_are_shell_quoted():
    src = open(PPN).read()
    assert 'claude -p $(shq "$PROMPT")' in src      # not \"$PROMPT\" — see shq() header
    assert r'claude -p \"$PROMPT\"' not in src
    assert '--plan $(shq "$PLAN_OVERRIDE")' in src


def test_plist_wiring_regression():
    src = open(PPN).read()
    assert "--live || " not in src               # old unconditional fallback chain removed
    assert "should_fallback" in src              # fallback now gated by the tested function
    assert r"rc=\$?" in src                       # harness exit code captured for the gate
    assert r'\"\$rc\" = 3' in src                 # fail-closed notice is rc=3-only (elif)
    # New wiring assertions
    assert "PLAN_ARG" in src
    assert "--live${PLAN_ARG}" in src
    assert "${PLAN_OVERRIDE:-$HOME/claude-plans/$SLUG.md}" in src
    assert "do NOT derive the plan from the branch slug" in src


def test_plan_override_missing_file_aborts(tmp_path):
    r = subprocess.run(
        ["bash", "bin/post-plan-now", "--plan", "/nonexistent-plan.md"],
        capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "does not exist — aborting" in r.stderr


def test_plan_arg_errors():
    r1 = subprocess.run(
        ["bash", "bin/post-plan-now", "--plan"],
        capture_output=True, text=True, cwd=REPO)
    assert r1.returncode == 1
    assert "--plan requires a path argument" in r1.stderr

    r2 = subprocess.run(
        ["bash", "bin/post-plan-now", "--pln", "/tmp/x.md"],
        capture_output=True, text=True, cwd=REPO)
    assert r2.returncode == 1
    assert "unknown argument" in r2.stderr


def test_arg_order_independent(tmp_path):
    for args in (
        ["--auto", "--plan", "/nonexistent.md"],
        ["--plan", "/nonexistent.md", "--auto"],
        ["--plan=/nonexistent.md"],
    ):
        r = subprocess.run(["bash", "bin/post-plan-now"] + args,
                           capture_output=True, text=True, cwd=REPO)
        assert r.returncode == 1
        assert "does not exist — aborting" in r.stderr, \
            f"args {args}: got {r.stderr!r}"


def test_plan_override_valid_path_passes_validation(tmp_path):
    plan = tmp_path / "myplan.md"
    plan.write_text("---\nauto_merge: false\n---\n# My Plan\n")
    r = subprocess.run(
        ["bash", PPN, "--plan", str(plan)],
        capture_output=True, text=True, cwd=str(tmp_path))
    assert r.returncode == 1
    assert "does not exist — aborting" not in r.stderr
    assert "not inside a git repo" in r.stderr



def test_skill_block_matches_resolver(tmp_path):
    """SKILL.md Phase 1 shell block and locate_plan agree on same corpus."""
    import sys as _sys
    _sys.path.insert(0, os.path.join(REPO, "tools", "postplan-harness"))
    from harness.planfile import locate_plan

    plans = tmp_path
    for name in ("s.md", "s-shared-context.md", "s-2.md"):
        (plans / name).write_text("---\nauto_merge: false\n---\n# " + name + "\n")

    skill_md = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    skill_src = open(skill_md).read()
    # Extract the resolution shell block between the two bash fences in Phase 1
    import re
    m = re.search(r"```bash\n(# Authoritative.*?PLAN_VARIANT_SELECTED.*?\n)```", skill_src, re.S)
    assert m, "SKILL.md Phase 1 shell block not found"
    shell_block = m.group(1)

    # Use plan-resolve.sh test seams: PLAN_DIR and PLAN_SLUG override slug derivation.
    env = os.environ.copy()
    env["PLAN_DIR"] = str(plans)
    env["PLAN_SLUG"] = "s"
    script = (
        f"cd {REPO!r}\n"
        f'mkdir -p "{tmp_path!s}"\n'
        + shell_block
        + '\necho "PLAN_FILE=$PLAN_FILE"\n'
        + '\necho "BEST=$BEST"\n'
    )
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)
    assert r.returncode == 0, r.stderr
    # Skill should pick s-2.md (highest)
    assert "s-2.md" in r.stdout, f"skill output: {r.stdout!r}"

    # Resolver should also pick s-2.md
    info = locate_plan("s", plans_dir=str(tmp_path))
    assert info.path.endswith("s-2.md"), f"resolver picked: {info.path}"


def test_skill_block_plan_blind(tmp_path):
    """SKILL.md shell block on empty plans dir prints nothing, leaves PLAN_FILE empty."""
    skill_md = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    skill_src = open(skill_md).read()
    import re
    m = re.search(r"```bash\n(# Authoritative.*?PLAN_VARIANT_SELECTED.*?\n)```", skill_src, re.S)
    assert m, "SKILL.md Phase 1 shell block not found"
    shell_block = m.group(1)

    plans_dir = str(tmp_path / "empty-plans")
    os.makedirs(plans_dir, exist_ok=True)
    # Use plan-resolve.sh test seams: PLAN_DIR and PLAN_SLUG override slug derivation.
    env = os.environ.copy()
    env["PLAN_DIR"] = plans_dir
    env["PLAN_SLUG"] = "no-such-slug"
    script = (
        f"cd {REPO!r}\n"
        + shell_block
        + '\necho "PLAN_FILE=${PLAN_FILE:-}"\n'
        + 'if [ -z "${PLAN_FILE:-}" ]; then echo "PLAN_FOUND=none"; fi\n'
    )
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)
    assert r.returncode == 0, r.stderr
    assert "PLAN_VARIANT_SELECTED" not in r.stdout
    assert "PLAN_FOUND=none" in r.stdout


def _fixture_repo(tmp_path, dirty_default=True, dirty=None):
    """A dirty linked worktree on a non-master branch — enough to clear post-plan-now's guards.

    Creates a main checkout at tmp_path/wt (on some-feature) with bin/lib/git-helpers.sh
    committed, then adds a linked worktree at tmp_path/wt-base (on wt-feature).
    Returns tmp_path/wt-base so is_in_worktree() passes in callers.
    CI has no global git identity, so the fixture sets its own.

    `dirty_default=False` leaves `f.txt` clean so a caller can choose exactly which paths
    are dirty; `f.txt` is a production path as far as the exit-5 guard is concerned, so the
    default dirtying would mask a test-only working set.

    `dirty` maps repo-relative paths to their post-branch content. Those paths are COMMITTED
    into the base commit first and only then rewritten, so they show up as tracked
    modifications: the "nothing to ship" guard above tests `git diff --quiet`, which an
    untracked file does not trip, and zero commits ahead of origin/master is exactly the
    shape the exit-5 guard must handle.
    """
    repo = tmp_path / "wt"
    repo.mkdir()
    run = lambda *a: subprocess.run(a, cwd=str(repo), check=True, capture_output=True)
    run("git", "init", "-q", "-b", "master")
    run("git", "config", "user.email", "test@example.com")
    run("git", "config", "user.name", "Test")
    (repo / "bin" / "lib").mkdir(parents=True)
    shutil.copy(os.path.join(REPO, "bin", "lib", "git-helpers.sh"),
                str(repo / "bin" / "lib" / "git-helpers.sh"))
    shutil.copy(os.path.join(REPO, "bin", "lib", "session-id.sh"),
                str(repo / "bin" / "lib" / "session-id.sh"))
    (repo / "f.txt").write_text("one\n")
    for rel in (dirty or {}):
        p = repo / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text("base\n")
    run("git", "add", "-A")
    run("git", "commit", "-qm", "base")
    run("git", "checkout", "-qb", "some-feature")
    linked = tmp_path / "wt-base"
    run("git", "worktree", "add", str(linked), "-b", "wt-feature")
    # Without an origin/master ref the `origin/master...HEAD` leg of the guard's changed-set
    # union errors out, and every guard test below would pass for the wrong reason.
    run("git", "update-ref", "refs/remotes/origin/master", "master")
    if dirty_default:
        (linked / "f.txt").write_text("two\n")    # dirty tree ⇒ "nothing to ship" guard passes
    for rel, text in (dirty or {}).items():
        (linked / rel).write_text(text)         # tracked-and-modified, so `git diff` sees it
    return linked


def _fixture_worktree(tmp_path, branch):
    """Return (main_root, wt_root) where wt_root is a linked worktree on `branch`.

    Uses _fixture_repo to build the main checkout, then adds another linked worktree.
    """
    feature_wt = _fixture_repo(tmp_path)
    # Derive main_root from the linked worktree's git-common-dir
    main_root = tmp_path / "wt"          # _fixture_repo always puts main here
    wt_root = tmp_path / f"wt-{branch}"
    subprocess.run(
        ["git", "worktree", "add", str(wt_root), "-b", branch],
        cwd=str(main_root), check=True, capture_output=True)
    (wt_root / "f.txt").write_text("wt-dirty\n")
    return main_root, wt_root


def _gh_stub(bin_dir, branch, rc=0):
    """Write a gh stub that answers headRefName queries with `branch`."""
    stub = pathlib.Path(bin_dir) / "gh"
    stub.write_text(
        "#!/bin/sh\n"
        f"case \"$*\" in\n"
        f"  *headRefName*) printf '%s\\n' {shlex.quote(branch)} ; exit 0 ;;\n"
        f"  *) exit {rc} ;;\n"
        f"esac\n"
    )
    stub.chmod(0o755)


def _guard_block():
    """Extract the Phase 0 guard block verbatim from SKILL.md."""
    skill_path = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    src = open(skill_path).read()
    START = "# --- post-plan worktree guard (Phase 0) ---"
    END   = "# --- end post-plan worktree guard ---"
    parts = src.split(START)
    assert len(parts) == 2, f"expected exactly one guard block start, got {len(parts) - 1}"
    inner = parts[1].split(END)
    assert len(inner) == 2, f"expected exactly one guard block end, got {len(inner) - 1}"
    return (START + inner[0] + END).strip()

def _run_ppn(tmp_path, args=(), extra_env=None, dirty=None, launchctl_stub=None):
    """Run bin/post-plan-now with launchctl + gh stubbed and HOME redirected.

    Returns the raw CompletedProcess — the guard tests need the return code, which
    _generate_cmd asserts away.

    The `gh` stub prints $FAKE_PR_NUMBER when it is non-empty and nothing otherwise, so a
    test selects the PR-exists / no-PR case purely through the environment.
    """
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text(launchctl_stub or "#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    (shim / "gh").write_text(
        '#!/bin/sh\nif [ -n "${FAKE_PR_NUMBER:-}" ]; then echo "$FAKE_PR_NUMBER"; fi\nexit 0\n')
    (shim / "gh").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    env.pop("FAKE_PR_NUMBER", None)
    env.update(extra_env or {})
    repo = _fixture_repo(tmp_path, dirty_default=dirty is None, dirty=dirty)
    return subprocess.run(["bash", PPN, *args], cwd=repo, env=env,
                          capture_output=True, text=True)

def _generate_cmd(tmp_path, extra_env=None):
    """Run bin/post-plan-now with launchctl stubbed and HOME redirected; return $CMD."""
    home = tmp_path / "home"
    r = _run_ppn(tmp_path, extra_env=extra_env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"

    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one generated plist, got {plists}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    return cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")

def test_generated_cmd_has_no_rc4_arm(tmp_path):
    cmd = _generate_cmd(tmp_path)
    assert '[ "$rc" = 4 ]' not in cmd, "rc=4 arm must be gone after the full-port"
    assert cmd.index('if should_fallback "$rc"; then') < cmd.index('elif [ "$rc" = 3 ]; then')

def test_generated_cmd_carries_one_claude_invocation(tmp_path):
    cmd = _generate_cmd(tmp_path)
    assert cmd.count("caffeinate -s claude -p ") == 1
    assert "RESUMING at Phase 5.5" not in cmd
    assert "then execute every phase" in cmd


def _rc3_gate(tmp_path, log_text=""):
    """The generated gate chain with the claude call stubbed and $LOG repointed
    at a fixture this test controls. Returns (gate_source, fixture_path)."""
    cmd = _generate_cmd(tmp_path)
    gate = cmd.split("rc=$?; ", 1)[1].split("; }; pp_rc=", 1)[0]
    gate = re.sub(r'CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS=0.*?--name "[^"]*"',
                  'echo "RAN-SKILL"', gate)
    real_log = re.search(r"grep -m1 '\^RESULT:' \"([^\"]+)\"", gate).group(1)
    fixture = tmp_path / "harness-run.log"
    fixture.write_text(log_text)
    gate = gate.replace(real_log, str(fixture))   # rewrites the grep AND the "See ..." suffix
    return gate, fixture

def _run_gate(gate, rc=3):
    return subprocess.run(
        ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; rc={rc}; {gate}'],
        capture_output=True, text=True)


def test_gate_selects_the_right_arm_per_rc(tmp_path):
    """Branch selection, exercised on the GENERATED chain with the claude call stubbed.

    The single `caffeinate -s claude … --name "…"` span is replaced (with an echo
    marker) and the harness segment with a literal rc; every condition under test —
    `should_fallback "$rc"`, `[ "$rc" = 3 ]` — is the generated text.
    """
    cmd = _generate_cmd(tmp_path)
    gate = cmd.split("rc=$?; ", 1)[1].split("; }; pp_rc=", 1)[0]
    seen = []
    def _stub(_m):
        seen.append(1)
        return f'echo "RAN-{len(seen)}"'
    gate = re.sub(r'CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS=0.*?--name "[^"]*"', _stub, gate)
    assert len(seen) == 1

    for rc, expect in ((4, "RAN-1"), (1, "RAN-1"), (2, "RAN-1"),
                       (0, None), (3, None)):
        r = subprocess.run(
            ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; rc={rc}; {gate}'],
            capture_output=True, text=True)
        assert r.returncode == 0, f"rc={rc}: {r.stderr!r}"
        if expect:
            assert expect in r.stdout, f"rc={rc}: expected {expect}, got {r.stdout!r}"
        else:
            assert "RAN-" not in r.stdout, f"rc={rc} must launch no session: {r.stdout!r}"
        if rc == 3:
            assert "fail-closed sentinel" in r.stdout, "rc=3 lost its notice"


def test_generated_cmd_captures_the_harness_result_line(tmp_path):
    cmd = _generate_cmd(tmp_path)
    assert "HARNESS_RESULT=$(grep -m1 '^RESULT:'" in cmd
    assert "HARNESS_RESULT=${HARNESS_RESULT:0:1400}" in cmd
    assert (cmd.index("rc=$?; ") < cmd.index("HARNESS_RESULT=$(grep")
            < cmd.index('if should_fallback "$rc"; then'))


def test_skill_only_cmd_has_no_harness_result_capture(tmp_path):
    cmd = _generate_cmd(tmp_path, extra_env={"POST_PLAN_SKILL": "1"})
    # HARNESS_RESULT appears in the serialised badge function body in all paths;
    # the negative path is that the *capture command* (POST_HARNESS_SEG) is absent.
    assert "HARNESS_RESULT=$(grep" not in cmd


_ADR_HOOK_RESULT_LINE = (
    'RESULT: post-plan BLOCKED — local pre-commit/pre-push gate denied the commit;'
    ' ERROR terminal=failed, no PR opened.'
    ' local-gate: git push --force-with-lease origin HEAD:'
    ' pre-push-adr-hook: a decision-trigger surface is being pushed without an ADR.'
    ' Resolve with ONE of:'
    ' 1. Add an ADR under ibl5/docs/decisions/ (run: bin/next-adr "kebab-title").'
    ' 2. Add a bypass marker to a commit message on this branch (reason >=15 c…'
    ' Clear the gate then re-run bin/post-plan-now.'
)


def test_exit3_message_names_the_specific_gate_denial(tmp_path):
    gate, fixture = _rc3_gate(tmp_path, _ADR_HOOK_RESULT_LINE + "\n")
    r = _run_gate(gate)
    assert r.returncode == 0, r.stderr
    assert "pre-push-adr-hook" in r.stdout
    assert "bin/next-adr" in r.stdout
    assert "fail-closed sentinel" in r.stdout
    assert f"See {fixture}." in r.stdout
    assert "RAN-" not in r.stdout


def test_exit3_result_line_is_inert_data_not_shell(tmp_path):
    # Use non-overlapping sentinel tokens so count() checks prove no execution.
    # (PWNED / PWNED2 would overlap: "PWNED" appears in both, giving count == 2
    # even when no command ran.)
    log_text = 'RESULT: gate denied: run bin/next-adr "kebab-title" $(echo INJECT1) `echo INJECT2`\n'
    gate, fixture = _rc3_gate(tmp_path, log_text)
    r = _run_gate(gate)
    assert r.returncode == 0, r.stderr
    assert '$(echo INJECT1)' in r.stdout
    assert '`echo INJECT2`' in r.stdout
    assert r.stdout.count("INJECT1") == 1
    assert r.stdout.count("INJECT2") == 1


def test_exit3_message_falls_back_when_no_result_line(tmp_path):
    for i, log_text in enumerate(["", "harness: starting\nharness: done\n"]):
        sub = tmp_path / str(i)
        sub.mkdir()
        gate, fixture = _rc3_gate(sub, log_text)
        r = _run_gate(gate)
        assert r.returncode == 0, f"case {i}: {r.stderr!r}"
        assert "rebase conflict" in r.stdout, f"case {i}: {r.stdout!r}"
        assert "gate denial" in r.stdout, f"case {i}: {r.stdout!r}"
        assert "fail-closed sentinel" in r.stdout, f"case {i}: {r.stdout!r}"
        assert "Cause: ." not in r.stdout, f"case {i}: {r.stdout!r}"


def test_exit3_message_stays_under_the_discord_cap_worst_case(tmp_path):
    gate, fixture = _rc3_gate(tmp_path, "RESULT: " + "x" * 2000 + "\n")
    r = _run_gate(gate)
    assert r.returncode == 0, r.stderr
    msg = r.stdout.rstrip("\n")
    assert "x" * 1300 in msg and "x" * 1450 not in msg      # the 1400 cap fired
    slug = re.search(r"on branch (.*?)\. SKIPPING", gate).group(1)
    fixed = len(msg) - 1400 - len(slug) - len(str(fixture))
    WORST_SLUG, WORST_LOG = 80, 120
    assert fixed + 1400 + WORST_SLUG + WORST_LOG < 1900, (
        "assembled rc=3 msg can exceed bin/discord-dm's 1900-char cap; "
        f"fixed={fixed}")


def test_badge_banner_rc3_carries_the_captured_result():
    r = subprocess.run(["bash", "-c",
        f'source "{PPN}" >/dev/null 2>&1; '
        'HARNESS_RESULT="RESULT: pre-push-adr-hook denied the push"; '
        'postplan_badge_banner_body 3 lbl "some time"'],
        capture_output=True, text=True)
    assert r.returncode == 0, r.stderr
    assert "pre-push-adr-hook denied the push" in r.stdout


def test_badge_banner_rc3_percent_is_safe_and_falls_back():
    # (a) percent characters in RESULT must not cause printf format-string confusion
    r = subprocess.run(["bash", "-c",
        f'source "{PPN}" >/dev/null 2>&1; '
        "HARNESS_RESULT='RESULT: 100% of gates %s %d denied'; "
        'postplan_badge_banner_body 3 lbl "some time"'],
        capture_output=True, text=True)
    assert r.returncode == 0, r.stderr
    assert "100% of gates %s %d denied" in r.stdout
    assert "(null)" not in r.stdout

    # (b) HARNESS_RESULT unset → fallback text names both causes
    r2 = subprocess.run(["bash", "-c",
        f'source "{PPN}" >/dev/null 2>&1; '
        'unset HARNESS_RESULT; '
        'postplan_badge_banner_body 3 lbl "some time"'],
        capture_output=True, text=True)
    assert r2.returncode == 0, r2.stderr
    assert "rebase conflict" in r2.stdout
    assert "gate denial" in r2.stdout


def test_prompt_assignment_count_is_still_two():
    """The two real PROMPT sites must keep their order (index 1 is the plan-blind one)."""
    src = open(PPN).read()
    prompts = re.findall(r'^\s*(PROMPT="(?:[^"\\]|\\.)*")$', src, re.M)
    assert len(prompts) == 2, f"expected exactly two PROMPT= sites, got {len(prompts)}"
    assert "<slug>-N.md" in prompts[1]
    assert not re.search(r'^\s*RESUME_(PROMPT|PLAN_CLAUSE)=', src, re.M)

def test_should_fallback_body_unchanged():
    src = open(PPN).read()
    assert 'should_fallback() { case "$1" in 0|3) return 1 ;; *) return 0 ;; esac; }' in src
    assert _fb(4) == "0"     # 4 escalates to the full skill like any other non-0/3 code

def test_bare_invocation_cmd_has_no_plan_slug_export(tmp_path):
    """Bare invocation (no --pr) must not inject PLAN_SLUG into $CMD."""
    cmd = _generate_cmd(tmp_path)
    assert "PLAN_SLUG" not in cmd, f"bare path must not export PLAN_SLUG; got {cmd!r}"

    # Boundary: PLAN_SLUG explicitly empty in caller env — still must not appear in CMD
    tmp2 = tmp_path / "b2"
    tmp2.mkdir()
    cmd2 = _generate_cmd(tmp2, extra_env={"PLAN_SLUG": ""})
    assert "PLAN_SLUG" not in cmd2, f"PLAN_SLUG='' must not be injected; got {cmd2!r}"

    # Boundary: PLAN_SLUG non-empty in caller env — bare path must still not inject it
    tmp3 = tmp_path / "b3"
    tmp3.mkdir()
    cmd3 = _generate_cmd(tmp3, extra_env={"PLAN_SLUG": "SOME-OTHER-BRANCH"})
    assert "PLAN_SLUG" not in cmd3, f"ambient PLAN_SLUG must not be injected on bare path; got {cmd3!r}"


def test_harness_default_is_the_main_checkout(tmp_path):
    """ADR-0092: the seam must not become $ROOT/tools/postplan-harness."""
    src = open(PPN).read()
    assert 'HARNESS="${HARNESS:-/Users/ajaynicolas/GitHub/IBL5/tools/postplan-harness}"' in src
    assert '$ROOT/tools/postplan-harness' not in src


def test_plan_override_reaches_the_python_harness_in_live_mode():
    """--plan must land in the HARNESS invocation, not only the skill-fallback prompt.

    plan_source (harness/state.py) is recorded by harness/planfile.py::locate_plan, which
    only sees an explicit path via runner.py's --plan. runner.py rejects --plan outside
    --mode isolated, and --live is itself isolated-only, so the INSTALLED live mode is the
    one place both apply. If PLAN_ARG were interpolated into $PROMPT instead, a live
    override would resolve through bin/lib/plan-resolve.sh, which writes no result.json —
    and the audit trail this field exists for would not cover the live run class.
    """
    src = open(PPN).read()
    seg = next(l for l in src.splitlines() if l.startswith("    HARNESS_SEG="))
    assert "--live${PLAN_ARG}" in seg


def _runner_module():
    """Import tools/postplan-harness/runner.py the way test_skill_block_matches_resolver
    imports harness.planfile. runner.py's CLI sits behind `if __name__ == "__main__":`
    (runner.py:580), so importing it executes no argparse and touches no run dir."""
    import sys as _sys
    _sys.path.insert(0, os.path.join(REPO, "tools", "postplan-harness"))
    import runner
    return runner




def test_harness_run_dir_artifacts_are_gitignored():
    """Boundary: the two new files live in the run dir, so they must never be committable.
    Asserted behaviorally via git, not just by reading the line, because an ignore rule can
    be overridden later by a negation pattern further down the file."""
    gi = [l.strip() for l in open(os.path.join(REPO, ".gitignore")).read().splitlines()]
    assert "tools/postplan-harness/out/" in gi

    runner = _runner_module()
    for name in (runner.CONFORMANCE_DONE_NAME, runner.CONFORMANCE_BRIDGE_NAME):
        rel = f"tools/postplan-harness/out/live-probe-ts/{name}"
        r = subprocess.run(["git", "check-ignore", "-q", rel], cwd=REPO)
        assert r.returncode == 0, f"{rel} is NOT gitignored (git check-ignore rc={r.returncode})"


# ---------------------------------------------------------------------------
# Rows 1-7: --foreground mode (Verification Matrix, Phase 5)
# ---------------------------------------------------------------------------

def _run_foreground(tmp_path, harness_rc, with_claude_stub=False):
    """Run post-plan-now --foreground with a fake harness exiting harness_rc.

    Stubs placed in HOME/.bun/bin/ land at position 3 of the CMD-exported PATH
    (/usr/local/bin:/opt/homebrew/bin:$HOME/.bun/bin:…), ahead of /usr/bin and
    the original $PATH tail, so they shadow any system caffeinate and, when
    with_claude_stub=True, any real claude.
    """
    home = tmp_path / "home"
    bun_bin = home / ".bun" / "bin"
    la_dir = home / "Library" / "LaunchAgents"
    la_dir.mkdir(parents=True)
    bun_bin.mkdir(parents=True)

    # caffeinate stub: swallow leading flags (-s etc.), exec the rest.
    (bun_bin / "caffeinate").write_text(
        '#!/bin/sh\nwhile [ $# -gt 0 ] && [ "${1#-}" != "$1" ]; do shift; done\nexec "$@"\n')
    (bun_bin / "caffeinate").chmod(0o755)

    # fake harness: ignore all args, exit with the requested code.
    harness = tmp_path / "fake-harness"
    harness.mkdir(exist_ok=True)
    (harness / "run").write_text(f"#!/bin/sh\nexit {harness_rc}\n")
    (harness / "run").chmod(0o755)

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness))
    env.pop("POST_PLAN_SKILL", None)

    if with_claude_stub:
        # claude stub: record each argv item on its own line via CLAUDE_LOG, exit 0.
        claude_log = tmp_path / "claude-log.txt"
        env["CLAUDE_LOG"] = str(claude_log)
        (bun_bin / "claude").write_text(
            '#!/bin/sh\nprintf "CLAUDE-ARG: %s\\n" "$@" >> "$CLAUDE_LOG"\nexit 0\n')
        (bun_bin / "claude").chmod(0o755)

    repo = _fixture_repo(tmp_path)
    return subprocess.run(
        ["bash", PPN, "--foreground"],
        cwd=repo, env=env, capture_output=True, text=True
    )


def test_detached_default_still_bootstraps(tmp_path):
    """Row 1: detached default (no flag) still writes the plist and CMD ends launchctl bootout.

    $LABEL is already interpolated when post-plan-now assembles the CMD string, so the
    CMD tail reads 'launchctl bootout gui/$(id -u)/com.ibl5.postplan-now-<slug>-<ts>'.
    """
    cmd = _generate_cmd(tmp_path)
    home = tmp_path / "home"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"detached mode must write exactly one plist, found: {plists}"
    assert re.search(
        r'launchctl bootout gui/\$\(id -u\)/com\.ibl5\.postplan-now-wt-feature-\S+$',
        cmd
    ), f"CMD must end with launchctl bootout: {cmd[-160:]!r}"


def test_rejects_misspelled_foreground():
    """Row 2: --foregound (misspelled) exits non-zero with 'unknown argument' on stderr."""
    r = subprocess.run(
        ["bash", PPN, "--foregound"],
        capture_output=True, text=True, cwd=REPO
    )
    assert r.returncode != 0
    assert "unknown argument" in r.stderr
    assert "foreground mode" not in r.stdout


def test_foreground_writes_no_plist(tmp_path):
    """Row 3: --foreground writes no file under $HOME/Library/LaunchAgents/."""
    r = _run_foreground(tmp_path, 0)
    home = tmp_path / "home"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 0, \
        f"--foreground must write no plist (exec happens before plist code), found: {plists}"
    # Secondary: no 'launchctl bootstrap' should appear in stdout or stderr.
    assert "launchctl bootstrap" not in r.stdout + r.stderr


def test_foreground_marker_wording_harness_ok(tmp_path):
    """Row 4: harness exit 0 → FULL marker wording in stdout, exit 0.

    Asserts the entire marker token-for-token; the harness-run-dir path is
    anchored on the actual fake-harness path and a timestamp wildcard.
    """
    r = _run_foreground(tmp_path, 0)
    assert r.returncode == 0, \
        f"expected exit 0, got {r.returncode}: stderr={r.stderr!r}"
    harness = tmp_path / "fake-harness"
    pattern = re.compile(
        r'^post-plan-now: postplan-rc=0 harness-rc=0 harness-run-dir='
        + re.escape(str(harness))
        + r'/out/live-wt-feature-\S+$',
        re.MULTILINE
    )
    assert pattern.search(r.stdout), \
        f"full marker not found in stdout:\n{r.stdout!r}"


def test_foreground_exit3_fails_closed(tmp_path):
    """Row 5: harness exit 3 fails closed — no claude fallback, postplan-rc=3 harness-rc=3, exit 3."""
    r = _run_foreground(tmp_path, 3)
    assert r.returncode == 3, \
        f"expected exit 3 (fail-closed), got {r.returncode}: stderr={r.stderr!r}"
    assert re.search(
        r'^post-plan-now: postplan-rc=3 harness-rc=3 harness-run-dir=\S+$',
        r.stdout, re.MULTILINE
    ), f"postplan-rc=3 harness-rc=3 marker not found in stdout:\n{r.stdout!r}"


def test_foreground_exit1_falls_back(tmp_path):
    """Row 6: harness exit 1 → claude fallback runs; marker carries harness-rc=1."""
    r = _run_foreground(tmp_path, 1, with_claude_stub=True)
    assert re.search(
        r'^post-plan-now: postplan-rc=\d+ harness-rc=1 harness-run-dir=\S+$',
        r.stdout, re.MULTILINE
    ), f"harness-rc=1 marker not found in stdout:\n{r.stdout!r}"
    claude_log = tmp_path / "claude-log.txt"
    assert claude_log.exists(), "claude stub must have been invoked for harness-rc=1"
    log_text = claude_log.read_text()
    assert "then execute every phase" in log_text, \
        f"fallback prompt not found in claude args:\n{log_text!r}"


def test_foreground_exit0_launches_no_claude(tmp_path):
    """Row 8: harness exit 0 → success; claude must NOT be invoked."""
    r = _run_foreground(tmp_path, 0, with_claude_stub=True)
    assert r.returncode == 0
    assert not (tmp_path / "claude-log.txt").exists(), \
        "claude must not be invoked on harness-rc=0"


def test_foreground_exit4_takes_full_fallback_not_resume(tmp_path):
    """Row 7: harness exit 4 → full skill fallback (same as rc=1); marker carries harness-rc=4."""
    r = _run_foreground(tmp_path, 4, with_claude_stub=True)
    assert re.search(
        r'^post-plan-now: postplan-rc=\d+ harness-rc=4 harness-run-dir=\S+$',
        r.stdout, re.MULTILINE
    ), f"harness-rc=4 marker not found in stdout:\n{r.stdout!r}"
    claude_log = tmp_path / "claude-log.txt"
    assert claude_log.exists(), "claude stub must have been invoked for rc=4"
    log_text = claude_log.read_text()
    assert "then execute every phase" in log_text, \
        f"full fallback prompt not found in claude args:\n{log_text!r}"
    assert "RESUMING at Phase 5.5" not in log_text, \
        "rc=4 must take the full fallback, not the old RESUME_PROMPT"

# --pr flag tests
# ---------------------------------------------------------------------------

def test_pr_flag_requires_an_argument():
    r = subprocess.run(["bash", PPN, "--pr"],
                       capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "--pr requires a PR number argument" in r.stderr


def test_pr_flag_rejects_non_numeric():
    for bad in ("abc", "-3", "12x", "0"):
        r = subprocess.run(["bash", PPN, "--pr", bad],
                           capture_output=True, text=True, cwd=REPO)
        assert r.returncode == 1, f"--pr {bad!r}: expected rc=1, got {r.returncode}"
        assert "--pr needs a positive integer PR number" in r.stderr, \
            f"--pr {bad!r}: {r.stderr!r}"
        assert bad in r.stderr, f"--pr {bad!r}: bad value not echoed in {r.stderr!r}"


def test_unknown_argument_message_lists_pr():
    r = subprocess.run(["bash", PPN, "--prr", "5"],
                       capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "unknown argument" in r.stderr
    assert "--pr" in r.stderr


def test_pr_flag_unresolvable_branch_errors(tmp_path):
    """gh returns empty/failure → no worktree found error."""
    stub_dir = tmp_path / "stub-bin"
    stub_dir.mkdir()
    gh = stub_dir / "gh"
    gh.write_text("#!/bin/sh\nexit 1\n")
    gh.chmod(0o755)
    env = dict(os.environ, PATH=f"{stub_dir}:{os.environ['PATH']}")
    r = subprocess.run(["bash", PPN, "--pr", "99"],
                       capture_output=True, text=True, cwd=REPO, env=env)
    assert r.returncode == 1
    assert "could not resolve a head branch" in r.stderr


def test_pr_flag_rejects_a_branch_with_no_worktree(tmp_path):
    """gh resolves branch, but no worktree has it checked out."""
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    stub_dir = tmp_path / "stub-bin"
    stub_dir.mkdir()
    _gh_stub(stub_dir, "orphan-branch")
    env = dict(os.environ,
               PATH=f"{stub_dir}:{os.environ['PATH']}",
               POST_PLAN_MAIN_ROOT=str(main_root))
    r = subprocess.run(["bash", PPN, "--pr", "7"],
                       capture_output=True, text=True,
                       cwd=str(main_root), env=env)
    assert r.returncode == 1
    assert "no worktree has it checked out" in r.stderr


def test_pr_flag_targets_the_matching_worktree_and_exports_plan_slug(tmp_path):
    """--pr resolves the worktree, writes one plist, and injects PLAN_SLUG before cd."""
    main_root, wt_root = _fixture_worktree(tmp_path, "pr-branch")
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)
    stub_dir = tmp_path / "stub-bin"; stub_dir.mkdir()
    _gh_stub(stub_dir, "pr-branch")
    env = dict(os.environ,
               HOME=str(home),
               HARNESS=str(harness),
               PATH=f"{shim}:{stub_dir}:{os.environ['PATH']}",
               POST_PLAN_MAIN_ROOT=str(main_root))
    env.pop("POST_PLAN_SKILL", None)
    r = subprocess.run(["bash", PPN, "--pr", "42"],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one plist, got {plists}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    cmd = cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")
    assert "export PLAN_SLUG=" in cmd
    assert "pr-branch" in cmd
    assert f'cd "{wt_root}"' in cmd
    # PLAN_SLUG export must precede the cd to the worktree root
    assert cmd.index("export PLAN_SLUG=") < cmd.index(f'cd "')


def test_pr_flag_plan_blind_when_no_plan_file_exists(tmp_path):
    """--pr succeeds even when no plan file exists for the branch (plan-blind mode)."""
    main_root, wt_root = _fixture_worktree(tmp_path, "pr-branch")
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    (home / "claude-plans").mkdir(parents=True)   # plans dir exists but empty
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)
    stub_dir = tmp_path / "stub-bin"; stub_dir.mkdir()
    _gh_stub(stub_dir, "pr-branch")
    env = dict(os.environ,
               HOME=str(home),
               HARNESS=str(harness),
               PATH=f"{shim}:{stub_dir}:{os.environ['PATH']}",
               POST_PLAN_MAIN_ROOT=str(main_root))
    env.pop("POST_PLAN_SKILL", None)
    r = subprocess.run(["bash", PPN, "--pr", "42"],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one plist, got {plists}"
    assert "plan-blind" in r.stderr or "no plan at" in r.stderr, \
        f"expected plan-missing warning in stderr: {r.stderr!r}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    cmd = cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")
    assert "export PLAN_SLUG=" in cmd, "PLAN_SLUG must be in CMD even in plan-blind mode"
    assert "pr-branch" in cmd


def test_refuses_to_run_in_the_main_checkout(tmp_path):
    """post-plan-now exits 1 with ADR-0062 message when run from main checkout."""
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    env = dict(os.environ, HOME=str(home),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    # Run from the main checkout itself (which is on some-feature, not master/main/HEAD)
    r = subprocess.run(["bash", PPN],
                       capture_output=True, text=True,
                       cwd=str(main_root), env=env)
    assert r.returncode == 1
    assert "refusing to run in the main checkout" in r.stderr
    assert "ADR-0062" in r.stderr
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert plists == [], "no plist should be written when refusing"


# ---------------------------------------------------------------------------
# SKILL.md Phase 0 guard block tests
# ---------------------------------------------------------------------------

def test_phase0_guard_extracts_cleanly():
    """_guard_block() must return a non-empty string containing both markers."""
    block = _guard_block()
    assert "# --- post-plan worktree guard (Phase 0) ---" in block
    assert "# --- end post-plan worktree guard ---" in block
    assert len(block) > 100


def test_phase0_guard_already_in_target_arm_is_a_noop(tmp_path):
    """ALREADY-IN-TARGET arm: linked worktree on the right branch → exit 0."""
    _, wt_root = _fixture_worktree(tmp_path, "target-branch")
    block = _guard_block()
    env = dict(os.environ, PLAN_SLUG="target-branch")
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "ALREADY-IN-TARGET" in r.stdout


def test_phase0_guard_main_checkout_arm_exits_nonzero(tmp_path):
    """MAIN-CHECKOUT arm: running from the main checkout → exit 1, STOP in output."""
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    block = _guard_block()
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(main_root))
    assert r.returncode == 1
    assert "STOP" in r.stdout
    assert "MAIN-CHECKOUT" in r.stdout


def test_phase0_guard_wrong_worktree_arm_exits_nonzero(tmp_path):
    """WRONG-WORKTREE arm: linked worktree on wrong branch → exit 1."""
    _, wt_root = _fixture_worktree(tmp_path, "wrong-branch")
    block = _guard_block()
    env = dict(os.environ, PLAN_SLUG="different-branch")
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 1
    assert "STOP" in r.stdout
    assert "WRONG-WORKTREE" in r.stdout
    assert "wrong-branch" in r.stdout
    assert "different-branch" in r.stdout


def test_phase0_guard_main_checkout_arm_wins_over_a_branch_match(tmp_path):
    """MAIN-CHECKOUT must win even when PLAN_SLUG matches the main checkout's branch.

    If the guard checked branch equality first, a main-checkout run on the correct
    branch would slip through to ALREADY-IN-TARGET and exit 0. The is_in_worktree
    check must be the outer gate.
    """
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    # The main checkout is on 'some-feature' (set by _fixture_repo).
    main_branch_r = subprocess.run(
        ["git", "rev-parse", "--abbrev-ref", "HEAD"],
        capture_output=True, text=True, cwd=str(main_root), check=True)
    main_branch = main_branch_r.stdout.strip()
    block = _guard_block()
    # Set PLAN_SLUG to the main checkout's own branch — branch matches, but it's still main.
    env = dict(os.environ, PLAN_SLUG=main_branch)
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(main_root), env=env)
    assert r.returncode == 1, "main checkout must be refused even when branch matches PLAN_SLUG"
    assert "MAIN-CHECKOUT" in r.stdout


def test_phase0_guard_prearm_blocks_reserved_branch_name(tmp_path):
    """Pre-arm: a worktree whose branch is named 'master' exits 1 before is_in_worktree.

    The master|main|HEAD case fires on the branch-name check, not the worktree
    check, so it catches a linked worktree on a reserved name as well as the main
    checkout itself.

    Uses the fixture repo's existing 'master' branch rather than -b master, which
    git rejects when 'master' already exists as the initial default branch.
    """
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    wt_master = tmp_path / "wt-on-master"
    subprocess.run(
        ["git", "worktree", "add", str(wt_master), "master"],
        cwd=str(main_root), check=True, capture_output=True)
    wt_root = wt_master
    block = _guard_block()
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(wt_root))
    assert r.returncode == 1, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "STOP" in r.stdout
    assert "MAIN-CHECKOUT or detached HEAD" in r.stdout


def test_phase0_guard_prearm_blocks_detached_head(tmp_path):
    """Pre-arm: a detached HEAD state exits 1 before the is_in_worktree check."""
    _, wt_root = _fixture_worktree(tmp_path, "some-detach-branch")
    subprocess.run(["git", "checkout", "--detach", "HEAD"],
                   cwd=str(wt_root), check=True, capture_output=True)
    block = _guard_block()
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(wt_root))
    assert r.returncode == 1, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "STOP" in r.stdout
    assert "MAIN-CHECKOUT or detached HEAD" in r.stdout

# Exit-5 guard: a PR already exists and the working set touches no production files.
# The failure mode is a SILENTLY DEAD guard, so each test below names the mutation it
# catches; a guard that never fires still leaves the whole rest of this file green.
# ---------------------------------------------------------------------------

_TEST_ONLY_DIRTY = {"ibl5/tests/Foo/BarTest.php": "<?php\n// test-only change\n"}


def test_guard_exits_5_when_pr_exists_and_no_production_files(tmp_path):
    """Catches: the guard block deleted, the exit code changed, or the message no longer
    naming the cheaper command."""
    r = _run_ppn(tmp_path, extra_env={"FAKE_PR_NUMBER": "2186"}, dirty=_TEST_ONLY_DIRTY)
    assert r.returncode == 5, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "/commit-commands:commit-push-pr" in r.stderr
    assert "PR #2186" in r.stderr


def test_guard_proceeds_when_pr_exists_and_uncommitted_production_files(tmp_path):
    """The false-block case: zero commits ahead of origin/master, one UNCOMMITTED
    production file. Catches narrowing the changed set back to
    `git diff --name-only origin/master...HEAD` alone, which sees nothing here."""
    dirty = dict(_TEST_ONLY_DIRTY)
    dirty["ibl5/modules/Player/index.php"] = "<?php\n// production change\n"
    r = _run_ppn(tmp_path, extra_env={"FAKE_PR_NUMBER": "2186"}, dirty=dirty)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "no production files" not in r.stderr


def test_guard_force_flag_overrides_exit_5(tmp_path):
    """Catches: the --force arm dropped, or the guard reading FORCE before the arg loop
    assigns it (which under `set -u` is exit 2, not 0)."""
    r = _run_ppn(tmp_path, args=("--force",),
                 extra_env={"FAKE_PR_NUMBER": "2186"}, dirty=_TEST_ONLY_DIRTY)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"


def test_guard_proceeds_when_no_pr_exists(tmp_path):
    """The hard stop: the guard must NOT widen to the no-PR case. Catches removing or
    inverting `[ -n "$PR_NUM" ]`."""
    r = _run_ppn(tmp_path, dirty=_TEST_ONLY_DIRTY)      # FAKE_PR_NUMBER unset ⇒ gh prints nothing
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "no production files" not in r.stderr


def test_force_flag_rejected_forms_fail_loudly(tmp_path):
    """--force is a BARE flag only. Catches a permissive `--force=*)` arm, under which a
    typo'd `--force=0` would silently ENABLE the override."""
    # index, not a slug of `args` — both forms slugify to "force1" and would collide on
    # the same tmp dir, making the second iteration blow up in setup instead of asserting.
    for i, args in enumerate((("--force=1",), ("--force", "1"))):
        r = _run_ppn(tmp_path / f"case{i}",
                     args=args, extra_env={"FAKE_PR_NUMBER": "2186"},
                     dirty=_TEST_ONLY_DIRTY)
        assert r.returncode != 0, f"{args}: expected failure, got 0"
        assert "unknown argument" in r.stderr, f"{args}: got {r.stderr!r}"


# ---------------------------------------------------------------------------
# Phase 4 CI-outcome resume clause. Phase 8 removed its only call site with the
# exit-4 resume; the function and these tests stay for the skill-fallback arm.
# ---------------------------------------------------------------------------

def _clause(run_dir, sha):
    """Run the REAL shell function out of bin/post-plan-now, not a text match."""
    script = ('eval "$(sed -n "/^postplan_ci_resume_clause()/,/^}/p" "$1")"; '
              'postplan_ci_resume_clause "$2" "$3"')
    return subprocess.run(["bash", "-c", script, "_", PPN, str(run_dir), sha],
                          capture_output=True, text=True)

def _ci_file(tmp_path, sha, status):
    p = tmp_path / f"ci-{sha}.json"
    # indent=2/sort_keys shape matters: the clause greps the status on its own line
    p.write_text('{\n'
                 '  "failed_checks": [\n    "build"\n  ],\n'
                 '  "sha": "' + sha + '",\n'
                 '  "status": "' + status + '"\n'
                 '}\n')
    return p

def test_ci_clause_reports_success(tmp_path):
    _ci_file(tmp_path, "aaa111", "success")
    r = _clause(tmp_path, "aaa111")
    assert r.returncode == 0, r.stderr
    assert "SUCCESS" in r.stdout
    assert "Do NOT run" in r.stdout

def test_ci_clause_reports_failure_and_orders_fix_before_5_5(tmp_path):
    _ci_file(tmp_path, "bbb222", "failure")
    r = _clause(tmp_path, "bbb222")
    assert r.returncode == 0, r.stderr
    assert "FAILURE" in r.stdout
    assert "BEFORE spawning the Phase 5.5 fidelity reviewer" in r.stdout

def test_ci_clause_unknown_when_file_missing(tmp_path):
    """Fail-open lands on "watch it yourself", never on a fabricated green."""
    r = _clause(tmp_path, "ccc333")
    assert r.returncode == 0, r.stderr
    assert "unknown" in r.stdout
    assert "SUCCESS" not in r.stdout

def test_ci_clause_indeterminate_on_timeout_status(tmp_path):
    """A watch that never settled is not a reusable verdict."""
    _ci_file(tmp_path, "ddd444", "timeout")
    r = _clause(tmp_path, "ddd444")
    assert r.returncode == 0, r.stderr
    assert "indeterminate" in r.stdout
    assert "SUCCESS" not in r.stdout

def test_ci_clause_exits_zero_on_garbage_file(tmp_path):
    """A truncated or corrupt outcome file must not abort the resume command."""
    (tmp_path / "ci-eee555.json").write_text("not json")
    r = _clause(tmp_path, "eee555")
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "SUCCESS" not in r.stdout
    assert r.stdout.startswith("CI OUTCOME:")

# --- Phase 2: session-id sidecar tests ---------------------------------------------------

def _plist_log_path(tmp_path):
    """Return the log path embedded in the generated plist's StandardOutPath."""
    home = tmp_path / "home"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one plist, got {plists}"
    body = plists[0].read_text()
    m = re.search(r"<key>StandardOutPath</key>\s*<string>(.*?)</string>", body, re.S)
    assert m, "StandardOutPath not found in plist"
    return m.group(1).replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")


def test_generated_cmd_pins_exactly_one_session_id(tmp_path):
    """The harness arm's claude -p leg carries exactly one --session-id.

    Phase 8 removed the rc=4 resume leg, so $CMD now holds a single invocation.
    Exactly-one is the assertion that matters: a second uuid appearing here would
    mean a leg came back untracked, leaving the sidecar pointing at the wrong
    transcript.
    """
    cmd = _generate_cmd(tmp_path)
    matches = re.findall(r"--session-id '([0-9a-f-]{36})'", cmd)
    assert len(matches) == 1, f"expected 1 --session-id occurrence, got {matches}"


def test_session_id_matches_the_sidecar(tmp_path):
    """The uuid in $CMD must match the sidecar written next to the log."""
    cmd = _generate_cmd(tmp_path)
    matches = re.findall(r"--session-id '([0-9a-f-]{36})'", cmd)
    assert matches, "no --session-id found in cmd"
    uuid = matches[0]

    log_path = _plist_log_path(tmp_path)
    sidecar = pathlib.Path(log_path[:-4] + ".session")
    try:
        assert sidecar.exists(), f"sidecar not found at {sidecar}"
        assert sidecar.read_text() == f"{uuid}\n", \
            f"sidecar content {sidecar.read_text()!r} != {uuid!r}"
    finally:
        sidecar.unlink(missing_ok=True)


def test_session_id_is_lowercase_canonical(tmp_path):
    """The uuid must be lowercase — macOS uuidgen emits uppercase, which is_session_id rejects.

    Explicitly assert uuid == uuid.lower(): an uppercase uuid passing the shq wrapper
    would reach the plist fine but be rejected by the consumer's is_session_id gate,
    silently producing a live run that shows permanently blank progress.
    """
    cmd = _generate_cmd(tmp_path)
    matches = re.findall(r"--session-id '([0-9a-f-]{36})'", cmd)
    assert matches, "no --session-id found in cmd"
    uuid = matches[0]
    assert re.match(r'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$', uuid), \
        f"uuid {uuid!r} is not lowercase canonical"
    assert uuid == uuid.lower(), f"uuid {uuid!r} is not lowercase"


def test_skill_only_leg_also_carries_a_session_id(tmp_path):
    """POST_PLAN_SKILL=1: GATE_OPEN/GATE_CLOSE are empty, exactly one caffeinate invocation,
    and it still carries --session-id.

    This is the arm with no harness — an edit that only touches the harness gate would
    leave the skill-only path bare, and it is the arm that runs on every machine where
    tools/postplan-harness/run is not executable.
    """
    cmd = _generate_cmd(tmp_path, extra_env={"POST_PLAN_SKILL": "1"})
    assert cmd.count("caffeinate -s claude -p ") == 1, \
        "POST_PLAN_SKILL=1 should produce exactly one caffeinate invocation"
    matches = re.findall(r"--session-id '([0-9a-f-]{36})'", cmd)
    assert len(matches) == 1, f"expected one --session-id in skill-only cmd, got {matches}"


def test_exit3_message_is_shell_safe_and_names_both_causes():
    """GATE_CLOSE is re-parsed by /bin/bash -lc on the far side of the launchd plist:
    a backtick or $( ) there silently mangles the message (see the file's own comments).
    It must also no longer claim rebase conflict is the only cause of exit 3."""
    src = open(PPN).read()
    line = [l for l in src.splitlines() if l.strip().startswith("GATE_CLOSE=\"; elif")]
    assert len(line) == 1, f"expected one populated GATE_CLOSE, got {len(line)}"
    body = line[0]
    assert "`" not in body, "backtick in GATE_CLOSE survives to a second shell parse"
    assert "$(" not in body, "command substitution in GATE_CLOSE"
    assert "gate denial" in body and "rebase conflict" in body


def test_badge_banner_rc3_names_both_fail_closed_causes():
    """The PR badge must not blame a rebase for a gate denial.

    Exit 3 carries two kinds since #2259; a banner naming only one sends the operator
    to the wrong recovery.
    """
    src = open(PPN).read()
    line = [l for l in src.splitlines() if l.strip().startswith("3) printf")]
    assert len(line) == 1, f"expected one rc=3 banner arm, got {len(line)}"
    body = line[0]
    assert "gate denial" in body and "rebase conflict" in body
    assert "`" not in body and "$(" not in body, "banner body is re-parsed via declare -f"


def test_exit3_dm_and_echo_send_the_same_single_string():
    """Pins the single-string invariant: echo and discord-dm both receive the same $msg,
    assigned exactly once. Tests 3-6 rely on stdout as a valid proxy for the DM text."""
    src = open(PPN).read()
    line = [l for l in src.splitlines() if l.strip().startswith("GATE_CLOSE=\"; elif")]
    assert len(line) == 1, f"expected one populated GATE_CLOSE, got {len(line)}"
    body = line[0]
    assert 'echo \\"\\$msg\\"' in body
    assert '--quiet --attempts 2 \\"\\$msg\\"' in body
    assert body.count("msg=") == 1


def test_generated_cmd_is_syntactically_valid_bash(tmp_path):
    cmd = _generate_cmd(tmp_path)
    path = tmp_path / "cmd.sh"
    path.write_text(cmd)
    assert subprocess.run(["bash", "-n", str(path)]).returncode == 0


def test_mint_failure_aborts_before_bootstrap(tmp_path):
    """Stub uuidgen and python3 to fail: post-plan-now must exit non-zero and write no plist.

    The plist-count assertion is the load-bearing one: a version that warns and continues
    would still exit non-zero on some other path while having fired a progressless run.
    """
    home = tmp_path / "home"
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    (shim / "gh").write_text("#!/bin/sh\nexit 0\n")
    (shim / "gh").chmod(0o755)
    (shim / "uuidgen").write_text("#!/bin/sh\nexit 1\n")
    (shim / "uuidgen").chmod(0o755)
    (shim / "python3").write_text("#!/bin/sh\nexit 1\n")
    (shim / "python3").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    repo = _fixture_repo(tmp_path)
    r = subprocess.run(["bash", PPN], cwd=repo, env=env, capture_output=True, text=True)

    assert r.returncode != 0, f"expected non-zero exit, got 0 (stderr: {r.stderr!r})"
    assert "session uuid" in r.stderr, f"stderr should name the uuid; got {r.stderr!r}"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 0, f"plist written despite mint failure: {plists}"


def test_guard_refuses_when_a_run_is_in_flight(tmp_path):
    r = _run_ppn(tmp_path, launchctl_stub=_LAUNCHCTL_LIVE)
    assert r.returncode == 6
    assert "already in flight" in r.stdout
    plist_dir = tmp_path / "home" / "Library" / "LaunchAgents"
    assert plist_dir.exists(), f"plist dir missing -- the glob below would be vacuous: {plist_dir}"
    plists = list(plist_dir.glob("com.ibl5.postplan-now-wt-feature-*.plist"))
    assert len(plists) == 0, "guard must not submit a plist when refusing"


def test_guard_allows_when_no_run_is_in_flight(tmp_path):
    r = _run_ppn(tmp_path)
    assert r.returncode != 6
    assert "already in flight" not in r.stdout


def test_guard_force_flag_overrides_inflight_refusal(tmp_path):
    r = _run_ppn(tmp_path, args=("--force",), launchctl_stub=_LAUNCHCTL_LIVE)
    assert r.returncode != 6
    assert "already in flight" not in r.stdout


def test_guard_slug_prefix_does_not_match_a_longer_slug(tmp_path):
    r = _run_ppn(tmp_path, launchctl_stub=_LAUNCHCTL_OTHER_SLUG)
    assert r.returncode != 6


def test_inflight_label_matching_is_anchored(tmp_path):
    shim = tmp_path / "bin"; shim.mkdir()
    lc = shim / "lc"
    lc.write_text(
        '#!/bin/sh\n'
        'printf "%s\\t%s\\t%s\\n" 1 0 com.ibl5.postplan-now-slug-extra-20260916-144924-7\n'
        'printf "%s\\t%s\\t%s\\n" 2 0 com.ibl5.postplan-now-slug-20260916-144924-7\n'
        'printf "%s\\t%s\\t%s\\n" 3 0 com.ibl5.postplan-now-slug-notatimestamp\n'
    )
    lc.chmod(0o755)
    def call(want):
        return subprocess.run(
            ["bash", "-c",
             f'export LAUNCHCTL_CMD={lc}; source "{PPN}" >/dev/null 2>&1; postplan_inflight_label "{want}"'],
            capture_output=True, text=True).stdout.strip()
    assert call("slug") == "com.ibl5.postplan-now-slug-20260916-144924-7"
    assert call("slug-extra") == "com.ibl5.postplan-now-slug-extra-20260916-144924-7"
    assert call("nosuch") == ""
    assert call("") == ""


def test_gate_denial_fails_closed_then_allows_a_refire(tmp_path):
    # Leg 1 -- job A: the harness dies on a local gate denial (rc=3, fail-closed).
    r = _run_foreground(tmp_path, 3, with_claude_stub=True)
    assert r.returncode == 3
    assert re.search(
        r"^post-plan-now: postplan-rc=3 harness-rc=3 harness-run-dir=\S+$",
        r.stdout, re.M), r.stdout
    assert "fail-closed sentinel" in r.stdout
    assert not (tmp_path / "claude-log.txt").exists(), \
        "rc=3 must NOT spawn a /post-plan skill session"
    # Leg 2 -- job B at 14:49:24: job A is gone, so its label is gone, so a re-fire is
    # ALLOWED. A guard that refuses here is a failed implementation, not a stricter one.
    r2 = _run_ppn(tmp_path / "leg2")  # default launchctl stub: silent, no live job
    assert r2.returncode != 6, r2.stdout
    assert "already in flight" not in r2.stdout
