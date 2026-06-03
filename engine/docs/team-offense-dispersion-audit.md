# Team-Offense Dispersion — Faithfulness Audit (RE spike)

> **Type:** reverse-engineering faithfulness audit. **Ships no engine code.** The
> deliverable is the evidence + the go/no-go verdict (§5); the durable decision is
> ADR-0040. Every claim below is grep-/line-reproducible against the decompile
> (`~/Downloads/jsb_560/decompiled/`) or the current Go source (`engine/`).
>
> **Question.** The 2026-06-02 season-aggregate calibration proved the engine cannot
> cut over: team scoring does not track offensive ratings (PF corr = 0.04), team
> strength is compressed to ~⅓ of real dispersion, scoring runs ~23.5 ppg/team low
> (memory `reference_jsb_season_aggregate_verdict`). Under the overriding
> fidelity-to-5.60 constraint (`project_jsb_native_rewrite`), the question is **not**
> "what constant raises dispersion" but **"what does jumpshot 5.60 actually do to
> disperse team offense, and did we port it."**
>
> **One-line answer.** 5.60 disperses team offense through **per-48 shot-VOLUME rates
> computed from season counting-stat sums** (the +0xD90 Branch-A composite), *not*
> through the ODPT offense ratings (OO/DriveOff/PO). The Go port carries the
> mechanism faithfully but feeds it **compressed 0-99 rating stand-ins** instead of
> the real season rates — and those real rates **exist in the IBL data path** (the
> static real-life/previous-season `.plr` block). The fix is **structural and
> faithful: source the real rate inputs**, not reweight a knob.

---

## §1 — How team-offense quality reaches scoring (the engine-side baseline)

> *Plan 1a. Matrix rows 1, 2.*

There are **four** code paths that read a player's offense-quality ratings
(OO / DriveOff / PO). Tracing each shows where team offense *can* and *cannot* move
team scoring.

```
grep -rnE '\.(OO|DriveOff|PO|TransOff)\b' engine/internal/sim/*.go | grep -v _test.go
```

| # | Path | File:line | What it does | Carries team-level scoring signal? |
|---|------|-----------|--------------|-----------------------------------|
| 1 | Ball-handler selection | `ballhandler.go:14-20,46` | picks **who** initiates the possession, weighted by each starter's ODPT share | **No — scale-invariant** (see below) |
| 2 | Shot-type selection | `shotselect.go:32-34` | picks **which** play type (outside/drive/post), matchup-adjusted | **No — routes type, all types share one make-value base** |
| 3 | Net advantage → make value | `netadvantage.go:20-24` → `shotdecision.go:35` | the chosen play type's offense rating enters `net`, a weak make-value term | **Weak, and defense-canceling** |
| 4 | Foul-bucket off-quality divisor | `teamquality.go:108` | OO feeds the foul-bucket divisor + HCA | minority bucket, not a scoring-level driver |

**Path 1 carries no team-level signal — it is scale-invariant.** The ball-handler
share is `rating / (teamTotal − rating)` (`ballhandler.go:31-37`,
`selectBallHandler` 43-92). For player *i* with share `rᵢ/(Σr − rᵢ)`: multiply
**every** player's rating by *k* and the share becomes `krᵢ/(kΣr − krᵢ)` =
`rᵢ/(Σr − rᵢ)` — **unchanged**. So a uniformly high-offense team and a uniformly
low-offense team distribute the ball identically; the absolute level of OO/DO/PO
never changes who-much the team shoots, only the *relative* split within the
lineup. **This channel disperses shots within a roster; it carries zero
team-to-team scoring signal.**

**Path 2 routes type, not magnitude.** `selectShotType` (`shotselect.go:40-55`)
weights outside/drive/post by `rating − offW + defW` (matchup tax table
`shotTypeWeights`, lines 11-17). It decides *which* play type is attempted; all
three play types then assemble make value through the **same** base
(`base2pt = FGP × 9`, `shotdecision.go:26`) and the same weak `net` term. Picking
"drive" over "post" does not raise team scoring.

