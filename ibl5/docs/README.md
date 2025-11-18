# IBL5 Documentation Index

This directory contains comprehensive project documentation for IBL5 development.

## 📖 Quick Navigation

### For New Contributors
1. Start with [Main README](../../README.md) - Project overview
2. Read [DEVELOPMENT_GUIDE.md](../../DEVELOPMENT_GUIDE.md) - Coding standards & workflow
3. Review [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) - What's been done
4. Check [STRATEGIC_PRIORITIES.md](STRATEGIC_PRIORITIES.md) - What to work on next

### For Developers

#### Architecture & Patterns
- **[REFACTORING_HISTORY.md](REFACTORING_HISTORY.md)** - Complete timeline of module refactorings
- **[STRATEGIC_PRIORITIES.md](STRATEGIC_PRIORITIES.md)** - Strategic analysis & next priorities
- **[TEST_REFACTORING_SUMMARY.md](TEST_REFACTORING_SUMMARY.md)** - Testing best practices & principles

#### Technical Guides
- **[STATISTICS_FORMATTING_GUIDE.md](STATISTICS_FORMATTING_GUIDE.md)** - StatsFormatter & StatsSanitizer usage
- **[DATABASE_GUIDE.md](../../DATABASE_GUIDE.md)** - Schema reference & query patterns
- **[API_GUIDE.md](../../API_GUIDE.md)** - RESTful API development
- **[PRODUCTION_DEPLOYMENT_GUIDE.md](../../PRODUCTION_DEPLOYMENT_GUIDE.md)** - Deployment procedures

## 📂 Documentation Structure

```
IBL5/
├── README.md                          # Main entry point
├── DEVELOPMENT_GUIDE.md               # Development standards
├── DATABASE_GUIDE.md                  # Schema reference
├── DATABASE_OPTIMIZATION_GUIDE.md     # DB optimization
├── API_GUIDE.md                       # API development
├── PRODUCTION_DEPLOYMENT_GUIDE.md     # Deployment
│
├── .github/
│   └── copilot-instructions.md        # Copilot agent instructions
│
├── ibl5/
│   ├── docs/                          # Project documentation (this folder)
│   │   ├── README.md                  # This file
│   │   ├── REFACTORING_HISTORY.md     # Complete refactoring timeline
│   │   ├── STRATEGIC_PRIORITIES.md    # Strategic analysis
│   │   ├── TEST_REFACTORING_SUMMARY.md # Testing best practices
│   │   └── STATISTICS_FORMATTING_GUIDE.md → ../classes/Statistics/README.md
│   │
│   ├── classes/                       # Component-specific docs
│   │   ├── DepthChart/
│   │   │   ├── README.md              # DepthChart architecture
│   │   │   └── SECURITY.md            # Security patterns
│   │   ├── Draft/README.md            # Draft module
│   │   ├── Negotiation/README.md      # Negotiation module
│   │   ├── Player/README.md           # Player module
│   │   └── Statistics/README.md       # StatsFormatter guide
│   │
│   ├── migrations/README.md           # Database migration procedures
│   │
│   └── tests/                         # Test-specific docs
│       ├── Extension/README.md
│       ├── Trading/README.md
│       └── UpdateAllTheThings/README.md
│
└── .archive/                          # Historical documents
    ├── TASK_COMPLETION_SUMMARY.md
    ├── SEASON_LEADERS_REFACTORING_SUMMARY.md
    ├── LEADERBOARDS_REFACTORING_SUMMARY.md
    └── 40+ other historical files
```

## 📋 Document Categories

### Active Documentation (Use These)
- **Core Guides** - Root-level markdown files for essential topics
- **Project Docs** - This folder (strategic & historical)
- **Component Docs** - READMEs next to the code they document
- **Copilot Instructions** - Coding standards for AI agent

### Archived Documentation (Historical Reference)
- **Completion Summaries** - Detailed refactoring reports
- **Older Guides** - Superseded by current documentation
- **Migration Reports** - Database optimization history

## 🎯 Finding What You Need

### "What's the current state of the project?"
→ [README.md](../../README.md) - Quick status overview

