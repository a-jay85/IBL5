---
name: pr-burndown
description: "Burn down the open PRs that wait on the owner. Background headless jobs do the prep that needs no owner call (rebase, regenerate snapshots, write promised tests, fix review findings, re-fire post-plan). The owner sees only the decisions that are theirs: one scoreboard page with a status row per PR (tests, comments, bot review, easy undo, your OK, your call). Each row opens a full card with a recommended option, and the owner answers in a batch."
last_verified: 2026-11-27
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
- When the owner approves a PR for merge in chat, run `bin/owner-approve N "<words>" --detach` right away as a foreground call. It returns at once with a log path. A launchd job then waits for a live post-plan run to end and arms only when the verdict covers the current head. When a job will push to the PR (a rebase, or a fix the owner asked for), pass `--fix-first="<what the job pushes>"` (no `--detach`) and run `--finish --detach` after the push. A plain approval carries to a new head when the PR's own changes are unchanged (a rebase, or post-plan merging master). It refuses only when the PR's own lines changed.
- Read the card's Owner approvals field before you recommend anything. An approval the owner already gave stays given.
- Plain approval (`approve`): run `bin/owner-approve N --finish --detach`.
- Fix-first approval, PR head unchanged: the fix has not landed. Launch the fix job the `condition:` line names.
- Fix-first approval, PR head moved past it: run `bin/owner-approve N --finish --detach`.
- A fix-first line that reads `amendment: MISSING` comes first. Run `bin/owner-approve N --record-amendments` before the fix job or the finish, so the reviewer sees the scope.
- No card may recommend undoing or reverting work an approval's Condition asked for. A kept bullet that says revert does not override the Condition.
- Send no sign-off card for an approval the owner already gave. Run each finish as a foreground `bin/owner-approve N --finish --detach`. The job arms when the PR's diff still matches the approved one. Card the PR again only when the job refuses because the PR's own lines changed, and say on the card what changed since the approval.
- When a PR is NOT READY and its only blockers sit in the PR body's kept-unplanned block, offer the owner `bin/owner-approve N "<words>" --detach` as the path. The approval accepts those listed items for the current diff and re-runs post-plan once. Never suggest a hand merge over the verdict.
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
bin/pr-triage | awk '$2=="OPEN-THREAD" && $3=="armed,"' > "$D/stalled.txt"
```

`bin/pr-triage --held` lists each held PR with every hold reason. It skips a PR with
auto-merge on. `stalled.txt` lists those PRs when GitHub won't merge them because a
review thread is still open. Step 2 § Stalled armed PRs says what to do with each.

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

### Stalled armed PRs

Each PR in `stalled.txt` has auto-merge on, but master won't take it while a review
thread is open. Run `bin/pr-triage --card N` and check each open thread:

- **Already fixed.** A later commit fixed the thread. Reply on the thread naming the
  commit, then resolve it. This needs no owner call.
- **No owner hold.** Post-plan armed the PR and the owner holds nothing on it. Put it in
  Prep only and re-fire post-plan. Post-plan's fix round fixes open threads.
- **Owner approved, thread unfixed.** Put it in Decide now. A fix changes the PR's own
  lines, so the earlier approval no longer covers them. Offer:
  1. **Fix it.** Run `gh pr merge N --disable-auto` first, so the PR can't merge before
     the owner looks again. A Step 6 job fixes each thread, and the PR comes back for a
     fresh OK.
  2. **Defer to the backlog.** File an issue in `a-jay85/IBL5-backlog`, reply
     "Deferred to a-jay85/IBL5-backlog#M" on the thread, and resolve it. The PR then
     merges on the earlier approval.
  3. **Hold.**

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

- **Head:** the merge state, labels, and auto-merge. The page never shows the SHA.
- **CI:** real failures, split from the gate checks that only wait on the owner
  (`human-signoff`, `needs-a-human label`, `auto-mergeable label`).
- **Review threads:** the open threads that are not outdated, each with its first line.
- **post-plan verdict:** the last `<!-- pr-ready-verdict -->` comment, marked `CURRENT`
  or `STALE` against the head. Commits after the review make it stale.
- **Door and blast radius:** the `Merge danger:` line in the PR body.
- **Fidelity evidence.** When the card data has a `fidelity-evidence:` row, the page prints its status words with the SHAs removed. Post-plan writes it for a PR that edits `tools/postplan-harness/harness/fidelity.py`. It compares stored verdicts only and does not change the hold. The page keeps STALE, missing, malformed, unknown and NOT RUN statuses in words.
- **Visual links:** the `a-jay85.github.io/IBL5/...` URLs from the comments.
- **Live run and worktree:** any live post-plan label, and the PR's worktree path.
- **Owner approvals:** each `bin/owner-approve` comment, oldest first. Each row gives the mode, the approved head and whether the PR head moved past it, then the owner's words, the `condition:` line, any `kept:` bullets and, for a fix-first approval, `amendment: recorded`, `MISSING` or `no plan file`.

A field that prints `unknown (fetch failed)` gets ❌ on the page, and its row stays visible. Never fill it from an
earlier fetch. Read the thread bodies or the full verdict only when the card needs more
than the first line.

Check one more thing yourself:

- **Does it do the job where it runs?** Read the diff against how the code deploys. For
  example, a Docker-only change does nothing on prod, which deploys by `git pull` onto a
  plain server. Put it in the card's `risks` when the PR misses its own goal.

Show the cards as one page. `bin/pr-burndown-gadget` builds it from live `bin/pr-triage --card` and `bin/pr-triage --held` data, so the layout is the same every run. It fetches each card again as it builds the page. You write only the judgment: the plain title, your call, short notes for red cells, the card text, the options and your pick.

Write the judgment as JSON with one entry per PR you looked at this run, in rank order:

```json
{"version": 1, "prs": [
  {"number": 4244, "owner_call": true,
   "title": "<plain title a GM would follow>",
   "why_next": "<one line: why this PR is next>",
   "call": "<the one decision, in a few plain words>",
   "notes": {"bot_review": "<2 or 3 words>", "easy_undo": "<what can't be undone>"},
   "does": ["<GM-visible effect>", "<who notices it>"],
   "undo": "<what a revert does and doesn't undo>",
   "risks": [{"risk": "<risk or open question>", "evidence": "<the live fact behind it>"}],
   "options": [{"label": "Ship it", "does": "<what you'll do>"},
               {"label": "Hold", "does": "<what happens then>"}],
   "recommend": {"option": 1, "why": "<one or two lines>"}},
  {"number": 4250, "owner_call": false}
]}
```

- Set `owner_call` to true when the owner has a decision on the PR (the Decide-now pile). Set it to false when the bot is handling the PR: a prep job, a live post-plan run, or a `bin/owner-approve N --finish`.
- A merge conflict that needs the owner's call takes `owner_call: true`, `"conflict_needs_you": true` and an option for it. Leave the field out when the bot will rebase.
- With `owner_call: true`, every field above is required except `why_next`, `notes` and `risks`. The `notes` keys are `tests`, `comments`, `bot_review`, `easy_undo` and `your_ok`, at most four words each. A note shows only on a ❌ or 🚨 cell.
- Write no hold codes such as "(7)". The script refuses them and strips any SHA.

Run `bin/pr-burndown-gadget --judgment=FILE --out=FILE` in one block:

```bash
D="${TMPDIR:-/tmp}/pr-burndown-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$D"
cat > "$D/judgment.json" <<'JSON'
<the judgment JSON>
JSON
bin/pr-burndown-gadget \
  --judgment="$D/judgment.json" --out="$D/scoreboard.html"
