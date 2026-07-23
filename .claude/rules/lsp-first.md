---
description: For symbol lookups (definition, references, signature), call the LSP tool before Grep/Explore — one semantic call replaces a noisy multi-file grep-and-read.
last_verified: 2026-07-22
paths: "**/*.php"
---

# LSP-First Symbol Lookup

Intelephense is wired for this repo (php-lsp plugin, `--stdio`). For **symbol-level
questions**, reach for the `LSP` tool **before** Grep or an Explore agent.

## When LSP is the right first move

| Question | LSP operation | Replaces |
|----------|---------------|----------|
| Where is this defined? | `goToDefinition` | grep for `function X` |
| Everywhere it's used? | `findReferences` | `grep -rn X` + reading files to disambiguate |
| What's the signature / docblock? | `hover` | opening the definition file |
| Find a symbol by name across the repo | `workspaceSymbol` (pass `query`) | `grep -rn` across the tree |
| What calls / is called by this? | `incomingCalls` / `outgoingCalls` | multi-hop grep tracing |

## Why (measured, 2026-07-07 on `CsrfGuard::validateSubmittedToken`)

- **Precision.** `findReferences` returned **26 semantic refs**; `grep -rn` returned
  **42 raw matches** — the extra 16 are docblocks/comments/string-literals you'd
  otherwise Read files to filter out.
- **Tokens.** The LSP path (`findReferences` ~200 tok + `hover` ~120 tok ≈ **~320 tok**)
  answered "understand this method + all real call sites." The grep-and-read path cost
  **~3–10K tok** (noisy grep + reading ≥3 call-site files). ~10–30× cheaper **and** exact.

## Caveats

- **1-based** line **and** character, as shown in the editor gutter.
- **PHP only** — intelephense doesn't cover JS/TS/Twig/SQL. Use Grep/Explore there.
- **Broad codebase sweeps stay with Grep/Explore** — LSP answers *symbol* questions, not
  "every file that mentions this string" or cross-language patterns.
- **Empty first result → retry once.** The server is spawned fresh per session with no
  persistent disk cache; the tool normally blocks until indexed, but if a first call ever
  returns empty, fire it again rather than falling back to grep. (No SessionStart warm-up
  exists: a `--stdio` server can't be reached by a shell hook — verified 2026-07-07.)
