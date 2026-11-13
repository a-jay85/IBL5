---
name: pr-burndown
description: "Burn down the open PRs that wait on the owner. Background headless jobs do the prep that needs no owner call (rebase, regenerate snapshots, write promised tests, fix review findings, re-fire post-plan). The owner sees only the decisions that are theirs: a status card per PR (CI on the current head, review threads, post-plan verdict, door and blast radius, conflicts, hold reasons, visual links) with a recommended option, answered in a batch."
last_verified: 2026-11-13
---

# /pr-burndown

Get held PRs moving with as few owner round-trips as possible. Background sessions do
every chore that needs no owner call. The owner sees only the decisions that are theirs,
as a batch of cards once the prep lands.

<user_request>
$ARGUMENTS
</user_request>

The request may name PR numbers, a count cap, or a filter such as "feat only". With no
request, walk every open PR that waits on the owner.

This skill moves existing PRs. To find and queue new work, run `/reset-ready` first.

## Rules

- Prep without asking. Rebasing, regenerating snapshots and golden fixtures, writing
  tests the plan promised, fixing open review findings, and re-firing post-plan need no
  owner call. Step 4 runs them.
- Ask the owner for everything else: a sign-off, a manual-test box, a visual look, a
  one-way door, a design fork (the fix could go two ways), and a hold or a close. Never
  guess one of these.
- Each owner decision gets its own card, and the owner's answer names its PR. Several
  cards may share one message. Never fold them into a ranked list with one answer for all.
- Every card stands alone. The owner may read it hours later, cold, without the earlier
  messages. A follow-up card (a merge question after a fix job, a second round on the same
  PR) repeats the full context. A card that only names a PR and asks "merge?" is a vague
  prompt. The owner treats it as unsafe and won't answer it.