```

It prints `wrote <path> shown=K hidden=H fetch_failed=F`. Exit 2 means the judgment broke a rule, and the message names the field. Fix that field and run it again. Show the file with the `html_render` tool (load it with ToolSearch). When `html_render` is not available, run the same command with `--format=markdown --out="$D/scoreboard.md"` and paste the file into your reply. The markdown table has the same columns.

The page has one row per PR that needs the owner. Its columns, in this order, are PR, Tests, Comments, Bot review, Easy undo, Your OK, Your call. Each status cell holds one of four icons: ✅ fine, ⏳ running or waiting, ❌ broken, 🚨 risky. A ✅ or ⏳ cell holds the icon alone. A ❌ or 🚨 cell adds two or three red words. Easy undo is ✅ for a two-way door and 🚨 for a one-way door. A field that fetched as `unknown (fetch failed)` shows ❌ with "Could not read", and its row stays on the page. Rows with `owner_call: false` are hidden, and one line under the table counts them. A held PR missing from the judgment still gets a row. A branch that is only behind master shows nothing. A real conflict that needs your call shows "Conflicts with master" in red in Your call.

Each row opens its full card: what the PR does, what undo means, each risk with its evidence, the live CI, comments, review verdict, fidelity evidence, visual links and owner approvals, the hold reasons in plain words, the numbered options and your 💡 pick. The card has the full shape the bullets at the top of this step ask for, so each card stands alone.

Your reply carries only what the page does not show: the one-line status, any fetch failure the script printed, and the line "Answer with one line per PR, like `4244: 1`." Each answer names its PR, so one message can answer several cards. When the owner writes `N: k`, act on option k for PR N. When N is not on the page or k is not one of its options, ask again about that PR alone. A PR on the page with no answer stays open for the next run.

Before you recommend an option, read the plan's Architectural trade-offs and "Must
preserve" sections (`ls ~/claude-plans/<branch>.md`). If the plan already rejected the
approach an option takes, say so on that option and don't recommend it. On #3760 a
recommended `workflow_run` retrigger that the plan had rejected cost a full round.

The script picks every icon from the live card data by fixed rules. You never pick an icon or write one into the judgment.

A branch that is only behind master shows nothing on the page, and it is not a hold reason. The merge line updates it once the PR is armed. Step 6 rebases a `DIRTY` PR, so the owner's pick already covers it. Neither state makes a "fix conflicts first" option or a recommendation to wait. When the clash is with another approved PR in this run, add a `risks` entry saying this PR rebases after that one merges. The one exception is a conflict that needs the owner's call to resolve, such as two PRs that change the same rule in opposite ways. Set `conflict_needs_you` and put the call in the options.

Start every reply with a one-line status of each background job that has moved since the
last card. Use these icons there: ✅ done or armed, ⏳ running, ❌ blocked.

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

- **`BEHIND` with no conflict, and no job.** Don't rebase it. Once the PR is armed, the
  merge line keeps it current. A Ship pick needs only `bin/owner-approve N "<words>" --detach`. Run it in this
  session, with no job. When a job will push to a `BEHIND` PR anyway, the job rebases it
  (the Step 6 brief, step 2), because `bin/pre-push-adr-hook` refuses a push that lacks
  origin/master.
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

Each job is a full Opus session with its own worktree stack, and too many stacks starve
Docker of memory. Read the Docker memory cap first:
`docker info --format '{{.MemTotal}}' | awk '{print int($1/1e9)}'` prints it in GB.
Under 9 GB, run at most 4 jobs at once. From 9 GB, run at most 6. From 13 GB, run at
most 8. Docker reports a little less than its setting, so a 14 GB setting prints 14 and
a 6 GB setting prints 6. A busy stack (PHPUnit or a browser check) uses about 1 to 2
GB, and the main stack holds about 1 GB. Queue the rest in `$D/jobs/queue.txt` and start
the next when a `.result` lands.

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
   <When it is only BEHIND, write instead:> Rebase it too: `git fetch origin master && git rebase --onto origin/master "$(git merge-base HEAD origin/master)"`.
   bin/pre-push-adr-hook refuses to push a branch that lacks origin/master, and ~/.claude/hooks/bash-guard.sh denies a local merge of master.
   If the rebase conflicts, run `git rebase --abort`, write "blocked: rebase conflict with master" to $D/jobs/pN.result, and stop.
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
   - Ship: tick the manual boxes the owner confirmed. The parent session records the approval with `bin/owner-approve N "<words>" --fix-first="<what this job pushes>"` before the job starts. A plain approval refuses once the job changes the PR's own lines. After the last push in step 5, the watcher started with `--then-finish` (§ Follow-ups) runs `--finish --detach` itself. With no watcher, the parent session runs `bin/owner-approve N --finish --detach` as a foreground call. The script checks that the head moved past the recorded one, waits out any live post-plan run, runs `bin/lib/prefire-verify` in the worktree, then arms only on a READY verdict for the pushed head's tree.
     A plain approval is for a pick with no push.
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

- **Shell watcher, zero tokens.** The default. Use it when the follow-up is "wait for the
  `.result`, then re-fire post-plan". That holds even when the step after post-plan is a
  sign-off card or another owner call. Post-plan DMs the owner when it ends, and the next
  `/pr-burndown` run builds the card when the owner sits down. Waking a session only
  builds the card early. Post-plan arms a Prep-only PR itself. Start it with one call,
  using the head SHA from before the job:

  ```bash
  bin/pr-refire-after-job N --pid <job pid> --result $D/jobs/pN.result --pre-sha <sha>
  # a PR with a fix-first approval adds --then-finish
  bin/pr-refire-after-job N --pid <job pid> --result $D/jobs/pN.result --pre-sha <sha> --then-finish
  ```

  Run one of the two. With `--then-finish` the job runs `--finish --detach` itself after a
  re-fire that exits 0. A skipped re-fire leaves the finish to the next `/pr-burndown` run.
  The script runs as launchd job `com.ibl5.pr-refire-N`, logs to `$D/jobs/wN.out`, and fires only when the head moved and the
  result does not start with `blocked`. It DMs the owner on every skip but a live
  post-plan run, and on a non-zero `post-plan-now` exit. The script header lists each
  case. Never hand-write a waiter or wait with Monitor here.
  `~/.claude/hooks/wait-guard.py` blocks a Monitor or background Bash wait on a
  `$D/jobs/` file.
- **The next `/pr-burndown` run.** Use it when the follow-up needs judgment and no owner
  call, such as reading a new verdict to decide between a re-fire and a fix job. Start the shell watcher
  above for the re-fire. For a fix-first approval pass `--then-finish`, and the job runs
  `--finish --detach` itself. The next run reads the new verdict and runs `--finish --detach`
  on any open PR with an approval marker (§ Rules). Never launch a new T3 thread for a follow-up. The
  owner turned that off on 2026-11-23.
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
  with its open threads. Re-run the `stalled.txt` line from Step 1 and list every PR it
  prints, including ones this run never touched.
- PRs prepped and armed with no owner call
- PRs still running, with their `.result` paths and who picks each one up (a watcher log
  or the next `/pr-burndown` run)
- PRs waiting on the owner, and the decision each needs
- PRs held, and why