### "What should I work on?"
→ [DEVELOPMENT_GUIDE.md](../../DEVELOPMENT_GUIDE.md) - Current priorities  
→ [STRATEGIC_PRIORITIES.md](STRATEGIC_PRIORITIES.md) - Detailed strategic analysis

### "How do I format statistics?"
→ [STATISTICS_FORMATTING_GUIDE.md](STATISTICS_FORMATTING_GUIDE.md) - Complete guide  
→ [classes/Statistics/README.md](../classes/Statistics/README.md) - Source documentation

### "How do I write good tests?"
→ [TEST_REFACTORING_SUMMARY.md](TEST_REFACTORING_SUMMARY.md) - Testing principles  
→ [.github/copilot-instructions.md](../../.github/copilot-instructions.md) - Test quality standards

### "What's been refactored?"
→ [REFACTORING_HISTORY.md](REFACTORING_HISTORY.md) - Complete timeline  
→ [DEVELOPMENT_GUIDE.md](../../DEVELOPMENT_GUIDE.md) - Quick status

### "How do I query the database?"
→ [DATABASE_GUIDE.md](../../DATABASE_GUIDE.md) - Schema & patterns  
→ [DATABASE_OPTIMIZATION_GUIDE.md](../../DATABASE_OPTIMIZATION_GUIDE.md) - Performance

### "How do I deploy to production?"
→ [PRODUCTION_DEPLOYMENT_GUIDE.md](../../PRODUCTION_DEPLOYMENT_GUIDE.md) - Deployment procedures

### "How do I build an API?"
→ [API_GUIDE.md](../../API_GUIDE.md) - API development guide

## 🔄 Document Lifecycle

### When Documentation is Created
1. **Module Refactoring** - Create detailed completion summary
2. **Major Features** - Create architecture documentation
3. **Complex Topics** - Create focused guides

### Where Documentation Lives
1. **Active Work** - Root directory for visibility
2. **Strategic Planning** - `ibl5/docs/` directory
3. **Component Docs** - Next to the code (e.g., `classes/Player/README.md`)
4. **Completed Work** - `.archive/` for historical reference

### When Documentation is Archived
- Completion summaries after consolidation into REFACTORING_HISTORY.md
- Guides replaced by newer, more comprehensive versions
- Outdated strategic documents after new priorities established

## 🤝 Contributing to Documentation

### Guidelines
1. **Keep docs concise** - Copilot Agent has limited context window
2. **Update references** - Fix broken links when moving files
3. **Use relative paths** - Makes documentation portable
4. **Include examples** - Code examples are more valuable than descriptions
5. **Link to source** - Reference actual code when possible

### When to Update
- ✅ After completing a module refactoring
- ✅ When changing project structure
- ✅ When establishing new patterns
- ✅ When priorities change

### When to Archive
- ✅ After consolidating completion summaries
- ✅ When guides become outdated
- ✅ When strategic documents are superseded

## 📊 Documentation Status

**Last Updated:** November 17, 2025

### Recent Changes
- ✅ Consolidated 3 completion summaries into REFACTORING_HISTORY.md
- ✅ Moved STRATEGIC_PRIORITIES.md to ibl5/docs/
- ✅ Moved TEST_REFACTORING_SUMMARY.md to ibl5/docs/
- ✅ Created STATISTICS_FORMATTING_GUIDE.md symlink
- ✅ Updated all documentation cross-references
- ✅ Created this comprehensive index

### Active Documents
- 6 core guides (root directory)
- 4 project docs (this directory)
- 8 component READMEs
- 1 Copilot instructions file
- 40+ archived historical documents

## 🚀 Quick Links

**Essential Reading:**
- [Main README](../../README.md)
- [Development Guide](../../DEVELOPMENT_GUIDE.md)
- [Copilot Instructions](../../.github/copilot-instructions.md)

**For Copilot Agent:**
- [Refactoring History](REFACTORING_HISTORY.md) - What's been done
- [Strategic Priorities](STRATEGIC_PRIORITIES.md) - What to do next
- [Test Best Practices](TEST_REFACTORING_SUMMARY.md) - How to test

**Historical Reference:**
- [Archive Directory](../../.archive/) - 40+ detailed summaries

---

**Maintained by:** Copilot Coding Agent  
**Questions?** Check [copilot-instructions.md](../../.github/copilot-instructions.md) for standards
