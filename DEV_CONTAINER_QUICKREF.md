# Dev Container Quick Reference

## What is a Dev Container?

A **Dev Container** is a pre-configured Docker container that contains:
- **OS**: Ubuntu 22.04 LTS
- **PHP**: 8.3 (with required extensions)
- **Composer**: Latest version
- **All Dependencies**: PHPUnit, PHPStan, PHP_CodeSniffer, etc.

When you open the IBL5 workspace in VS Code, it automatically:
1. Detects `.devcontainer/devcontainer.json`
2. Starts the container
3. Runs `.devcontainer/post-create.sh` (one time)
4. Makes everything available for development

## Quick Start for GitHub Copilot Agent

### First Time Only

When workspace opens for the first time, VS Code shows:

```
Notification: "Folder contains a Dev Container configuration file."
Button: "Reopen in Container"
```

Click the button or press:
```
Cmd+Shift+P → "Dev Containers: Reopen in Container"
```

Then wait 2-3 minutes for:
- Container image to download
- Container to start
- `post-create.sh` to run (composer install)

Once complete, you'll see:
```
✅ Development environment setup complete!
```

### Every Run After That

Container restarts with all dependencies already cached (< 30 seconds).

## Running Tests

### Simple

```bash
cd ibl5
phpunit
```

### Specific Test Suite

```bash
cd ibl5
phpunit tests/Player/
phpunit tests/Team/
```

### Specific Test

```bash
cd ibl5
phpunit --filter testRenderPlayerHeader
```

### With Options

```bash
cd ibl5
phpunit --testdox              # Verbose output
phpunit --no-coverage          # Skip coverage
phpunit --colors=always        # Colored output
phpunit --display-all-issues   # Show all issues
```

## Other Commands

### Static Analysis

```bash
cd ibl5
composer analyse               # Run PHPStan
```

### Code Style

```bash
cd ibl5
composer lint:php              # Check PSR-12 compliance
composer lint:php:fix          # Auto-fix style issues
```

### Composer

```bash
cd ibl5
composer install               # Install dependencies (already done)
composer update                # Update packages
composer show                  # List installed packages
```

## Container Troubleshooting

### Container won't start

```bash
# Rebuild the container
Cmd+Shift+P → "Dev Containers: Rebuild Container"
# Takes 2-3 minutes
```

### Need to SSH into container

```bash
# Open new terminal and it automatically connects to container
Cmd+` # (backtick)
```

### Container is slow

```bash
# Clear VS Code cache
Cmd+Shift+P → "Dev Containers: Clean Up Dev Containers"
```

### Need to reinstall dependencies

```bash
# Inside container terminal
cd ibl5
composer install --no-cache
```

### Want to exit dev container (go back to local)

```bash
Cmd+Shift+P → "Dev Containers: Reopen Folder Locally"
```

## File Locations in Container

```
/workspaces/IBL5/              ← Repository root
├── .devcontainer/             ← Container config
├── ibl5/                       ← Project root
│   ├── vendor/                 ← Composer packages
│   ├── vendor/bin/phpunit      ← Executable
│   └── tests/                  ← Test files
└── setup-dev.sh                ← Local setup (not needed in container)
```

## Key Environment Variables

The container automatically sets:
- `PHP_VERSION=8.3`
- `COMPOSER_ALLOW_SUPERUSER=1` (allows root install)
- `DEBIAN_FRONTEND=noninteractive` (for automated installs)

## What's Pre-Installed

The container automatically installs:
- PHP 8.3 with extensions: `mbstring`, `intl`, `pdo`, `pdo_mysql`
- Composer (latest)
- PHPUnit 12.4.3+
- PHPStan 2.1+
- PHP_CodeSniffer 4.0+
- Git

## Performance Tips

1. **On first run**: Allow 2-3 minutes for full setup
2. **On subsequent runs**: Takes < 30 seconds to start
3. **Running tests**: Same speed as local PHP (tests complete in ~2 minutes)
4. **Slow disk?**: Move Docker to faster SSD if available

## Limits

The container has:
- **Storage**: Inherited from your Docker installation (~100GB typical)
- **Memory**: Uses available system RAM
- **CPU**: Uses available cores

If you run out of resources:
```bash
# Clean up unused containers
docker system prune

# Remove all IBL5 containers
docker ps -a | grep IBL5 | awk '{print $1}' | xargs docker rm
```

## Switching Between Environments

You can work in both local and container simultaneously:

```
Local Development:
  bash setup-dev.sh
  cd ibl5 && phpunit

Container Development:
  Cmd+Shift+P → "Reopen in Container"
  cd ibl5 && phpunit
```

Both use the same code (they share the filesystem).

## Official Documentation

- [VS Code Dev Containers](https://code.visualstudio.com/docs/devcontainers/containers)
- [Dev Container Spec](https://containers.dev/)
- [Docker Documentation](https://docs.docker.com/)

## Getting Help

Check these files in order:
1. This file (`DEV_CONTAINER_QUICKREF.md`)
2. `DEVELOPMENT_ENVIRONMENT.md` (comprehensive guide)
3. `COPILOT_SETUP_SOLUTION.md` (why this setup exists)
4. `.github/copilot-instructions.md` (coding standards)

## Quick Command Reference

| Task | Command |
|------|---------|
| Run all tests | `phpunit` |
| Run specific suite | `phpunit tests/Player/` |
| Run with verbose output | `phpunit --testdox` |
| Run static analysis | `composer analyse` |
| Check code style | `composer lint:php` |
| Fix code style | `composer lint:php:fix` |
| Install dependencies | `composer install` |
| Verify setup | `vendor/bin/phpunit --version` |
| Open new terminal | `Cmd+`` (backtick) |
| Rebuild container | `Cmd+Shift+P` → "Dev Containers: Rebuild Container" |
| Reopen locally | `Cmd+Shift+P` → "Dev Containers: Reopen Folder Locally" |

## Summary

✅ Container handles all setup automatically
✅ First run: ~2-3 minutes
✅ Subsequent runs: ~30 seconds
✅ All tools ready immediately
✅ Run tests anytime: `phpunit`
✅ Works offline (dependencies cached)

That's it! You're ready to develop! 🚀
