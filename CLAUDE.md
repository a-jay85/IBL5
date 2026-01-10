# IBL5 - Internet Basketball League

See @README.md for project overview and @DATABASE_GUIDE.md for schema reference.

## Skills Architecture

This project uses progressive loading to reduce context tokens by 50-85%.

### Path-Conditional Rules (`.claude/rules/`)
Rules auto-load when working with matching files:
- `core-coding.md` - Always loaded (universal rules)
- `php-classes.md` - PHP class development
- `phpunit-tests.md` - Test writing
- `view-rendering.md` - View/HTML rendering
- `schema-reference.md` - Database schema

### Task-Discovery Skills (`.github/skills/`)
Skills auto-load based on task intent:
- `refactoring-workflow` - Module refactoring
- `security-audit` - XSS/SQL injection auditing
- `phpunit-testing` - PHPUnit test writing
- `documentation-updates` - Doc updates
- `code-review` - PR validation
- `basketball-stats` - Stats formatting
- `contract-rules` - CBA salary rules
- `database-repository` - Repository patterns

## Quick Reference

| Task | Resource |
|------|----------|
| Run tests | `cd ibl5 && vendor/bin/phpunit` |
| Schema | `ibl5/schema.sql` |
| Stats formatting | `BasketballStats\StatsFormatter` |
| Creating skills | `.github/SKILLS_GUIDE.md` |

Use `/memory` to see currently loaded rules and skills.