**Path 3 is the only absolute-level channel, and it is weak.** `netAdvantage`
(`netadvantage.go:16-35`): `net = off − penalty − def`, off ∈ {OO, DriveOff, PO}.
It feeds **only** `shotValue2pt`'s `net × netToShotValue / leagueBaseline` term
(`shotdecision.go:31-36`; `netToShotValue = 500`, `leagueBaseline = 233`). The 3pt
path is **net-free by construction** (`shotValue3pt = leagueBaseline × 1.5`,
`shotdecision.go:38-41`).

**Quantified make-value contribution.** Per offense-rating point the net term is
`500/233 ≈ 2.15‰`. ODPT ratings are 1-9; a realistic offense swing of +8 (1→9)
adds `8 × 2.15 ≈ +17‰` of make value. The base term is `FGP × 9`
(`fgpToPermille = 9`, `shotdecision.go:15`); at a league-typical FGP ≈ 50 that is
`450‰`. So the **entire offense-quality swing is ±17‰ against a ~450‰ base
(≈ ±3.8%)** — and because `net = off − def`, a strong offense facing a strong
defense **cancels**. The make-value path is FGP-dominated and league-uniform; team
offense quality barely moves it. *(Reproduce: `shotdecision.go:13-36`,
`netadvantage.go:16-35`.)*

**The bucket path reads no quality rating at all.** `twoPtBucketWeight`
(`bucketweights.go:98-107`) consumes only `p.FGA`, `p.ORB`, `p.FTA` — **volume**
ratings — never OO/DriveOff/PO/FGP:

```
grep -nE 'p\.(FGA|ORB|FTA|OO|DriveOff|PO)' engine/internal/sim/bucketweights.go
# → only p.FGA (99), p.ORB (100), p.FTA (101)
```

**§1 conclusion.** In the Go engine — faithfully mirroring 5.60 (§2-§3) — team
offensive *quality* (OO/DriveOff/PO) reaches scoring through three channels that
are either **within-lineup-relative / scale-invariant** (who shoots, which type)
or **weak and defense-canceling** (the net make-value term). **Team scoring level
and dispersion are driven by shot VOLUME (the FGA-rate bucket composite) and
shooting EFFICIENCY (FGP), not by the ODPT offense ratings.** This already reframes
the calibration symptom: see the **negative finding** in §5.

---

## §2 — The faithful +0xD90 inputs (D88 / DB8 / D70): what 5.60 sources them from

> *Plan 1b. Matrix rows 3, 4.*

The 2pt bucket weight is the recovered +0xD90 Branch-A composite
(`jsb560_decompiled.c:91078-91086`, `COMPOSITE_DOUBLES_TRACE.md §4`):

```
D90 = D88 − (D88/(D70+D88)) × DB8 × ((D88/(DB8+D88)) × 0.5 + 0.25)
```

reproduced verbatim in `twoPtBucketWeight` (`bucketweights.go:98-107`). The inputs:

| Input | 5.60 source (`COMPOSITE_DOUBLES_TRACE.md §1,§3`) | Faithful form |
|-------|------------------------------------------------|---------------|
| **D88** (`+0xD88`) | `(ΣFGA / ΣGP) × 48` | per-48 **FGA rate** from season counting-stat sums |
| **DB8** (`+0xDB8`) | `(ΣORB / ΣGP) × 48` | per-48 **ORB rate** |
| **D70** (`+0xD70`) | `(ΣFTM × ((C[+0x6938]×5 − C[+0x68D8]×0.5)/(C[+0x6728]×5)) / Σstat) × 48` | per-48 **FTA-weighted rate × a league-relative scalar** |

Each is `(season_stat_sum / GP_sum) × 48.0` (`_DAT_00669ed0 = 48.0`,
`COMPOSITE_DOUBLES_TRACE.md:108-111`). **These are season-aggregate VOLUME rates —
not ratings.**

