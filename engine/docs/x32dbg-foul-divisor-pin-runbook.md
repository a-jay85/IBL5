# x32dbg runbook — pin the foul-divisor stand-ins (offQ / defQ / teamDef)

**Purpose:** ADR-0061 ships `offQualityConstant = 1.575`, the foul-divisor numerator
base (`defQ`), and `teamDef` as **corpus-calibrated stand-ins** — the same status
`offQualityRatingScale` held. The RE proved offQ is volume-neutral (a constant) but did
**not** pin its runtime value; only live execution resolves it. This runbook is the
named follow-up: attach x32dbg to `jumpshot.exe` and read the three values live.

Expected payoff (per ADR-0061): replace the stand-ins with faithful pinned values,
likely close the residual GATE-1 home-margin undershoot (faithful, not calibrated), and
let the two synthetic degeneracy guards (`TestBucketWeights_FoulPathMix`,
`TestSimulate_FoulOutRate`) be re-derived from the faithful foul rate or dropped.

A model cannot shortcut this — the value is runtime/data-dependent, not statically
recoverable.

## Environment

- **VM:** Windows 11 ARM under UTM (Apple Virtualization) on the M3 Max.
- **Target:** `jumpshot.exe` (JSB 5.60, 32-bit PE, image base `0x400000`).
- **Data:** load **IBL5** (`IBL5.plr/.sco/.sch/.bxs`) — this is the calibration target, so
  pin against IBL5 (same as the `+0x6638`/`+0x6698` baselines were).
- **Debugger:** x32dbg.

## What we are reading (decompile-confirmed, JSB 5.60)

Foul function `FUN_004e1ba0 @ 0x4E1BA0`. Foul weight (decompile `:97165`):

```c
fVar11 = FUN_004e3d90(param_4);          // :97127  defQ
fVar12 = FUN_004e3f80(param_4,param_5);   // :97128  offQ  (param_5==1 ⇒ home, applies HCA)
dVar2  = *(double *)(param_1 + 0x68a8) * _DAT_00669ea0;  // :97116  teamDef (param_1 = CEngine)
...
local_e80 = (local_e80 / fVar12) * (fVar11 - dVar2 * _DAT_0066d3a0) + local_e80;  // :97165
//          = (foul_base / offQ)   * (defQ   - teamDef · 5/6)        + foul_base
```

`.rdata` constants (statically pinned from the PE):

| symbol | value | role |
|--------|-------|------|
| `_DAT_00669ea0` | **5.0** | teamDef scale |
| `_DAT_0066d3a0` | **0.8333… (5/6)** | teamDef coefficient in the foul formula |

`dVar2` is assigned once at entry (`:97116`) and **not reassigned** before `:97165`
(verified: the only `dVar2 =` sites are `:96919/:96943/:97047/:97116`, none between).

| stand-in | live read | expected behavior |
|----------|-----------|-------------------|
| `offQualityConstant` | `ST(0)` after `call 004E3F80`, **away** side (no HCA) | roster-**invariant** → the constant (invariance = volume-neutral proof) |
| `defQ` (numerator base) | `ST(0)` after `call 004E3D90` | **varies** by matchup (intended defense coupling) |
| `teamDef` | `[CEngine + 0x68A8]` (double) × 5.0 | check per-team vs league-static (see §4) |

Both `FUN_004e3f80`/`FUN_004e3d90` are `__thiscall` returning `float10` (x87) ⇒ the return
is in `ST(0)` — read it directly, no stack-slot mapping.

---

## Part 0 — Kill the exception storm + freezes (do this FIRST)

The storm has a cause: `FUN_004e3f80` installs an SEH frame (`ExceptionList = &local_14`),
and the xtajit x86→ARM64 JIT first-chances every SEH setup and every internal `0xC0000005`.
Default x32dbg breaks on each → the storm.

1. **VM snapshot NOW** (UTM), with IBL5 already loaded in jumpshot.exe. You *will* hit a
   freeze — revert to snapshot instead of a ~20-min reload. Single biggest time-saver.