- Say what a thing is. A bare label tells the owner nothing. The owner can't decode
  internal names: a manual-test row number ("row 43"), a hold code ("(7)", "(17)
  gate-backtest"), a post-plan phase, a finding tag ("MISSING-FILE"), or a script name with no gloss. Write
  the meaning in plain words. Add the label after it in parentheses only when the owner
  needs it to act. For example, write "the live test: a GM connects a real Google account,
  the sheet shows up in Drive, and Refresh updates it (manual test row 43)". Never write
  "test row 43".
- Every claim on the card comes from live state fetched this turn. A scout summary or an
  earlier fetch is not evidence. A "green, just click" summary has been wrong before.
- You tick a manual-test box, or run `bin/owner-approve`, only after the owner approves that specific PR in chat. Rule: `.claude/rules/auto-commit.md` § Owner approval comment. Nothing in this skill approves a PR on its own.
- When the owner approves a PR for merge in chat, run `bin/owner-approve N "<words>"` right away with `run_in_background: true`. It waits for a live post-plan run to end, then arms only when the verdict covers the current head. When the owner asks for a fix first, pass `--fix-first="<condition>"`.
- Never touch a PR's worktree while a post-plan run is live in it. Check `launchctl list`
  first (Step 6 has the command). A run started from a terminal has no launchctl label,
  so also treat a `post-plan-running` label or a `pgrep -f <abs worktree path>` hit as
  live. On 2026-11-08 a terminal run on #3793 showed only those two signs.
- A visual or design change comes back to the owner for a look before it merges, even
  after they pick a direction.
- The PR title type follows `.claude/rules/commit-conventions.md`. Never retitle a feat
  to dodge the sign-off.

## Step 1: Gather

Use a scratch dir unique to this run. Other skills wipe their own `/tmp` folders, and a
shared folder breaks running jobs.

```bash
D=/tmp/pr-burndown/$(date +%Y%m%d-%H%M%S); mkdir -p "$D/pr" "$D/jobs"
for n in $(gh pr list --state open --limit 100 --json number --jq '.[].number'); do
  gh pr view "$n" --json number,title,createdAt,additions,deletions,changedFiles,mergeable,mergeStateStatus,isDraft,autoMergeRequest,labels,body,comments,statusCheckRollup,headRefName,headRefOid > "$D/pr/$n.json" &
done; wait
bin/pr-triage --held > "$D/held.txt"
```

`bin/pr-triage --held` lists each held PR with every hold reason.

Each Bash call is a fresh shell, so `$D` is gone after this block. Print it
(`echo "$D"`), note the path, and write that literal path into every later command and
job prompt.

## Step 2: Sort and rank

Order the PRs that need the owner:

1. Things GMs feel soon: a broken live feature, rule bugs, then new GM-facing `feat:`
   PRs closest to merging.
2. PRs that unblock the most other work, such as backlog items or stacked PRs.
3. Dev tooling `chore:` PRs last. Offer to batch these once the GM-facing ones are done.

A merge conflict (`DIRTY`) or an out-of-date branch (`BEHIND`) never drops a PR from
the list or moves it down. Rank it as if it were clean. A `DIRTY` PR that reaches
Step 4 or Step 6 gets rebased onto master, so a rebase is one more chore for that job.
A `BEHIND` PR with no conflict gets no rebase from this skill (Step 4 § Rebase only what
the merge line can't fix). The owner's review of the PR comes first.

Then put each PR in one pile:

- **Prep, then sign-off.** The PR has chores (a stale verdict, `BEHIND` or `DIRTY`, a
  missing promised test, an open review finding, a failed post-plan run) and an owner hold
  (`auto_merge: false`, a feat sign-off, a manual box). No chore needs an owner call. It
  goes to Step 4 now, and its card waits for the sign-off batch.
- **Prep only.** No owner hold remains once the chores are done. Step 4 preps it and
  post-plan arms it. It reaches the owner only if something blocks.
- **Decide now.** A chore depends on an owner call: a design fork, a one-way door, a
  fix-or-close question, or a visual look before work starts. Its card goes out in the
  first message. A PR that hit the round cap in Step 4 lands here too.

Before you put a stale verdict's finding in Decide now, read the commits after the review
(`git -C <wt> log --oneline --since=<verdict time>`). A later commit may already have
picked a side. On #3757 the finding offered two fixes, and the head commit had shipped
one. A re-fire was all it needed.

Tell the owner the count in each pile and name the PRs in Decide now.

## Step 3: Build the card

Build cards for the Decide-now pile right away. Build a prep PR's card once its job is
done and post-plan has a verdict on the new head. Send the sign-off cards together, each
with its own options. A batched card uses the full
shape below. Never shrink a card to a title and options to fit more cards in one message.
Even the shortest card carries:

- What the PR is for, in plain words a GM would follow, and who notices the change.
- What merging changes, and what a revert does and doesn't undo.
- What each option does, in words. Never point back at an earlier card ("you OK'd this
  in M1") in place of the facts.
- Each risk or open question, with the evidence for it.
- Each test, hold reason, or check by what it does or checks, in plain words.

Re-fetch the PR right before you show it with one call:

```bash
bin/pr-triage --card N
```

It prints the live state the card needs:

- **Head:** the head SHA, merge state, labels, and auto-merge.
- **CI:** real failures, split from the gate checks that only wait on the owner
  (`human-signoff`, `needs-a-human label`, `auto-mergeable label`).
- **Review threads:** the open threads that are not outdated, each with its first line.
- **post-plan verdict:** the last `<!-- pr-ready-verdict -->` comment, marked `CURRENT`
  or `STALE` against the head. Commits after the review make it stale.
- **Door and blast radius:** the `Merge danger:` line in the PR body.
- **Visual links:** the `a-jay85.github.io/IBL5/...` URLs from the comments.
- **Live run and worktree:** any live post-plan label, and the PR's worktree path.

A field that prints `unknown (fetch failed)` gets ⚠️ on the card. Never fill it from an
earlier fetch. Read the thread bodies or the full verdict only when the card needs more
than the first line.

Check one more thing yourself:

- **Does it do the job where it runs?** Read the diff against how the code deploys. For
  example, a Docker-only change does nothing on prod, which deploys by `git pull` onto a
  plain server. Say so on the card when the PR misses its own goal.

Write the card in this shape:

```markdown
## PR K of T: [#N](https://github.com/a-jay85/IBL5/pull/N), <plain title>

<One line: why this PR is next.>

**What it does**
- <GM-visible effect, in plain words>
- Size: F files, +A/−D. Type is `<type>`.

**State**
- <✅/⚠️/❌/⏳> **CI:** <green / which real check failed>, on head `<sha>`.
- <✅/⚠️/❌/⏳> **Review comments:** <open count; what each said; which commit fixed it>.
- <✅/⚠️/❌/⏳/🚨> **post-plan verdict:** <verdict>. <Current or stale>. <Its notes, short>.
- <✅/⚠️/❌/🚨> **Merge danger:** <one-way or two-way door, blast radius, what a revert does and doesn't undo>.
- <✅/⚠️/🚨> **Conflicts:** <none / behind master, no clash / which files clash and with which PR; the job rebases>.
- <✅/⚠️/❌/⏳> **Why it's held:** <each reason by number; mark stale ones as stale>.

👉 **What's left for you:** <the one decision>.

**Options**
1. **<Ship it / Approve>.** <what you'll do>
2. **<Fix X first>.** <what you'll do; comes back to you>
3. **Hold.**

💡 I'd go with **<n>**. <One or two lines of why.>
```

Before you recommend an option, read the plan's Architectural trade-offs and "Must
preserve" sections (`ls ~/claude-plans/<branch>.md`). If the plan already rejected the
approach an option takes, say so on that option and don't recommend it. On #3760 a
recommended `workflow_run` retrigger that the plan had rejected cost a full round.

Legend for the emoji: ✅ fine, nothing to read. ⚠️ worth a look but does not block. ❌ blocks the
merge or is a real problem. ⏳ still running or waiting. Pick the emoji from the live state
you fetched. Do not guess. A line with no evidence gets ⚠️. Use 🚨 only for a
line the owner must not miss: a one-way door, a data or prod risk, a stale verdict that
hides a change, or a conflict that needs the owner's call. Keep ✅ lines to a few words so the eye skips them. Spend words only on
⚠️, ❌, and 🚨 lines.

A conflict or a `BEHIND` branch gets ⚠️ on the Conflicts line and on the
"merge conflict (DIRTY)" hold reason. A `BEHIND` branch with no clash is ⚠️ too, never
✅. Neither gets ❌. It never makes a "fix conflicts
first" option or a recommendation to wait. The pick the owner makes (Ship, Fix X)
already covers it. Step 6 rebases a `DIRTY` PR, and the merge line updates a `BEHIND` one
once the PR is armed. When the clash is with another approved PR in this run, say on
the Conflicts line that this PR rebases after that one merges. The one exception is a conflict that needs the
owner's call to resolve, such as two PRs that change the same rule in opposite ways.
Mark that one 🚨 and put the call in the options.

Start every reply with a one-line status of each background job that has moved since the
last card. Use the same emoji there: ✅ done or armed, ⏳ running, ❌ blocked.

## Step 4: Run the prep

First pick the route for each PR. Post-plan's own fix round fixes open review threads,
re-anchors a plan mutation row whose code moved, and strengthens the test for a row that
did not trip. It merges master too. A prep job that fixes those chores and then
re-fires post-plan does the same work twice.

- **Re-fire post-plan only.** The chores are open review findings, a stale verdict, a
  `BEHIND` branch, or a failed post-plan run. Run the Step 6 live-run check, then
  `(cd <abs worktree path> && bin/post-plan-now --auto)`. Count it as a round.
- **Prep job.** Any chore post-plan can't do: a `DIRTY` conflict, a snapshot or golden
  fixture to regenerate, a promised test the plan names, or a finding post-plan's fix
  round already tried and left open.

### Rebase only what the merge line can't fix

Master takes a PR only when it is up to date with master. So when several approved PRs
get rebased at once, one merges and the rest fall behind again. Their rebases and CI runs
were wasted. The merge line already handles `BEHIND`. On every master push,
`.github/workflows/update-behind-prs.yml` (`bin/merge-line-watch` locally) asks
`bin/merge-line-select` for the oldest armed PR with no conflict and no red required
check, and updates only that one (ADR-0081). Each armed PR gets updated once, when its
turn comes.

- **`BEHIND` with no conflict.** Don't rebase it. List no rebase in the job's chores. Once
  the PR is armed, the merge line keeps it current. A Ship pick needs only
  `bin/owner-approve`. Run it in this session, with no job.
- **`DIRTY`.** Rebase it in the job. The merge line skips conflicted PRs, so nothing else
  will.
- **Two approved PRs that clash with each other.** Rebase and ship the higher-ranked one
  first. Hold the other's rebase until the first merges, then launch its job. Rebasing
  both now leaves the second conflicted again.
- **Arm order.** The merge line goes by the time each PR was armed, oldest first, with
  `hotfix` PRs ahead. Arm approved PRs in the Step 2 rank order, so they merge in that
  order.

Post-plan still merges master on each run. Firing several at once still costs one CI run
each before the line takes over. The rules above stop the skill from adding a second one.

Count rounds per PR in this run. A round is one prep job or one re-fire. Stop after 2
rounds whose post-plan verdict still holds on a new finding. Don't start a third. Move the
PR to Decide now with a card that offers: close it, redesign the approach, or ship it with
the open findings filed to the backlog. Count rounds, not bug classes. The reviewer gives
each new edge case its own class name, so the same weak approach never repeats a class.
On #3714, #3715, #3742, and #3760, round 3 and later each turned up one more edge case.

Launch a prep job for every PR on the prep-job route. Use the Step 6 job template, with
step 3 listing the chores (rebase, the snapshots to regenerate, each missing test with its
matrix row and the mutation it must catch, each review finding to fix) and step 7 set to:

```text
7. Prep only, no owner decision. Do NOT add labels, tick boxes, sign off, or arm.
   Comment on the PR listing the chores done.
```

Run at most 4 jobs at once. Each one is a full Opus session, and more worktree stacks
starve Docker. Queue the rest in `$D/jobs/queue.txt` and start the next when a `.result`
lands.

Run a read-only chore yourself when it is one command, such as a replay script or a query
the verdict's note asks for. Post its output as a PR comment. A job costs a full Opus
session. On #3789 the open note was a replay over 30 real PRs, and one inline run closed it.

Hand each job's follow-up off as Step 6 § Follow-ups says. A Prep-only PR is done once
post-plan arms it. A Prep-then-sign-off PR gets its card in the next batch, built from
the new verdict.

Never invent an owner pick for a prep job. If a chore needs a judgment the plan does not
settle, the job stops and comments, and the PR moves to Decide now.

## Step 5: Help the owner look

When the decision needs eyes:

- **Live page:** bring up the PR worktree stack with `bin/wt-stack start <slug> --seed`, take the screenshot, then run `bin/wt-stack stop <slug>` before the next step. Drop
  `--seed` for prod data only when `.env` has the `REMOTE_*` credentials. Open
  `http://<slug>.localhost/ibl5/...` with the `preview_*` tools and embed the screenshot.
  For before and after, swap the old CSS value in with `preview_evaluate` and shoot again.
  If the page 502s while php returns 200, restart `ibl5-traefik`. If the DB is behind,
  run the migrations in the worktree.
- **Design choices:** have a background agent build a self-contained HTML gallery of
  options using real data (for example team colors from `ibl_team_info`). Save it under
  `~/claude-plans/_reports/` and show it with `html_render`. Name a pick and a runner-up.
- **Slow previews:** many worktree stacks starve Docker of memory and freeze the preview.
  Count running stacks with `docker ps`. Offer to stop stacks no job is using.
- **Try the flow yourself first.** Before you send the owner to try a flow, run it on that
  stack. If it can't work there, because the stack lacks credentials (Google OAuth keys, a
  mail or payment provider) or prod-only data, say so on the card. Name where it can run
  and what the owner must set up first.

## Step 6: Carry out the pick

Sub-agents cannot push. Run each decision as a headless session. Write the prompt to
`$D/jobs/pN.txt` with the Write tool. Replace `$D`, `N`, and `pN` with literal values
before you write it, because nothing expands them for the child session:

```text
Owner decision on PR #N (branch <branch>), <date>: "<owner's words>". <What it covers.>
1. Get the PR worktree (bin/pr-wt-resolve N prints its path; bin/wt-new if none). Work only there.
   First check for a live post-plan run: launchctl list | grep -E 'com\.ibl5\.(postplan-now-<branch>-|post-plan-fleet-N$)'.
   Use the flattened branch slug for <branch> (post-plan-now flattens it): `printf '%s' "<branch>" | tr -cs 'A-Za-z0-9._-' '-'`. A branch with `/` never matches raw.
   If one is listed, do not touch the worktree. Write "blocked: post-plan run live" to $D/jobs/pN.result and stop.
2. <Only when the PR is DIRTY:> Rebase onto origin/master (or merge it). Expected conflicts: <files>. Keep both sides' intent.
   <When it is only BEHIND, write instead:> Do not rebase or merge master. The merge line updates the branch once the PR is armed.
3. <The change the owner asked for, with the exact behavior. A bug fix gets a test that fails without it.>
   For each review thread you fix, also fix every site its "other instances" line names.
   Resolve a thread only after a commit changes the code it points at, or after the owner
   defers it: then reply "Deferred to a-jay85/IBL5-backlog#M" and resolve it.
4. Run the same checks post-plan runs before it fires, in the worktree, one call each:
   bin/lib/prefire-verify --plan <plan path> --stage matrix, then --stage phpunit, then
   --stage exec-mutations. Drop --plan when the PR has no plan
   (ls ~/claude-plans/<branch>.md). Exit 3 means Docker or the DB is down: run
   `bin/wt-stack start <slug>`, retry, then run `bin/wt-stack stop <slug>` once the query returns. Also run composer run analyse in the worktree's Docker when
   PHP changed, and <any extra tests this change needs>. Fix what broke.
   Do not push until every stage exits 0. A pass is cached by tree hash, so the parent's
   re-fire reuses it. If a failure is outside this PR's diff and also fails on master,
   write that in the .result and stop.
5. Push (force-with-lease after a rebase).
6. Do NOT run post-plan or /pr-ready. This session is headless and bin/post-plan-now refuses
   to start here. The parent session re-fires post-plan after you finish.
7. <One of:>
   - Ship: tick the manual boxes the owner confirmed. After the last push in step 5, the parent session runs `bin/owner-approve N --finish` with `run_in_background: true`. The script checks the pushed head, runs `bin/lib/prefire-verify` in the worktree, and arms only on a READY verdict for that head's tree.
     Master refuses any merge while a review thread is open (ci-gotchas.md § Conversation
     resolution). Before you report the PR as shipping, list its open threads. File each
     finding the owner deferred to the backlog, reply "Deferred to
     a-jay85/IBL5-backlog#M", and resolve the thread. Name any thread still open in the
     .result.
   - Come back: save before/after screenshots at 1280 and 390 to $D/jobs/pN-*.png,
     and do NOT sign off or arm.
   If anything blocks, do not merge. Comment on the PR saying what blocks.
Write a 5-line outcome to $D/jobs/pN.result: head SHA and CI, what changed, what blocks, live URLs.
Then stop.
```

Launch it from the main checkout:

```bash
nohup env CLAUDE_HEADLESS=1 claude -p "$(cat "$D/jobs/pN.txt")" \
  --dangerously-skip-permissions --model claude-opus-5-5 > "$D/jobs/pN.log" 2>&1 &
```

Then:

- Call `link_pull_request` with the PR URL.
- For a hold, leave a PR comment with the owner's reason so the next run sees it.
- Hand the follow-up off (§ Follow-ups below). The finished `.result` file is the
  completion signal. Never poll on the main thread.
- Bring up the next card right away.
- Before any git operation in a PR's worktree, or before re-firing post-plan, check for a
  live run: `launchctl list | grep -E 'com\.ibl5\.(postplan-now-<branch>-|post-plan-fleet-<N>$)'`.
  Use the flattened branch slug for `<branch>` (post-plan-now flattens it): `printf '%s' "<branch>" | tr -cs 'A-Za-z0-9._-' '-'`. A branch with `/` never matches raw.
  If one is listed, don't touch the worktree. Wait for it to end.

When a `.result` lands, read it and report it in one line at the top of the next reply. If
the owner's request changes a PR that other PRs copy (for example a shared style used by
four hub PRs), make the change once in its own commit and copy that commit to the others.

### Follow-ups: keep this session asleep

A Monitor here wakes this session with its whole context: every PR dump, the plan
excerpts, this skill. Inside the one-hour cache window a wake is cheap. After it, a wake
pays full price for everything. So hand each job's follow-up to the cheapest carrier that
can do it, and stop any watcher this session started (`TaskStop`).

- **Shell watcher, zero tokens.** Use it when the follow-up needs no judgment: wait for
  the `.result`, then re-fire post-plan. Post-plan arms a Prep-only PR itself and DMs the
  owner. Write `$D/jobs/wN.sh` and start it with `nohup bash $D/jobs/wN.sh >/dev/null 2>&1 &`.
  The script:
  1. Waits until `$D/jobs/pN.result` is non-empty or the job's pid is gone.
  2. Fires only when the PR head moved off the pre-job SHA and the result does not start
     with `blocked`. The job pushes only after every check passes, so a moved head is the
     pass signal. Never grep the result for words like "stop" or "blocks", because a clean
     result says "blocks: none".
  3. Runs the live-run check, then `(cd <wt> && env -u CLAUDE_HEADLESS bin/post-plan-now --auto)`.
  4. Logs each skip to `$D/jobs/wN.out`. A skip sends no DM. The job's PR comment says why.
  5. DMs the owner when `post-plan-now` exits non-zero:
     `<wt>/bin/discord-dm "PR #N: post-plan-now exited $rc. See $D/jobs/wN.out"`.
     Exit 9 (a pre-fire check refused) and exit 10 (the check could not run) stop before
     post-plan starts. They print to stdout and send no DM, so without this step the
     watcher fails silently.
- **New T3 thread.** Use it when the follow-up needs judgment, such as reading a new
  verdict on a PR the owner has not approved yet, or building a sign-off card. Call
  `t3_thread_launch` with a title like "PR #N: <goal>" and a `clientRequestId` like
  `prb-<run>-N`. The brief holds only what that PR needs: the goal, the owner's words and
  date, why it's held, the `.result` path and job pid, and numbered steps. The steps: wait
  with its own background Monitor, re-fire post-plan, wait for its `RESULT:` line, then
  run `bin/owner-approve N --finish` or build the card. For a card, the brief says to run `/pr-burndown N` from Step 3.
  Name each thread by its `link` in your reply.
- **This session.** Use it only while the owner is still answering cards and the job will
  land inside the hour.

A PR whose post-plan run is already live and has no owner hold gets nothing. Post-plan's
DM is its completion signal. That DM exists only once post-plan starts. A refused pre-fire
check exits before then, so whoever fires `post-plan-now` reports a non-zero exit itself.

Post-plan holds condition 7 when the plan says `auto_merge: false`. The hold lasts until
`bin/owner-approve` adds `human-approved` to the PR and the `human-signoff` workflow has recorded the diff
fingerprint. A PR the owner approved in chat is already armed by then, so it merges once
`human-signoff` passes. A push that changes the PR's changed lines drops the label, and
the hold comes back. The PR stays armed but can't merge until someone adds the label again.

## Step 7: Wrap up

When the owner stops or the list ends, list:

- PRs merged or armed. An armed PR with an open review thread stays blocked, so list it
  with its open threads.
- PRs prepped and armed with no owner call
- PRs still running, with their `.result` paths and who picks each one up (a watcher log
  or a thread link)
- PRs waiting on the owner, and the decision each needs
- PRs held, and why
