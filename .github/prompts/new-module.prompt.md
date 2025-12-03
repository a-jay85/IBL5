---
name: new-module-scaffold
description: Scaffold a new IBL5 module with Contracts/ interface pattern
agent: IBL5-Refactoring
tools: ['edit']
---

# Scaffold New Module: ${input:moduleName:Enter module name (e.g., ComparePlayers)}

Create the following directory structure in `ibl5/classes/${input:moduleName}/`:

## Directory Structure

```
${input:moduleName}/
├── Contracts/
│   ├── ${input:moduleName}RepositoryInterface.php
│   ├── ${input:moduleName}ValidatorInterface.php
│   └── ${input:moduleName}ServiceInterface.php
├── ${input:moduleName}Repository.php
├── ${input:moduleName}Validator.php
├── ${input:moduleName}Service.php
├── ${input:moduleName}View.php
└── README.md
```

## Interface Template

Each interface should follow this pattern:

```php
<?php

declare(strict_types=1);

namespace ${input:moduleName}\Contracts;

/**
 * ${input:moduleName}RepositoryInterface - Data access contract
 * 
 * [Description of responsibilities]
 */
interface ${input:moduleName}RepositoryInterface
{
    /**
     * [Method description]
     *
     * @param int $id [Parameter description]
     * @return array [Return description]
     */
    public function findById(int $id): array;
}
```

## Implementation Template

Each implementation should follow this pattern:

```php
<?php

declare(strict_types=1);

namespace ${input:moduleName};

use ${input:moduleName}\Contracts\${input:moduleName}RepositoryInterface;

/**
 * @see ${input:moduleName}RepositoryInterface
 */
class ${input:moduleName}Repository implements ${input:moduleName}RepositoryInterface
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @see ${input:moduleName}RepositoryInterface::findById()
     */
    public function findById(int $id): array
    {
        // Implementation with dual database support
        if (method_exists($this->db, 'sql_escape_string')) {
            // Legacy implementation
        } else {
            // Modern prepared statements
        }
    }
}
```

## Test Directory

Also create `ibl5/tests/${input:moduleName}/` with test files for each class.

## Registration

Add test suite to `ibl5/phpunit.xml`:
```xml
<testsuite name="${input:moduleName} Module Tests">
    <directory>tests/${input:moduleName}</directory>
</testsuite>
```

Now scaffold the complete module structure with interfaces, implementations, and tests.
