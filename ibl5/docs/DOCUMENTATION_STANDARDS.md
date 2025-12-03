# IBL5 Documentation Standards

**Purpose:** Documentation organization, lifecycle, and standards for the IBL5 codebase.  
**When to reference:** Creating documentation, refactoring modules, archiving docs.

---

## Documentation Locations

### 1. Root Directory (Essential Technical Guides Only)
- Place ONLY core technical guides that developers need frequently
- Maximum of 6-8 files to keep root uncluttered
- Examples: README.md, DEVELOPMENT_GUIDE.md, DATABASE_GUIDE.md, API_GUIDE.md
- **DO NOT** place completion summaries, strategic planning docs, or historical reports here

### 2. `ibl5/docs/` (Project Documentation)
- **Purpose**: Strategic planning, historical tracking, testing guides
- **Place here**:
  - Strategic analysis documents (STRATEGIC_PRIORITIES.md)
  - Consolidated refactoring history (REFACTORING_HISTORY.md)
  - Testing best practices (TEST_REFACTORING_SUMMARY.md)
  - Process documentation
  - Planning documents
- **DO NOT** place component-specific or module-specific docs here

### 3. Component READMEs (With Code)
- **Purpose**: Document specific classes, modules, or features
- **Location**: Next to the code they document
- Examples:
  - `ibl5/classes/Player/README.md` - Player module architecture
  - `ibl5/classes/Statistics/README.md` - StatsFormatter usage
  - `ibl5/classes/DepthChart/SECURITY.md` - Security patterns
  - `ibl5/tests/Trading/README.md` - Trading test documentation
- **When to create**: When refactoring a module or creating a new class
- **Keep updated**: Update when module architecture changes

### 4. `.archive/` (Historical Documents)
- **Purpose**: Preserve completed work and superseded documentation
- **Place here**:
  - Completed refactoring summaries (after consolidating into REFACTORING_HISTORY.md)
  - Superseded guides or plans
  - Historical completion reports
- **DO NOT** delete historical docs - archive them for reference

---

## Documentation Lifecycle

### When Creating New Documentation

**1. Refactoring a Module:**
- Create detailed completion summary initially
- After review, consolidate key points into `ibl5/docs/REFACTORING_HISTORY.md`
- Move detailed summary to `.archive/`
- Create component README in module directory (`ibl5/classes/Module/README.md`)

**2. Strategic Planning:**
- Create in `ibl5/docs/` directory
- Link from main README.md or DEVELOPMENT_GUIDE.md
- Update `ibl5/docs/README.md` index

**3. Technical Guides:**
- Create in root directory only if essential and frequently referenced
- Consider if content belongs in existing guide instead
- Update main README.md navigation section

**4. Component Documentation:**
- Create README.md in the class/module directory
- Keep focused on that specific component
- Link from root documentation if important

### When Updating Documentation

1. **Check all cross-references** - Update links in related documents
2. **Update the index** - Modify `ibl5/docs/README.md` if adding/moving docs
3. **Verify links** - Test all internal links work correctly
4. **Update timestamps** - Add "Last Updated" date if present

### When Archiving Documentation

1. **Consolidate first** - Extract key information into permanent docs
2. **Move to `.archive/`** - Don't delete, preserve for reference
3. **Update references** - Remove links from active docs, note archive location
4. **Add to archive index** - Document what was archived and why

---

## Documentation Standards

### File Naming
- Use SCREAMING_SNAKE_CASE for guide documents (DEVELOPMENT_GUIDE.md)
- Use README.md for directory/component documentation
- Use descriptive names (REFACTORING_HISTORY.md, not HISTORY.md)

### File Location Rules
- ✅ **DO**: Create consolidated history in `ibl5/docs/`
- ✅ **DO**: Keep component docs with their code
- ✅ **DO**: Archive completed summaries
- ❌ **DON'T**: Scatter completion summaries in root
- ❌ **DON'T**: Create redundant or overlapping docs
- ❌ **DON'T**: Delete historical documentation

### Content Structure
- Start with purpose/overview
- Include "Last Updated" date for living documents
- Use consistent markdown formatting
- Include navigation links to related docs
- Add examples where helpful

### Cross-References
- Use relative paths: `../DEVELOPMENT_GUIDE.md` or `ibl5/docs/REFACTORING_HISTORY.md`
- Test all links before committing
- Update all references when moving files

---

## Quick Decision Tree

**Creating new documentation? Ask:**

1. **Is this a completion summary for a refactored module?**
   - Initial: Create detailed summary
   - After review: Consolidate into `ibl5/docs/REFACTORING_HISTORY.md`
   - Move detailed version to `.archive/`

2. **Is this strategic planning or process documentation?**
   - Place in `ibl5/docs/`
   - Update `ibl5/docs/README.md` index

3. **Is this about a specific class or module?**
   - Create README.md in that module's directory
   - Example: `ibl5/classes/YourModule/README.md`

4. **Is this an essential technical guide?**
   - Only add to root if truly essential
   - Otherwise, add to `ibl5/docs/` or expand existing guide

5. **Is this superseded or historical?**
   - Move to `.archive/`
   - Update any references

---

## Documentation Index

**Always maintain** `ibl5/docs/README.md` as the comprehensive documentation index. When adding documentation:
1. Add entry to appropriate section
2. Include brief description
3. Ensure link works
4. Commit index update with the new doc

---

## Example Workflow: Refactoring "FreeAgency" Module

**1. Initial Summary** (during/after refactoring):
- Create `FREE_AGENCY_REFACTORING_SUMMARY.md` in root (temporary)
- Document all changes, architecture, improvements
- Include in PR for review

**2. After PR Merge**:
- Add key points to `ibl5/docs/REFACTORING_HISTORY.md` under "Completed Refactorings"
- Create `ibl5/classes/FreeAgency/README.md` for component architecture
- Move `FREE_AGENCY_REFACTORING_SUMMARY.md` to `.archive/`
- Update `ibl5/docs/README.md` index
- Update `DEVELOPMENT_GUIDE.md` to mark FreeAgency as complete

**3. Update References**:
- Verify links in README.md
- Check copilot-instructions.md examples if relevant
- Ensure `ibl5/docs/README.md` is current

---

## Documentation Updates During Refactoring (PR Checklist)

**Documentation MUST be updated incrementally during the refactoring PR, not after merge.**

**After completing each component (Repository/Service/View/etc.):**

1. ✅ Update `STRATEGIC_PRIORITIES.md` - Mark module as complete with brief summary
2. ✅ Update `REFACTORING_HISTORY.md` - Add entry to "Completed Refactorings" section
3. ✅ Create component README.md - In `ibl5/classes/ModuleName/README.md`
4. ✅ Update documentation cross-references - Fix any links in related docs
5. ✅ Verify all links work - Test internal documentation links before finalizing

**Verification Checklist Before PR Review:**

- [ ] `STRATEGIC_PRIORITIES.md` updated with module completion summary
- [ ] `REFACTORING_HISTORY.md` updated with detailed refactoring section
- [ ] Component README.md created in `ibl5/classes/ModuleName/`
- [ ] `DEVELOPMENT_GUIDE.md` updated (refactoring count, status)
- [ ] `ibl5/docs/README.md` updated if new docs created
- [ ] All internal documentation links verified and working
- [ ] No "TODO" comments about documentation left in code or docs
- [ ] Test suite registered in `ibl5/phpunit.xml`
- [ ] All tests passing without warnings or errors

**DO NOT deviate from this structure** - consistency is critical for Copilot Agent effectiveness.