**What the Go port feeds them instead.** `bucketweights.go:59-61,99-101` maps a
0-99 *rating* to a per-48 analog: `d88 = floor1(p.FGA) × 0.30`,
`db8 = floor1(p.ORB) × 0.15`, `d70 = floor1(p.FTA) × 0.30`. The header
(`bucketweights.go:31-37`) labels these **VALIDATION-PHASE STAND-INS**: *"the
per-48 rate inputs D88/DB8 derive from r_fga/r_orb (the bundle carries no season
GP/FGA sums), D70 stands in for the CEngine team/league FTA-weighted aggregate
absent from the bundle."*

**This is the dispersion-compression mechanism.** A 0-99 rating with a league mean
near 50 and a narrow SD, scaled by a constant, produces a **narrow** per-team
spread. Real per-48 season volume rates have a **much wider** team-to-team spread
(a high-pace, shot-heavy team vs. a slow, low-volume team differ by far more than
their `r_fga` ratings do). Substituting the compressed rating for the wide volume
rate **collapses the bucket-weight spread → collapses how concentrated each team's
shot distribution is → collapses team-scoring dispersion.** This is the
mechanical origin of the "~⅓ of real dispersion" symptom.

**Does the IBL data path carry the faithful inputs? — YES (D88, DB8) / PARTIAL (D70).**

The `.plr` file carries a **static "Real-Life / Previous Season Stats" block**
(`ibl5/docs/JSB_FILE_FORMATS.md:122-140`), explicitly *"the reference stats used by
the engine when simulating games, independent of the in-game season totals"*:

```
grep -nE 'realLife(GP|FGA|FTA|ORB|DRB|AST)' ibl5/docs/JSB_FILE_FORMATS.md
# 52 realLifeGP · 64 realLifeFGA · 72 realLifeFTA · 84 realLifeORB · 88 realLifeDRB · 92 realLifeAST
```

Every D88/DB8 input is present: `realLifeGP` (the ΣGP divisor), `realLifeFGA`,
`realLifeORB`, `realLifeFTA`. The block is **static** (a prior-season reference, not
the live in-game totals), which:

- matches the doc's "used by the engine when simulating games" wording;
- matches memory `reference_jsb_season_rating_stability` ("IBL ratings constant all
  season"); and
- **moots the cold-start problem** — a live-season-totals source (`.plr` 144-207,
  `ΣGP = 0` before game 1) would divide by zero; a static reference block never does.

> **Reconciliation note (verify in the model PR, not a blocker).** The trace reads
> the rate composites from runtime struct offsets `+0x144..0x178`
> (`COMPOSITE_DOUBLES_TRACE.md:128-149`), whose **file** counterpart is the live
> season block (`.plr` 144-207). The file-format doc, by contrast, names the
> real-life block (52-111) as the engine's "real-life tendencies" source. The
> numeric inputs are identical between the two blocks; the open item is **which
> block the loader (`FUN_004b04d0`) maps into `+0x144`**. The static real-life block
> is the recommended source on the three grounds above; the model PR must confirm
> the loader mapping. Either way, **the inputs exist in the IBL `.plr`** — the
> bundle simply does not carry them (`bundle.go:60-113` has ratings only, no season
> sums: `grep -niE 'season|games|sum' engine/internal/bundle/bundle.go` → none).

**D70 is a partial gap — see the second negative finding in §5.** Its per-player
part (`ΣFTM/Σstat × 48`) is sourceable from the real-life block, but its
**league-relative scalar** `((C[+0x6938]×5 − C[+0x68D8]×0.5)/(C[+0x6728]×5))`
(`COMPOSITE_DOUBLES_TRACE.md:157-159`) reads **runtime CEngine league aggregates** —
the same "loader-populated, not modeled" class as `league_baseline` (CEngine+0x6638)
and the constants memory `reference_jsb_rdata_static_read` flags as the only
unpinnable runtime values. It is a uniform league scalar, so it degrades to a
**calibrated constant**, not a faithfully-sourced per-player input.

---

## §3 — Branch-B usage-shrink: pinned, but not the dispersion driver

> *Plan 1c. Matrix rows 5.*

The plan framed 1c as RE-ing "four **unresolved** `.rdata` constants." **That premise
is stale.** `COMPOSITE_DOUBLES_TRACE.md §5` (dated 2026-06-02) **already resolved
them** by direct `.rdata` read of `jumpshot.exe` (PE, image base 0x400000):

```
grep -n '_DAT_0066d318\|_DAT_0066d310\|_DAT_0066d320\|_DAT_00669ad0' \
  ~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md
```

| Constant | Value | Role | Gates Branch-B? |
|----------|-------|------|-----------------|
| `_DAT_0066d318` | **0.2** | `:91072` scale on `(team DRB-rate + team AST-rate)` | ✅ yes |
| `_DAT_0066d310` | **0.04** | `:91076` per-player scale forming `in_f0` | ✅ yes |
| `_DAT_0066d320` | 1/3000 | `:90985` an **unrelated** earlier-loop normalization | ❌ no |
| `_DAT_00669ad0` | 20000 | `:6537` a **different function** | ❌ no |

So 1c's RE answer: **only two constants gate Branch-B** (the trace's own
"Attribution correction," lines 219-229), and the usage target is

