# JSB native simulation engine — build/test targets.
# Lives here (not in repo-root bin/) so the engine toolchain is self-contained
# and does not add a bin/* script that would trip the ADR decision-trigger gate.
#
# These targets mirror the gates in .github/workflows/engine.yml so that
# `make fmt-check lint cover` locally reproduces what CI enforces.

# Coverage floor — a ratchet, not an aspiration. Current total is 95.3%; this
# floor sits below that with headroom so routine refactors don't trip it.
# Raise it as coverage improves; never lower it. Kept in sync with engine.yml.
COVER_MIN := 90.0

.PHONY: build vet test golden-update lint fmt-check cover research

# Compile the jsbsim CLI to ./bin/jsbsim.
build:
	go build -o bin/jsbsim ./cmd/jsbsim

vet:
	go vet ./...

test:
	go test ./...

# Regenerate the golden-master snapshot after an intentional output change.
golden-update:
	go test ./internal/sim -run Golden -update

# Static analysis — requires golangci-lint v2.x on PATH (CI pins the version).
lint:
	golangci-lint run ./...

# Fail if any Go file is not gofmt-formatted.
fmt-check:
	@unformatted="$$(gofmt -l .)"; \
	if [ -n "$$unformatted" ]; then \
		echo "The following files are not gofmt-formatted:"; \
		echo "$$unformatted"; \
		exit 1; \
	fi

# Run tests with coverage and enforce the COVER_MIN floor.
cover:
	go test -coverprofile=cover.out ./...
	@total="$$(go tool cover -func=cover.out | awk '/^total:/ {gsub(/%/, "", $$3); print $$3}')"; \
	echo "total coverage: $$total% (floor: $(COVER_MIN)%)"; \
	if awk "BEGIN { exit !($$total < $(COVER_MIN)) }"; then \
		echo "coverage $$total% is below floor $(COVER_MIN)%"; \
		exit 1; \
	fi

# AutoResearch leverage sweep (ADR-0087). Overnight run: ~8 min/walk × 8 walks
# (2 baseline + 3 stand-ins × 2 non-baseline points) ≈ 64 min, inside a 2h window.
# --runs 50 (default) maximizes per-game SNR; --sample-stride 1 = full corpus, no
# thinning. The harness NEVER auto-commits (ADR-0087 §3): it emits the ranked
# leverage table and a human reads it before picking the next J-item
# (engine/.claude/rules/jsb-engine-post-work.md).
#
#   JSB_ARCHIVE_DIR   (required) root dir of JSB backup zips
#   JSB_RESEARCH_RUNS (optional, default 50) seeded engine runs per corpus game
#   JSB_RESEARCH_SEED (optional, default 0)  base seed for deterministic per-game seeds
research:
	@if [ -z "$$JSB_ARCHIVE_DIR" ]; then \
		echo "make research: JSB_ARCHIVE_DIR is unset — set it to the JSB backup archive root"; \
		exit 1; \
	fi
	@mkdir -p ../jsb-native/re-artifacts
	@out="../jsb-native/re-artifacts/leverage-$$(date +%Y%m%d).txt"; \
	tmp="$$out.partial"; \
	echo "make research: writing leverage report to $$out (runs=$${JSB_RESEARCH_RUNS:-50} seed=$${JSB_RESEARCH_SEED:-0} stride=1)"; \
	go run ./cmd/jsbcalibrate --mode research \
		--archive "$$JSB_ARCHIVE_DIR" \
		--runs "$${JSB_RESEARCH_RUNS:-50}" \
		--seed "$${JSB_RESEARCH_SEED:-0}" \
		--sample-stride 1 \
		> "$$tmp" && mv "$$tmp" "$$out" \
		|| { echo "make research: run failed; partial output left at $$tmp"; exit 1; }
