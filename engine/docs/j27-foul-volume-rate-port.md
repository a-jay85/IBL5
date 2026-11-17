---
description: J27 per-type foul rate port. Scales the foul-only bucket by 2.10 and the raw half-court and-one basis by 0.053 toward the JSB live-trace rates, maps the two and-one FT call sites, keeps the offensive foul residual, and lists the residuals. FTA/g moves from 11.186 to 18.741 against sco 18.587.
last_verified: 2026-11-17
---

# J27: per-type foul rate port

The live-outcome-map port removed both scales below. Its write-up is `engine/docs/j27-live-outcome-map-port.md`. This page stays as history.

This doc records the retune of the engine's foul rates by foul type toward the rates the live JSB trace measured (`engine/docs/j27-foul-volume-3-port.md`). It closes the FTA gap of a-jay85/IBL5-backlog#265 to within 1%. The decompile literals stay as they are: `shootingFoulDrawCut`, `andOneMadeTwoCut`, `teamFoulBonusThreshold`, and `threePtFoulCut`.

The engine now tags two more foul sites so the walk can count them apart:

- `FoulSiteAndOneMadeTwo` marks the and-one that the 5% made-2 roll fires (`andOneMadeTwoFires`, `engine/internal/sim/possession.go` and `engine/internal/sim/transition.go`).
- `FoulSiteNonShootingBonus` marks a non-shooting foul that reaches the team-foul bonus and shoots 2 FTs. A non-shooting foul below the bonus keeps `FoulSiteNonShooting` and shoots none.

`resolveFoulAt` in `engine/internal/sim/teamfoul.go` takes the tag. The tags carry `json:"-"`, and the split draws no extra random number, so the tag change alone left golden.json byte-identical.

## Targets and result

Master base db1f206cd54f4d28e8778e4315c898ca3dc1fc93. The walk command, run from `engine/`, was `JSB_ARCHIVE_DIR=<archive dir> go test -tags archive -run TestRealArchive_FTADecomp -count=1 -v ./internal/calibrate/`. It used the built-in settings: seed 20240601, stride 1, games cap 60, over 113 snapshots. The AFTER walk charged 214938 fouls at 16.180 PF per team-game, which is about 13,284 team-games.

PF per team-game by type:

| Type | JSB trace | Engine BEFORE | Engine AFTER |
|------|-----------|---------------|--------------|
| shooting-two | 6.29 | 3.220 | 6.230 |
| shooting-three | 0.10 | 0.107 | 0.090 |
| and-one (bucket) | 0.39 | 1.783 | 1.415 |
| and-one-made-two | 1.86 | 2.151 | 2.090 |
| non-shooting (below bonus) | 5.06 | 2.946 | 5.092 |
| non-shooting-bonus | 1.20 | 0.242 | 1.244 |
| late-game | none traced | 0.024 | 0.020 |
| offensive foul | 1.31 | no host | no host |
| total PF/g | sco 17.116 | 10.473 | 16.180 |

| Row | BEFORE | AFTER | sco |
|-----|--------|-------|-----|
| FTA/g | 11.186 | 18.741 | 18.587 |
| FTA per PF | 1.0681 | 1.1583 | 1.0859 |
| FTA gap % | -39.82% | +0.83% | 0% |
| home/away FTA ratio | 1.082 | 0.958 | 1.101 |

The BEFORE column is the walk recorded in `engine/docs/j27-foul-volume-3-port.md`. The AFTER walk printed the full per-site sum, and it matched engine PF/g to 0.001.

## Site mapping

JSB's foul handler in FUN_004d8570 (`jsb-native/jsb_560/decompiled/jsb560_decompiled.c` 93646-93700) has two FT call sites for an and-one-flagged foul. I read both in the 5.60 binary with `objdump -d`:

- The handler checks the FT guard at `0x4da211` (`cmpl $5`), then calls the make roll FUN_004e2220 at `0x4da25d`.
- On a make (FGM and FGA both counted, `0x6300` set to 1) with `local_150 == 1` (test at `0x4da32b`), it calls the FT routine at `0x4da35a`.
- On a miss (FGA only, branch at `0x4da3a3`, `0x6300` set to 0) with `local_150 == 1` (test at `0x4da3ea`), it calls the FT routine at `0x4da427`. That call pushes `[esp+0x64]`, which holds `local_110`, the 3pt flag.

Both sites need `local_150 == 1`. They split on whether the make roll inside the handler hits. The plan assumed both sites charge 1 FT on a made basket, and the binary contradicts that. The FT count at `0x4da427` stays open. The result of FUN_004e9fc0 feeds `0x622011`, and that address may only build play-by-play text.