```
in_f0 = player[+0x1E8] × (teamDRBrate + teamASTrate) × 0.2 × 0.04   (= × 0.008)
```

with `player[+0x1E8]` = the **TO (Transition Offense)** ODPT rating = bundle
`r_trans_off` (`bundle.go:72`). All three Branch-B inputs are **statically
identified**; nothing here is RE-blocked any longer.

**What Branch-B actually does (`jsb560_decompiled.c:91087-91099`, traced live):**
because the other three buckets `+0xDB0`/`+0xDE0`/`+0xD78` are **dead-zero**
(`COMPOSITE_DOUBLES_TRACE.md §RESOLUTION`, lines 14-32), the proportional shrink
`dVar59 = (Σbuckets − in_f0)/Σbuckets` collapses to **`D90_new = D90 − in_f0`**.
Branch-B is therefore a **per-player usage CAP**: a high-TO player on a high-DRB/AST
team has more subtracted from his 2pt bucket.

**Does the missing dispersion live here? — NO.** Three reasons:

1. **Branch-B reads no offense-quality rating either** — its only per-player factor
   is TO (transition), and the team factors are DRB/AST **volume** rates. Porting
   it would not make team scoring track OO/DriveOff/PO.
2. **It is a shrink (a cap), not an amplifier.** Subtracting a usage target from the
   highest-volume players **compresses** within-lineup concentration, if anything —
   the opposite of adding team-to-team dispersion.
3. **It depends on the same season aggregates as §2** (team DRB-rate `+0xDC0`,
   team AST-rate `+0xDD0` = `(Σstat/ΣGP)×48`), so it cannot even be exercised
   faithfully until candidate (A)'s real rates are wired.

Branch-B is faithful to port and no longer RE-blocked, but it is a **secondary
modulator**, not the dispersion driver. Its real-world effect on team dispersion is
an **open empirical question** (`COMPOSITE_DOUBLES_TRACE.md:228-229`), answerable
only after (A) and only by measurement against PR2's level/dispersion instrument.

---

## §4 — The make-value net weight in 5.60: the port is already faithful

> *Plan 1d. Matrix rows 6.*

Does 5.60's `shot_value` carry **more** team-offense signal than the Go port's
`net × 500/233 + fgp × 9`, leaving room to faithfully reweight `net`? **No — 5.60's
assembly is identical to the port.**

```
grep -n '0x6638\|0xD64\|0xD68\|league_baseline\|shot_value' \
  ~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md
```

`00_MASTER_REFERENCE.md:1046-1056` (re-verified 2026-05-30 against possession_handler
RAW + `.rdata`):

