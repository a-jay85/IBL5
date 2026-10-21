---
description: The league rules the site must enforce, owned by the league owner. Claude checks changes against this list; the weekly digest flags PRs that seem to change a rule.
last_verified: 2026-10-21
---

# League Rules

The owner owns this file. Code and agents follow it. When the code and this file disagree, the code has a bug or this file is out of date. Ask the owner which one. Never quietly change either side to match.

Each rule names the class that enforces it, so you can check it. Money is in thousands: 7000 means $7,000,000 in league terms.

The weekly comprehension digest (`bin/automouse/comprehension-digest`) reads this file. It marks any merged PR that seems to change a rule here with `RULE CHANGE?`.

## Season calendar

- The season has six phases: Preseason, HEAT, Regular Season, Playoffs, Draft, Free Agency. An admin sets the phase by hand in the League Control Panel.
- The regular season is 82 games. It starts Nov 1. The All-Star break is Feb 1 to 4. Playoffs run Jun 1 to 30. (`Season\Season`, `League\League`)
- Trades use next season's salary during Playoffs, Draft and Free Agency. (`Season\Season::advancesContractYears()`)
- Waivers and the cap page use next season's salary only in Draft and Free Agency. Teams still sign waiver players during the Playoffs, so waivers stay on the current season until then.
- An admin sets the sim length by hand in the League Control Panel. It has changed over the league's history. It is 7 days right now. Preseason sims run up to 31 days. JSB can sim a whole season at once, but the league never does.
- Season rollover only moves forward one year at a time. Any other jump halts without writing. (`Updater\SeasonRollover\SeasonRolloverDecision`)

## Teams and rosters

- There are 28 teams in 2 conferences and 4 divisions. The map lives in `ibl_league_config`.
- A roster holds at most 15 players. Injured players count toward the 15. JSB forces a waiver when a 16th player is signed. (`Team\Team::ROSTER_SPOTS_MAX`)
- A player on waivers has an ordinal above 960. Waived players do not take a roster spot.
- Outside the playoffs, a depth chart has exactly 12 active players, with at least 3 healthy players at each position. In the playoffs it has 10 to 12 active, with at least 2 per position. (`DepthChart\DepthChartValidator`)
- A depth chart must list every rostered player once and nobody else.

## Salary cap

- The soft cap is 5000. The hard cap is 7000. Both are code constants. (`League\League`)
- There is no luxury tax.
- Cash sent in a trade counts against the sender's cap. Cash received comes off the receiver's cap.
- Buyouts are capped at 40% of the hard cap (2800).

## Contracts

Full tables live in `.claude/rules/contract-rules.md`. The rules:

- Contracts run 1 to 6 years. Salary never goes down from one year to the next. Once a year is 0, every later year is 0.
- Raises are capped at 10% of year 1 per year, or 12.5% with Bird Rights. Bird Rights take 3 or more straight years with one team. (`League\ContractRules`)
- Year 1 must be at least the veteran minimum for the player's experience (35 to 103) and at most the experience maximum (1063, 1275 or 1451).
- The MLE pays 450 in year 1, rising to 675 in year 6. The LLE is one year at 145. Each team gets one MLE and one LLE per season.
- Draft picks sign rookie deals: 3 years in round 1, 2 years in round 2.
- A rookie option doubles the final-year salary. It adds year 4 for round 1 picks and year 3 for round 2 picks. Teams can exercise it only in Free Agency, Preseason or HEAT, and only at the experience level set for the round. (`Player\Contract\PlayerContractValidator`)
- An extension adds at most 5 years. JSB holds 6 salary years: the current year plus 5. Free agency contracts can run 6 years, so the validator shared with free agency (`FreeAgency\CommonContractValidator`) accepts a sixth year. The extension form only sends 5.
- Extensions are allowed in Preseason, Regular Season and Playoffs. A team gets one per season and one try per sim. A rejected offer still uses the try. (`Extension\ExtensionValidator`)

## Trades

