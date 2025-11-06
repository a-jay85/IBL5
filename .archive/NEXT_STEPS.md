# IBL5 - Next Steps for Refactoring

## Quick Start

👉 **Read the full analysis**: [REFACTORING_PRIORITIES_REPORT.md](REFACTORING_PRIORITIES_REPORT.md)

This document provides a quick overview of what to do next based on the comprehensive refactoring priorities assessment.

---

## Current State at a Glance

✅ **12 modules** fully refactored with comprehensive tests  
🟡 **2 modules** partially refactored  
❌ **51 modules** awaiting refactoring  
📊 **~350 tests** covering core business logic  
🎯 **Goal**: Get heavily-used sections under test before extending

---

## Top 3 Immediate Priorities

### 🥇 Priority 1: Free Agency Module

**Start Here First** - This is the most critical business module.

- **Size**: 1,648 lines (largest unrefactored module)
- **Why Critical**: Handles all contract signings and salary cap enforcement
- **Risk**: Financial integrity of the league depends on this
- **Impact**: Protects against contract/cap bugs that affect league balance

**What to do:**
1. Follow the refactoring pattern from Waivers/Draft modules
2. Extract 5-6 classes (Repository, Validator, Processor, View, Controller)
3. Write 60+ comprehensive tests covering:
   - Salary cap validation
   - Contract offer processing
   - Hard cap enforcement
   - Edge cases (minimums, exceptions)
4. Security audit for SQL injection and XSS

**Estimated Time**: 4-6 weeks  
**Expected Outcome**: 1,648 → ~150 lines, fully tested, secure

---

### 🥈 Priority 2: Player Display Module

**Most Viewed Page** - Best user experience matters here.

- **Size**: 749 lines
- **Why Important**: Most frequently accessed page on the site
- **Impact**: First impression for users, affects perception of site quality

**What to do:**
1. Leverage existing Player classes (already refactored)
2. Extract display-specific classes:
   - `PlayerDisplayController`
   - `PlayerDisplayUIService`
3. Write 30-40 tests for:
   - Different page views
   - Conditional logic (buttons, displays)
   - Permission checks
4. Mobile-responsive design improvements

**Estimated Time**: 2-3 weeks  
**Expected Outcome**: 749 → ~80 lines, responsive, tested

---

### 🥉 Priority 3: Statistics Module

**Core Feature** - Central to basketball simulation.

- **Size**: 513 lines
- **Why Important**: Core feature users interact with daily
- **Impact**: Enables new stat features, better performance

**What to do:**
1. Build on existing `StatsFormatter` and `StatsSanitizer`
2. Extract remaining classes:
   - `StatisticsRepository`
   - `StatisticsController`
   - `StatisticsView`
3. Write 40-50 tests for:
   - Query generation
   - Stat calculations
   - Filtering/sorting
   - Export functionality
4. Performance optimization

**Estimated Time**: 3-4 weeks  
**Expected Outcome**: 513 → ~60 lines, performant, extensible

---

## Implementation Timeline

### Phase 1: Foundation (Months 1-3)
- ✅ Month 1: **Free Agency Module**
- ✅ Month 2: **Player Display Module**
- ✅ Month 3: **Statistics Module**
- **Target**: 60% test coverage

### Phase 2: Expansion (Months 4-6)
- Chunk_Stats & Player_Search
- Compare_Players
- Developer experience improvements
- **Target**: 75% test coverage

### Phase 3: Advanced Features (Months 7-9)
- RESTful API development
- Event system
- Configuration management
- **Target**: 80%+ test coverage

---

## How to Approach Each Refactoring

### Step-by-Step Process

1. **Analyze** (1-2 days)
   - Read through existing code
   - Identify responsibilities
   - Map database queries
   - Note security concerns

2. **Design** (1-2 days)
   - Sketch class structure
   - Define interfaces
   - Plan test scenarios
   - Review with team

3. **Extract Classes** (1-2 weeks)
   - Start with Repository (database)
   - Then Validator (rules)
   - Then Processor (logic)
   - Then View (UI)
   - Finally Controller (orchestration)
   - Follow existing patterns!

4. **Write Tests** (1 week)
   - Unit tests for each class
   - Integration tests for workflows
   - Edge case coverage
   - Security tests

5. **Security Audit** (2-3 days)
   - SQL injection prevention
   - XSS prevention
   - Input validation
   - Authorization checks

6. **Review & Refine** (2-3 days)
   - Code review
   - Performance testing
   - Documentation
   - Backward compatibility check

### Use These as Templates

Best examples to follow:
- **Waivers**: Most complete refactoring with excellent tests
- **Depth Chart**: Security best practices
- **Team**: Clean MVC separation
- **Player**: Facade pattern for complex objects

---

## Developer Experience Improvements

### Quick Wins (Can Do Now)

