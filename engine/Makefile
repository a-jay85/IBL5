# JSB native simulation engine — build/test targets.
# Lives here (not in repo-root bin/) so the engine toolchain is self-contained
# and does not add a bin/* script that would trip the ADR decision-trigger gate.

.PHONY: build vet test golden-update

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
