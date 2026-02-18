---
name: documentation-updates
description: Update IBL5 documentation during pull requests and feature development. Use when updating docs, creating README files, or documenting completed work.
---

# IBL5 Documentation Updates

Update documentation incrementally during refactoring PRs.

## Documentation Locations

| Location | Purpose | When to Update |
|----------|---------|----------------|
| `ibl5/docs/STRATEGIC_PRIORITIES.md` | Module completion summaries | Mark module complete |
| `ibl5/docs/REFACTORING_HISTORY.md` | Detailed refactoring timeline | Add entry for each module |
| `ibl5/classes/ModuleName/README.md` | Component architecture | Create during refactoring |
| `ibl5/docs/DEVELOPMENT_GUIDE.md` | Status counts, priorities | Update module counts |

## PR Documentation Checklist

### 1. Update STRATEGIC_PRIORITIES.md

```markdown
### N. ModuleName Module ✅ (Month Day, Year)

**Achievements:**
- X classes created with separation of concerns
- Reduced module code: XXX → XX lines (XX% reduction)
- XX comprehensive tests
- [Key security/feature improvement]

**Documentation:** `ibl5/classes/ModuleName/README.md`
```

### 2. Update REFACTORING_HISTORY.md

```markdown
### N. ModuleName Module (Month Day, Year)

**Summary:** One sentence description

**Key Improvements:**
- Bullet points of main changes

**Classes Created:**
1. **ModuleRepository** - Data access
2. **ModuleService** - Business logic
3. **ModuleView** - HTML rendering

**Files Refactored:**
- `modules/ModuleName/index.php`: XXX → XX lines (-XX%)

**Test Coverage:**
- ModuleRepositoryTest: X tests
- ModuleServiceTest: X tests

**Documentation:** `ibl5/classes/ModuleName/README.md`
```

### 3. Create Component README

Create `ibl5/classes/ModuleName/README.md`:

```markdown
# ModuleName Module

## Overview
Brief description of what the module does.

## Architecture
```
ModuleName/
├── Contracts/
│   └── ModuleRepositoryInterface.php
├── ModuleRepository.php
├── ModuleService.php
└── ModuleView.php
```

## Usage
```php
$service = new ModuleService($db);
$result = $service->doSomething($params);
```

## Security
- All queries use prepared statements
- Output escaped with HtmlSanitizer
```

### 4. Update DEVELOPMENT_GUIDE.md

- Update test count if changed
- Update module completion status

## File Naming Standards

- Guides: `SCREAMING_SNAKE_CASE.md` (e.g., `DEVELOPMENT_GUIDE.md`)
- Component docs: `README.md` in directory
- Historical: Move to `ibl5/docs/archive/`

## Content Standards

- Start with purpose/overview
- Include "Last Updated" date
- Use consistent markdown formatting
- Add code examples where helpful