- Teams can trade players, draft picks and cash. Trades can include more than two teams.
- Trades are never allowed in the Playoffs. They are always allowed in Draft, Free Agency, Preseason and HEAT. In the Regular Season, the "Allow Trades" setting decides. (`Season\Season::areTradesAllowed()`)
- After a trade, every team must be at or under the hard cap and at or under 15 players. Injured players count toward the 15. (`Trading\TradeValidator`)
- Any cash amount in a season must be at least 100. Cash can cover up to 6 years.
- Picks have no cap or roster effect.
- The team receiving an offer accepts or rejects it. Cap and roster checks run again at accept time, using database values.
- There is no trade deadline beyond the phase rule.

## Waivers

- Waivers are always open in HEAT, Regular Season and Playoffs, and always closed in the Draft. In Preseason and Free Agency, the "Allow Waiver Moves" setting decides. (`Season\Season::areWaiversAllowed()`)
- A team can only waive its own players. A team over the hard cap with 12 or fewer players cannot waive anyone.
- Signing off waivers or free agency needs an open roster spot. Injured players count toward the 15. A team with 12 or more healthy players cannot sign past the hard cap. A team with fewer than 12 may go over, but only for a veteran-minimum player.
- The veteran minimum in that exception is the minimum for the player's own experience, the same as in free agency.
- A player with no contract signs for one year at the veteran minimum. A signing resets the player's Bird years to 0.
- Signing is first come, first served. There is no claim order and no waiting period.

## Free agency

- Only a logged-in GM of a real team can bid. A player who already signed this period cannot get new bids.
- Bird Rights apply only when the bidding team is the player's current team.
- A custom offer from a team without Bird Rights must fit under the soft cap in year 1. MLE, LLE and veteran-minimum offers skip that check. Every offer must fit within soft-cap space plus 2000.
- A team can hold only one pending MLE offer and one pending LLE offer at a time.
- An admin runs one batch per day to decide winners. Each offer gets a perceived value: the yearly average, times a mood factor (winning, tradition, loyalty, security, playing time), times a random 95% to 105%.
- The player's asking price drops 10% per day. The best offer wins only if its value beats the day's asking price. An offer worth half the asking price or less is rejected on the spot. (`FreeAgency\Admin\FreeAgencyAdminProcessor`)

## Draft

- Only the team on the clock can pick. The pick on the clock is the first empty slot, and only the GM whose team owns that pick can submit it. A pick can only take an undrafted player. (`Draft\DraftController`)
- The lottery covers picks 1 to 12. The admin enters the lottery result; the site does not draw it.
- Round 1 order: the lottery picks first, then the other playoff teams worst first, then division winners, then conference winners. Round 2 is all teams worst first. (`ProjectedDraftOrder\ProjectedDraftOrderService`)
- Draft class uploads are admin-only. A file with any bad row is rejected whole.

## Standings and playoffs

- Standings come from played games in `ibl_schedule`. Box scores do not feed standings.
- Each conference sends 8 teams to the playoffs: the division winners plus the 6 best other teams.
- Tiebreakers, in order: win percentage, head-to-head, division winner, division record, conference record, point differential, lowest team ID.
- The conference champion is the team that wins playoff round 3.

## Stats and data

- The Updater is the only way data enters the site. It is admin-only. It runs a fixed list of steps. A step that fails is logged, and the rest still run.
- Box scores come from the `.sco` file. Each (date, visitor, home) game is unique; there are no doubleheaders. A game missing from the schedule is rejected.
- Standings, head-to-head records, playoff results and season records are all rebuilt from the schedule and box scores. `ibl_hist` and `ibl_plr_snapshots` are the only independent records.

## Voting and awards

- Each GM casts one end-of-year ballot and one All-Star ballot while voting is open.
- End-of-year ballot: 3 ranked picks each for MVP, 6th Man, Rookie of the Year and GM of the Year. Blanks and repeats are rejected. A GM cannot vote for their own players or for themselves.
- All-Star ballot: exactly 4 picks in each of the four conference and court groups, and none of your own players.
- Season awards can be generated only during the Playoffs or the Draft.

## Who can do what

- A GM acts only for their own team. The site reads the team from the login session and ignores any team the form sends.
- Every form that changes data carries a CSRF token.
- The League Control Panel and the Updater are admin-only.

## Known bugs

The owner ruled on these on 2026-10-20. The rules above are correct. The code still needs a fix.

- **The 24-hour waiver wait.** The waiver page shows a 24-hour wait that the code never enforces. Remove it from the page.
