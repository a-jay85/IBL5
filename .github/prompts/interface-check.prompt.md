---
name: interface-compliance-check
description: Validate that implementations match their interface contracts
agent: IBL5-Review
tools: ['search', 'usages']
---

# Interface Compliance Check

Validate that all implementations in `ibl5/classes/` properly implement their interface contracts.

## Check the following for ${input:moduleName:Enter module name to check (e.g., PlayerSearch)}:

### 1. Interface Files Exist
Verify `ibl5/classes/${input:moduleName}/Contracts/` contains:
- All required interface files (`*Interface.php`)
- Comprehensive PHPDoc on each method

### 2. Implementation Compliance
For each class in `ibl5/classes/${input:moduleName}/`:

- [ ] Has `implements InterfaceName` clause
- [ ] Has `@see InterfaceName` class docblock
- [ ] All interface methods are implemented
- [ ] Method signatures EXACTLY match interface (types, nullability)
- [ ] Each method has `@see InterfaceName::methodName()` docblock

### 3. Type Signature Matching
Compare each method:

```php
// Interface declares:
public function findById(int $id): ?array;

// Implementation MUST match exactly:
public function findById(int $id): ?array  // ✅
public function findById($id): array       // ❌ Missing type hints
public function findById(int $id): array   // ❌ Nullability mismatch
```

### 4. Constructor Dependencies
Verify constructors accept appropriate dependencies:
- Database connection (`$db`)
- Other services via dependency injection
- No hidden dependencies (globals, singletons)

## Report Format

```markdown
## Interface Compliance: ${input:moduleName}

### Interfaces Found
- ${input:moduleName}RepositoryInterface.php
- ${input:moduleName}ServiceInterface.php
- ...

### Compliance Status

| Class | Interface | Status | Issues |
|-------|-----------|--------|--------|
| ${input:moduleName}Repository | ${input:moduleName}RepositoryInterface | ✅/❌ | [Issues] |

### Issues Found
[List any signature mismatches, missing implementations, or docblock issues]

### Recommendation
- [ ] Fully compliant
- [ ] Minor fixes needed
- [ ] Significant refactoring required
```

Now check interface compliance for the specified module.
