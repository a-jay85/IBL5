#!/usr/bin/env bash
# Recompile-per-candidate sweep for offQualityConstant (Phase 6, ADR-0061).
#
# Threading a runtime *float64 override through gameState→foulBucketWeight→
# offQualityWithHCA would be ~9 sites + 2 faithful-function signature changes + all
# test callers — too much surface for a measurement seam (cf. the ADR-0054
# OffVolumeScale seam, which pre-existed). So each candidate is a recompile: set the
# const, build, run jsbcalibrate --mode calibrate at the GATE runs/stride, capture the
# full JSON. Identical numbers to a runtime override; the only cost is N builds.
#
# Restores teamquality.go to its committed value on exit (trap), so a crashed run
# never leaves the const mutated.
set -euo pipefail

ENGINE_DIR="$(cd "$(dirname "$0")" && pwd)"
F="$ENGINE_DIR/internal/sim/teamquality.go"
ARCHIVE="/Users/ajaynicolas/GitHub/IBL5/ibl5/backups"
OUT="$ENGINE_DIR/sweep-out"
RUNS="${RUNS:-20}"
STRIDE="${STRIDE:-1}"
SEED="${SEED:-20240601}"
CANDIDATES=(${CANDIDATES:-1.575 1.60 1.625 1.65})

mkdir -p "$OUT"
ORIG=$(grep -E '^\toffQualityConstant = ' "$F" | grep -oE '[0-9.]+')
restore() { sed -i '' "s/offQualityConstant = [0-9.]*/offQualityConstant = $ORIG/" "$F"; }
trap restore EXIT

echo "sweep start $(date -u +%FT%TZ) runs=$RUNS stride=$STRIDE seed=$SEED candidates=${CANDIDATES[*]}" | tee "$OUT/sweep.log"

for c in "${CANDIDATES[@]}"; do
  sed -i '' "s/offQualityConstant = [0-9.]*/offQualityConstant = $c/" "$F"
  echo "=== candidate $c  build+run $(date -u +%FT%TZ) ===" | tee -a "$OUT/sweep.log"
  go build -o "$OUT/jsbcalibrate.bin" ./cmd/jsbcalibrate
  "$OUT/jsbcalibrate.bin" --archive "$ARCHIVE" --mode calibrate \
    --runs "$RUNS" --sample-stride "$STRIDE" --seed "$SEED" \
    > "$OUT/calib-$c.json" 2>"$OUT/calib-$c.stderr" || { echo "candidate $c FAILED" | tee -a "$OUT/sweep.log"; continue; }
  python3 - "$c" "$OUT/calib-$c.json" <<'PY' | tee -a "$OUT/sweep.log"
import sys, json
c, path = sys.argv[1], sys.argv[2]
d = json.load(open(path))
hm = {h['game_type']: h for h in d.get('home_margins', [])}
fid = {f['game_type']: f for f in d.get('season_aggregates', {}).get('fidelity', [])}
nan = float('nan')
for gt in sorted(set(hm) | set(fid)):
    m = hm.get(gt, {}); f = fid.get(gt, {})
    # margin_gap + fta_disp are the GATE-1 readouts; cov/var(lnFGA) are the count-axis
    # blocker (defQ/teamDef feed (defQ−teamDef·5/6), the lead negative-Cov driver —
    # ADR-0043). The pin can move this, so surface it: watch engine_cov toward real
    # (real ≈ +2.7e-4, engine baseline ≈ −1e-3) without re-tuning toward it (ADR-0041).
    print("const=%s gt%s margin eng=%.3f sco=%.3f gap=%+.3f | fta_disp=%.3f | "
          "cov(lnFGA,lnPPS) eng=%+.6f real=%+.6f | var(lnFGA) eng=%.6f real=%.6f" % (
        c, gt, m.get('engine_home_margin', nan), m.get('sco_home_margin', nan),
        m.get('margin_gap', nan), f.get('fta_dispersion_ratio', nan),
        f.get('engine_cov_ln_fga_ln_pps', nan), f.get('real_cov_ln_fga_ln_pps', nan),
        f.get('engine_var_ln_fga', nan), f.get('real_var_ln_fga', nan)))
PY
done
echo "sweep done $(date -u +%FT%TZ)" | tee -a "$OUT/sweep.log"