1. **Code Quality Tools**
   ```bash
   # Add these to composer.json
   composer require --dev phpstan/phpstan
   composer require --dev squizlabs/php_codesniffer
   ```

2. **Pre-commit Hooks**
   - Run tests before commit
   - Check code style
   - Prevent committing debug code

3. **Documentation**
   - README for each module
   - Architecture decision records
   - "How to add a feature" guides

### Medium-term Improvements

4. **Docker Environment** (Week-long project)
   - Consistent development environment
   - One-command setup
   - Pre-loaded test data

5. **CI/CD Enhancements**
   - Run full test suite on every commit
   - Code coverage reporting
   - Performance regression detection

---

## Testing Strategy

### Coverage Goals

- **Now**: ~30% coverage of active features
- **After Phase 1**: 60% coverage (top 3 modules done)
- **After Phase 2**: 75% coverage (priorities 4-5 done)
- **Long-term**: 80%+ coverage

### Test Pyramid

```
       /\
      /E2E\     <- Few (critical user flows)
     /------\
    / Integ \   <- Some (database operations)
   /----------\
  /   Unit     \ <- Many (business logic)
 /--------------\
```

Focus on **unit tests** for business logic (fast, isolated).  
Add **integration tests** for database operations.  
Use **E2E tests** sparingly for critical paths.

---

## Success Metrics

Track these to measure progress:

### Code Quality
- [ ] Test coverage > 60% (Phase 1)
- [ ] Test coverage > 75% (Phase 2)
- [ ] Average entry point < 100 lines
- [ ] All critical modules have 30+ tests

### Developer Productivity
- [ ] Can add new feature in < 1 day
- [ ] Can fix bug in < 1 hour
- [ ] New developer productive in < 1 week
- [ ] Test suite runs in < 5 minutes

### Business Impact
- [ ] Production bugs reduced 70%
- [ ] Feature velocity increased 2x
- [ ] User confidence increased (fewer issues)
- [ ] Can deploy new features weekly

---

## Questions & Answers

### Q: Should I refactor everything at once?
**A**: No! Focus on priorities 1-3 first. They provide the most business value.

### Q: What if I find bugs during refactoring?
**A**: Fix them! But write tests first to ensure they stay fixed.

### Q: How much should I test?
**A**: Aim for 80%+ coverage per module. Test all public methods and edge cases.

### Q: Can I change the existing API?
**A**: Only if absolutely necessary. Maintain backward compatibility. Add new, better APIs alongside old ones if needed.

### Q: What about legacy PHP-Nuke modules?
**A**: Leave them for Phase 4+ unless they pose security risks. Focus on core business logic first.

### Q: How do I handle database changes?
**A**: Use migrations (already set up in `/ibl5/migrations`). Never modify schema directly.

---

## Resources

### Documentation
- [Full Priorities Report](REFACTORING_PRIORITIES_REPORT.md) - Complete analysis
- [Refactoring Summary](REFACTORING_SUMMARY.md) - Overall approach
- [API Development Guide](API_DEVELOPMENT_GUIDE.md) - API best practices
- [Database Schema Guide](DATABASE_SCHEMA_GUIDE.md) - Schema improvements

### Example Refactorings
- [Player Refactoring](PLAYER_REFACTORING_SUMMARY.md) - Facade pattern
- [Team Refactoring](TEAM_REFACTORING_SUMMARY.md) - MVC pattern
- [Waivers Refactoring](REFACTORING_SUMMARY.md#waivers-module) - Complete example
- [Common Repository](COMMON_REPOSITORY_REFACTORING_SUMMARY.md) - DRY principle

### Testing Resources
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- Existing test files in `/ibl5/tests/` - Use as templates
- `MockDatabase` class - For mocking database in tests

---

## Get Started Checklist

Ready to begin? Follow this checklist:

- [ ] Read the [Full Priorities Report](REFACTORING_PRIORITIES_REPORT.md)
- [ ] Review existing refactoring examples (Waivers, Team, Player)
- [ ] Set up development environment with dependencies
- [ ] Choose Priority 1 (Free Agency) as first target
- [ ] Create feature branch: `refactor/free-agency`
- [ ] Follow the step-by-step process above
- [ ] Write tests as you go (not after!)
- [ ] Get code review before merging
- [ ] Celebrate! 🎉 Then move to Priority 2

---

## Need Help?

- Check existing refactoring summaries for patterns
- Review test files for testing examples
- Look at `CommonRepository` for shared functionality
- Use `DatabaseService` for SQL escaping
- Follow SOLID principles consistently

**Remember**: The goal is not perfection, but **testable, maintainable, extensible code**. Focus on business value and developer experience.

---

**Good luck!** 🚀

The codebase is in great shape. With these priorities addressed, it will be ready for rapid feature development with confidence.