```
2pt normal:  shot_value = (net_advantage × 0.5 / league_baseline) × 1000.0 + base_2pt_value
3pt:         shot_value = league_baseline × 1.5      // NO net_advantage
FT:          shot_value = player[+0xD68]             // pure FTP
```

- 5.60 `(net × 0.5 / baseline) × 1000` = port `net × 500 / baseline`
  (`shotdecision.go:18,35`; `netToShotValue = 0.5 × 1000 = 500`). **Identical.**
- 5.60 `base_2pt_value` = `player[+0xD64]` = `(make-rate) × 1000` round-to-int
  (`COMPOSITE_DOUBLES_TRACE.md:151-156`); the port's `base2pt = FGP × 9` is the
  documented stand-in for that per-game 2P% base (`shotdecision.go:5-12,24-26`).
- 5.60's 3pt is **net-independent** (`league_baseline × 1.5`), exactly as the port
  (`shotdecision.go:38-41`); `league_baseline = CEngine+0x6638`
  (`00_MASTER_REFERENCE.md:182,1056`).

**§4 conclusion.** 5.60 keeps make value **matchup-light by design** — team offense
enters it through the **same weak `net × 0.5/baseline` term** the port already
implements, FGP/baseline-dominated. 5.60 does **not** express team-offense
dispersion through make value; it expresses it through the **bucket/volume path**
(§2). Reweighting `net` would therefore **fabricate** a mechanism 5.60 does not have.

---

## §5 — Verdict: go/no-go per candidate

> *Plan 1e. Matrix rows 7.*

| Candidate | Faithful? | Verdict | Rationale |
|-----------|-----------|---------|-----------|
| **(A)** source real per-48 season-rate aggregates into the bundle (D88/DB8/D70 from `Σstat/ΣGP × 48`) | **FAITHFUL** | ✅ **GO — primary** | §2: this is 5.60's actual dispersion mechanism; the port ships the formula but feeds compressed rating stand-ins. The real inputs **exist** in the static real-life `.plr` block (52-111). |
| **(B)** port the +0xD90 Branch-B usage-shrink | **FAITHFUL** | ◻ **CONDITIONAL — secondary, measure after (A)** | §3: pinned + statically identified (not RE-blocked), but a usage *cap* reading the same season aggregates, not a dispersion amplifier. Effect is an open empirical question — port after (A), measure on PR2's instrument. |
| **(C)** reweight `net` in `shot_value` | **INVENTED** | ✖ **NO-GO** | §4: 5.60's make value is net-light by design and the port already matches it exactly (`00_MASTER_REFERENCE.md:1048` = `shotdecision.go:35`). Reweighting fabricates a mechanism 5.60 never ran. |

### Negative findings (a path proven NOT faithful / NOT available)

1. **(C) is not faithful — REJECTED.** 5.60's make-value net weight equals the Go
   port's exactly (§4). There is no faithful "heavier net" to port; raising it would
   be a `#957`-style guess at a mechanism instead of porting 5.60's. *(This is the
   audit succeeding at its purpose: it stops an invented fix, exactly as the
   team-foul-bonus audit stopped an invented NBA bonus.)*

2. **D70's league-relative scalar is NOT available in the IBL data path — a
   documented permanent partial gap.** Its per-player FTA-rate part is sourceable
   (§2), but the factor `((C[+0x6938]×5 − C[+0x68D8]×0.5)/(C[+0x6728]×5))` reads
   runtime CEngine **league** aggregates (`COMPOSITE_DOUBLES_TRACE.md:157-159`) —
   the "loader-populated, not modeled" class (`reference_jsb_rdata_static_read`,
   like uniform stamina and `league_baseline`). (A) sources the per-player part
   faithfully and carries the league scalar as a **calibrated constant**; it cannot
   be faithfully sourced.

