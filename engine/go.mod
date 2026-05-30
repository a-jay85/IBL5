module github.com/a-jay85/IBL5/engine

go 1.22

// Pin the exact toolchain so the seedable-PRNG golden-master snapshots are
// reproducible across machines and CI. RNG source consumption (math/rand/v2)
// is not guaranteed byte-identical across minor Go versions, and golden tests
// are the engine's primary regression mechanism — so the version that
// generates a golden must equal the version that verifies it. The `go` command
// honors this directive (GOTOOLCHAIN=auto), auto-downloading if needed.
toolchain go1.26.3