The engine has no make roll inside its foul handler. Its bucket and-one always counts a made basket and 1 FT (`outcomeAndOne` in `engine/internal/sim/possession.go`). So the engine's two and-one sites together stand for both JSB sites. JSB charges 2.25 PF per team-game across them, and the engine charges 3.505.

## Knobs

Two constants in `engine/internal/sim/foulvolume.go` carry the retune. ADR-0236 treats each as a stand-in until the per-type rates come straight from the decompile.

- `foulBucketVolumeScale = 2.10` multiplies the foul-only bucket weight through `scaledFoulWeight` inside `playBuckets` (`engine/internal/sim/possession.go`). It drives shooting-two and both non-shooting rows. The half-court and transition paths both read their foul weight from `playBuckets`, so each gets the scale once.
- `andOneHalfCourtScale = 0.053` multiplies the raw half-court and-one basis. The transition path keeps `andOneBucketWeight` unscaled. The scale sits in `halfCourtAndOneWeight` (`engine/internal/sim/bucketweights.go`). The home-court term joins after the scale, and the floor `andOneBucketFloor` applies last.
- The made-2 and-one follows the fixed `andOneMadeTwoCut` literal. No knob touches it.

The plan scaled the raw basis and the home-court term together. At 0.2 that cut the and-one rate to 0.599, but `TestSimulate_HomeCourtAdvantage_Directional` fell to a home margin of +0.40 points (1.09σ against a 3σ bar), and the home/away FTA ratio fell to 0.891. The home-court term carries most of the engine's home margin, so scaling it removes the margin. I kept the term unscaled. The final constants give a home margin of +3.34 points (8.98σ).

With the home-court term unscaled, the raw basis is about 7% of the home team's and-one weight. A smaller scale barely moves the rate: 0.0146 gave 1.404 against 0.053's 1.415. I kept 0.053 so the constant stays above the floor at a basis of 1.0, which `TestHalfCourtAndOneScaleBeforeFloor` checks.

`TestFoulRatesByTypeFixture` (`engine/internal/sim/foulrate_test.go`) pins each per-type rate on the golden bundle over 200 seeded games. Its bucket and-one band is ±4%, because removing the and-one scale moves the fixture rate by about 6% (1.147 to 1.212).

## Offensive foul

The offensive foul on a turnover stays RESIDUAL. The governing record is Q1 in `engine/docs/j27-foul-volume-port.md`. JSB rolls a 0.1 charge on its outcome-4 turnover (site `0x4da858`, 1.31 PF per team-game in the live trace) and skips the steal check when the charge fires. The engine decides steals at the top of each trip (ADR-0045). Its outcome-4 arm `outcomeTurnover` (`engine/internal/sim/outcome.go:19`) fires on about 0.1% of trips.

A host needs one of two mapping choices, and the decompile settles neither:

- Hosting the roll on `outcomeTurnover` adds about 0.01 PF per team-game.
- Hosting it on the steal-driven turnover turns 10% of engine steals into charges. That moves STL/g, which ADR-0045 calibrated.

A backlog issue in a-jay85/IBL5-backlog, titled "J27: offensive foul on turnover (1.31 PF/team-game) has no engine host", tracks the site. `bin/backlog new` needs the origin PR URL, so the issue is filed once this branch's PR opens.

## Provenance correction

Earlier notes credited ADR-0045 with the offensive-foul decision. ADR-0045 never mentions the offensive foul. The decision lives in Q1 of `engine/docs/j27-foul-volume-port.md`. ADR-0045 lines 114-115 also say a faithful port adds no team-foul bonus. [#3867](https://github.com/a-jay85/IBL5/pull/3867) reversed that when it added `teamFoulBonusThreshold`.

## Residuals

- The bucket and-one sits at 1.415 against JSB's 0.39. The plan asked for 0.4 or closer, and this port misses that by 0.625. The engine's two and-one sites sum to 3.505 against JSB's 2.25. Porting the make roll inside JSB's foul handler (FUN_004e2220) would split the engine's bucket and-one into made and missed cases, and that is the next lever.
- The home/away FTA ratio fell from 1.082 to 0.958, further from sco's 1.101. The foul bucket's home-court legs favor the away team, and the 2.10 scale amplifies them.
- PF/g is 16.180 against sco's 17.116. The 0.936 shortfall is close to the missing offensive foul's 1.31.
- FTA per PF is 1.1583 against sco's 1.0859, because the engine's and-one over-count adds FTs.