3. **The PF-vs-offense-rating ≈ 0.04 symptom is LARGELY FAITHFUL, not purely a
   bug.** §1 establishes that 5.60 routes ODPT offense quality into *who-shoots*
   (scale-invariant share) and *which-play-type* (weak), and into make value only
   via a weak, defense-canceling net term. **5.60 itself does not make team scoring
   track OO/DriveOff/PO.** Expecting strong PF↔ODPT-offense correlation is therefore
   an *unfaithful* target. The faithful, fixable defect is the dispersion
   **magnitude** — the volume composite fed compressed rating stand-ins (§2) — not
   the quality correlation.

### Recommended faithful path

Ship a single model PR scoped to **(A)**: wire the static real-life/previous-season
counting-stat sums (`realLifeGP/FGA/ORB/FTA/DRB/AST`, `.plr` 52-111) into the
bundle and compute D88/DB8 (and D70's per-player part) as `(Σstat/ΣGP) × 48`,
**replacing** the `r_fga × 0.30` / `r_orb × 0.15` / `r_fta × 0.30` stand-ins
(`bucketweights.go:59-61,99-101`). Carry D70's league scalar as a calibrated
constant (negative finding 2). Then port **(B)** as a measured follow-on and confirm
empirically whether the usage-shrink helps or hurts dispersion. **Reject (C).**

Measure every step against PR2's level/dispersion instrument
(`jsb-calibrate-level-dispersion-metrics`). Expect (A) to also narrow the scoring
**level** deficit where the same compressed volume input depresses attempts — noted,
but level remains a separate fidelity axis (Out of Scope of this audit).

This audit ships **no engine code**; (A)/(B) are a separate model PR scoped from
this verdict. The decision is recorded in **ADR-0040**.

---

## Reproduce-the-evidence index (CLI-executable verification)

| Claim | Command |
|-------|---------|
| §1 quality consumers | `grep -rnE '\.(OO\|DriveOff\|PO\|TransOff)\b' engine/internal/sim/*.go \| grep -v _test.go` |
| §1 net path | `grep -n 'OO\|DriveOff\|PO\|netToShotValue' engine/internal/sim/netadvantage.go engine/internal/sim/shotdecision.go` |
| §1 bucket reads volume only | `grep -nE 'p\.(FGA\|ORB\|FTA\|OO\|DriveOff\|PO)' engine/internal/sim/bucketweights.go` |
| §2 D88/DB8/D70 formula | `grep -n '91078\|D90\|D88\|DB8\|D70' ~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md` |
| §2 inputs in `.plr` | `grep -nE 'realLife(GP\|FGA\|FTA\|ORB\|DRB\|AST)' ibl5/docs/JSB_FILE_FORMATS.md` |
| §2 bundle has no season sums | `grep -niE 'season\|games\|sum' engine/internal/bundle/bundle.go` |
| §3 four constants | `grep -n '_DAT_0066d318\|_DAT_0066d310\|_DAT_0066d320\|_DAT_00669ad0' ~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md` |
| §3 Branch-A/B body | `sed -n '91072,91099p' ~/Downloads/jsb_560/decompiled/jsb560_decompiled.c` |
| §4 shot_value spec | `grep -n '0x6638\|0xD64\|0xD68\|league_baseline\|shot_value' ~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md` |

## Source-of-record

- `engine/internal/sim/netadvantage.go`, `shotdecision.go`, `bucketweights.go`,
  `ballhandler.go`, `shotselect.go`, `teamquality.go` — engine-side paths (§1, §4).
- `engine/internal/bundle/bundle.go` — what the bundle carries today (§2).
- `~/Downloads/jsb_560/decompiled/COMPOSITE_DOUBLES_TRACE.md` — +0xD90 Branch-A/B,
  the four constants (§2, §3).
- `~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md` — shot_value / league_baseline (§4).
- `ibl5/docs/JSB_FILE_FORMATS.md` — `.plr` real-life + season stat blocks (§2).
- Memories: `reference_jsb_season_aggregate_verdict`, `reference_play_outcome_buckets`,
  `reference_jsb_hca_pr7a_blocked`, `reference_jsb_season_rating_stability`,
  `reference_jsb_rdata_static_read`.