2. **x32dbg → Options → Preferences → Exceptions:** add range `0x00000000–0xFFFFFFFF`,
   set **"Ignore (pass to debuggee)" on first chance**. Passes first-chance exceptions to
   the program (JIT/SEH handle them); only breaks on a genuine unhandled second-chance
   crash. This is what ends the storm.
3. **Preferences → Events:** disable "Break on system breakpoint", "Break on TLS
   callbacks", "Break on DLL entry/exit" — all emulator noise.

### Hard rules (from prior freezes — non-negotiable)

- ✅ Only software exec BPs (`bp <addr>`). Break → dump `ST(0)` / Dump pane → continue.
- ❌ **Never** `bphws` / `bpm` (data BPs) — they fault into the JIT region
  (`EXCEPTION_ACCESS_VIOLATION` ~`0x2C31xxxx`), not your code.
- ❌ **Never** F7 (step-into) or single-step the loop — both freeze. Especially never step
  into `FUN_0040b6a0` (the per-player copy inside offQ's loop — the known freezer).
- ✅ `File → Save Database` once BPs are set (addresses persist across a restart).

---

## Part 1 — Set the breakpoints

After IBL5 is loaded and execution is paused:

1. `Ctrl+G` → `4E3D90` → right-click → **Find references** (or `xrefsat 4e3d90`). The hit is
   the foul site inside `FUN_004e1ba0`. Double-click to land in the disasm.
2. You'll see the two adjacent calls: `call 004E3D90` then `call 004E3F80`. Set a BP on the
   **instruction immediately after each** (the return address):
   - after `call 004E3D90` → `ST(0)` = **defQ**
   - after `call 004E3F80` → `ST(0)` = **offQ**
3. *(teamDef base)* you already get the **CEngine base** as `ECX` at the existing
   `bp jumpshot.exe+0xD8570` hit (process-lifetime singleton, stable all session). Keep
   that BP, or reuse the base you already recorded.

## Part 2 — Capture

4. `F9` → trigger a game sim (same path that produced the `+0x6638`/`+0x6698` reads).
5. At the defQ-return BP: read **`ST(0)`** from the FPU register pane (x32dbg renders the
   80-bit value in decimal). Record. `F9`.
6. At the offQ-return BP: read **`ST(0)`** = offQ. Record whether this team is home/away
   (home applies HCA via `param_5==1`). `F9`.
7. Repeat for **~6–8 stops across both teams and 2–3 different games** (revert-snapshot
   between games if it freezes). You're collecting offQ/defQ for *different rosters* — the
   roster spread is the whole point of the volume-neutral check.

## Part 3 — Read teamDef (one Dump, no stepping)

8. With the CEngine base in hand (`ECX` at `+0xD8570`): in a Dump pane,
   `Ctrl+G <CEngine_base>+0x68A8`, render **Float→Double**. **× 5.0 → teamDef.**
9. *(optional confirm `param_1 == CEngine`)* `bp jumpshot.exe+0xE1BA0`, F9; compare its
   `param_1` to the `+0xD8570` `ECX` base — should be identical. (If `FUN_004e1ba0` is not
   `__thiscall`, `param_1` is the first stack arg `[ESP+4]`, not `ECX` — but you don't need
   it once step 8 has the base.)

## Part 4 — Interpret

- **`offQualityConstant`** — the away-side offQ reads should be **identical across
  different rosters/games**. That single repeated value *is* `offQualityConstant`, and its
  invariance is the live confirmation of the RE's volume-neutral claim. Home-side reads are
  smaller by the fixed HCA subtraction; the gap confirms `hcaMagnitude` (Go: `0.2`).
- **`defQ`** — record the range; the foul-divisor numerator's effective base is what you
  compare against the Go stand-in.
- **`teamDef`** — read it for both teams across a few possessions. `+0x68a8` is read from
  the CEngine singleton at a fixed offset, but the master ref tags the neighboring
  `+0x68xx/+0x69xx` slots as **team-defense factors** — they may be **scratch fields the
  engine overwrites with the current defending team's value before each call**, not a
  league constant:
  - **varies by defending team** → it's the live team-defense input; capture per defending
    roster (that's the faithful source for the stand-in),
  - **constant** → league baseline; one read pins it.

## Part 5 — Apply

Replace the three stand-ins in `engine/internal/sim/teamquality.go`
(`offQualityConstant`, the `defQ` base, `teamDef`) with the pinned values, regen the golden
fixture, and re-run the GATE-1 sweep (`engine/sweep-offq.sh`, runs=20/stride=1,
`--archive ibl5/backups`). Per ADR-0061 the faithful values should narrow/close the
home-margin undershoot without re-introducing foul degeneracy. If teamDef turns out
per-team, the stand-in becomes a computed per-matchup term, not a constant — note that as a
fidelity item.

## Part 6 — Degeneracy guards: re-derive vs drop (pre-analyzed, VM-independent)

Two synthetic guards exist only because offQ/defQ/teamDef are *calibrated* stand-ins;
the pin lets them be re-derived faithfully or dropped. Pre-analyzed here so the VM
session is a mechanical edit, not a judgment call. Both tests have a **structural core**
(a faithful mechanism — KEEP unconditionally) and a **magnitude band** (a calibration
backstop tied to `offQualityConstant = 1.575` — the only part the pin touches).

### `TestBucketWeights_FoulPathMix` (`internal/sim/bucketweights_test.go:48`)
- **Structural core — KEEP:** `homeFoul > awayFoul` (line 75). This is the bucket-level
  HCA mechanism (home shrinks offQ → bigger foul bucket); faithful values cannot change
  its sign. Keep verbatim.
- **Magnitude band — re-derive or drop:** `homeFoul ∈ [0.02, 0.25]` (line 71). The
  comment (66-70) is explicit that the `0.25` ceiling exists to *floor the GATE-1
  home-margin calibration* at offQ=1.575 (it holds at 0.249, hard against the top). That
  is a calibration artifact, not a faithful target.

### `TestSimulate_FoulOutRate` (`internal/sim/sim_test.go:772`)
- **Structural core — KEEP:** the test that an entire active roster fouling out is a
  rare event (the `active[tb.TeamID] == 0` cascade). Non-degeneracy is faithful.
- **Magnitude band — re-derive or drop:** `rate ≤ 0.08` (line 796). The `0.08` ceiling
  is a synthetic degeneracy cap, not corpus-derived.

### Decision rule (apply after the pin, per band)
1. Run the live foul rate the faithful offQ/defQ/teamDef produce (the post-pin golden +
   `sweep-offq.sh` give the shares).
2. **Inside the current band** → KEEP the band, but rewrite the comment from "calibration
   floor" to a faithful assertion, and drop the calibration-only `0.25` *upper* bound
   (it only floored the old margin). The directional + non-degeneracy cores stay.
3. **Outside the current band** → the band was calibration-shaped → **DROP it** and lean
   on the corpus GATE-1 sweep + golden for magnitude. Do **NOT** widen a band to fit the
   new value — fitting a synthetic guard to the measured number is the ADR-0041
   metric-gaming failure the whole approach forbids.
4. Either way, the count-axis Cov readout (now in the sweep, Part 5) is the *acceptance*
   signal — never a tuning target.

## References

- `ibl5/docs/decisions/0061-foul-bucket-volume-neutral-divisor.md` (the stand-in + the named follow-up)
- `~/jsb-foulfork-RE-verdict-20260612.md` (the Fork-A/Fork-B carrier RE)
- Decompile: `~/Downloads/jsb_560/decompiled/jsb560_decompiled.c`
  (`FUN_004e1ba0 :97082`, foul formula `:97116/:97127/:97128/:97165`;
  `FUN_004e3f80 :98252`, `FUN_004e3d90 :98164`)
- Master ref: `~/Downloads/jsb_560/decompiled/00_MASTER_REFERENCE.md` (CEngine team-defense fields, line ~716)
- Prior live-read method (CEngine baselines): memory `reference_jsb_cengine_runtime_values`
