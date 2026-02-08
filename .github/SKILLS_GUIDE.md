# IBL5 Skills Guide

How to create, use, and validate skills for Claude/Copilot progressive loading.

## Skills Architecture

IBL5 uses two complementary systems for efficient context loading:

### `.claude/rules/` - Path-Conditional Rules
Rules auto-load based on file paths being worked on.
- Always loaded: `core-coding.md` (no `paths` frontmatter)
- Conditional: Other rules load only when touching matching files

### `.claude/skills/` - Task-Discovery Skills
Skills auto-load based on task intent detected in your prompt.
- Claude Code reads `name` and `description` from YAML frontmatter in each `SKILL.md`
- When your request matches a skill's description, the full instructions load
- Resources (templates, examples) load only when referenced

## Creating a New Skill

### 1. Create Directory Structure

```
.claude/skills/
└── skill-name/           # kebab-case directory name
    ├── SKILL.md          # Required: skill definition
    ├── templates/        # Optional: starter code files
    │   └── Template.php
    └── examples/         # Optional: usage examples
        └── example.php
```

### 2. Write SKILL.md

```markdown
---
name: skill-name
description: Clear description of what this skill does and when to use it. Be specific about capabilities and triggers. Maximum 1024 characters.
---

# Skill Title

Detailed instructions, guidelines, and patterns...

## Section 1

Content here...

## Templates

See [templates/Template.php](./templates/Template.php) for starter code.

## Examples

See [examples/example.php](./examples/example.php) for usage patterns.
```

### 3. YAML Frontmatter Requirements

| Field | Required | Constraints |
|-------|----------|-------------|
| `name` | Yes | Lowercase, hyphens for spaces, max 64 chars |
| `description` | Yes | Specific capabilities & use cases, max 1024 chars |

### 4. Description Best Practices

**Good descriptions** trigger correct auto-loading:
```yaml
description: PHPUnit 12.4+ test writing with behavior-focused patterns and mock objects for IBL5. Use when writing tests, creating test files, or reviewing test quality.
```

**Bad descriptions** are too vague:
```yaml
description: Help with testing.
```

Include:
- Specific technologies/frameworks
- Action verbs ("writing", "auditing", "formatting")
- Trigger phrases ("Use when...")

## Creating Path-Conditional Rules

### 1. Create Rule File

Place in `.claude/rules/rule-name.md`

### 2. Add YAML Frontmatter

```markdown
---
paths: ibl5/classes/**/*.php
---

# Rule Title

Content only loads when working with matching files...
```

### 3. Glob Pattern Examples

| Pattern | Matches |
|---------|---------|
| `**/*.php` | All PHP files |
| `ibl5/tests/**/*.php` | Test files only |
| `**/*View.php` | View class files |
| `ibl5/schema.sql` | Specific file |
| `{src,lib}/**/*.ts` | Multiple directories |

### 4. Unconditional Rules

Omit `paths` frontmatter for always-loaded rules:
```markdown
# Core Coding Rules

These rules apply to ALL code work...
```

## Validation Checklist

Before committing a new skill:

### SKILL.md Validation
- [ ] `name` field: lowercase, hyphens, max 64 characters
- [ ] `description` field: specific, max 1024 characters
- [ ] Directory uses kebab-case naming
- [ ] SKILL.md exists in skill root directory
- [ ] Body contains actionable instructions
- [ ] Templates/examples referenced with relative paths

### Rule Validation
- [ ] `.md` extension
- [ ] Valid glob pattern in `paths` (if conditional)
- [ ] Content is focused and specific
- [ ] No duplicate content with other rules

### Testing
```bash
# In Claude Code, run:
/memory

# Verify your skill/rule appears in loaded context
```

## Testing Progressive Loading

### Test Path-Conditional Rules

1. Open a file matching the rule's `paths` pattern
2. Run `/memory` command
3. Verify rule appears in loaded context

Example:
- Open `ibl5/classes/Player/PlayerView.php`
- Should load: `core-coding.md`, `php-classes.md`, `view-rendering.md`

### Test Task-Discovery Skills

1. Write a prompt matching the skill's description
2. Skills load automatically based on intent

Example prompts:
- "Audit for XSS vulnerabilities" → loads `security-audit`
- "Format field goal percentage" → loads `basketball-stats`
- "Write tests for PlayerService" → loads `phpunit-testing`

### Verify with /memory

```
/memory

# Shows:
# - Project memory: CLAUDE.md
# - Rules: .claude/rules/*.md (matching current file)
# - Skills: .claude/skills/*/SKILL.md (matching task)
```

## Benefits

| Approach | Token Usage |
|----------|-------------|
| All docs in context | ~12,000+ tokens |
| Core rules only | ~800 tokens |
| Core + 1 task skill | ~1,500 tokens |
| Core + 2 task skills | ~2,200 tokens |

**Savings:** 50-85% context reduction for typical tasks

## Existing Skills

| Skill | Description | Triggers |
|-------|-------------|----------|
| `refactoring-workflow` | Module refactoring | "refactor", "extract", "Repository pattern" |
| `security-audit` | XSS/SQL injection | "security", "XSS", "SQL injection", "audit" |
| `phpunit-testing` | PHPUnit tests | "test", "PHPUnit", "mock" |
| `documentation-updates` | Doc updates | "documentation", "README", "update docs" |
| `code-review` | PR validation | "review", "PR", "merge", "validate" |
| `basketball-stats` | Stats formatting | "percentage", "average", "PPG", "stats" |
| `contract-rules` | CBA salary rules | "contract", "salary", "Bird Rights", "MLE" |
| `database-repository` | Repository pattern | "repository", "database", "query", "mysqli" |

## Existing Rules

| Rule | Path Pattern | Loads When |
|------|--------------|------------|
| `core-coding.md` | (none) | Always |
| `php-classes.md` | `ibl5/classes/**/*.php` | Working in classes |
| `phpunit-tests.md` | `ibl5/tests/**/*.php` | Working on tests |
| `view-rendering.md` | `**/*View.php` | Working on View files |
| `schema-reference.md` | `ibl5/schema.sql` | Viewing schema |
