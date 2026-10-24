---
description: Module registry and access-control logic for URL routing and season-phase availability.
last_verified: 2026-10-04
---

# Module

Controls which application modules are accessible at any given time. `ModuleRegistry` is the canonical list of valid module names used by the URL router. `ModuleAccessControl` derives module availability from the current season phase, site settings (e.g., trivia mode), and league context (IBL vs Olympics), ensuring modules are only reachable when appropriate.

## Module composition

`ibl5/modules.php` publishes one request-scoped `Module\ModuleServices` after the page-cache exit and before `ModuleAccessControl`. A module `index.php` holds its file guard, its auth/CSRF gates, its input parsing and one wiring call. It never uses `new` and never reads `$mysqli_db`. `ibl5/tests/Module/ModuleCompositionRootRatchetTest.php` enforces both, and `ibl5/tests/Security/ModuleFactorySecuritySurfaceTest.php` keeps factories free of auth and request state.

Converted shape:

```php
if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}
$module_name = basename(dirname(__FILE__));
global $user, $authService;            // only globals the guards and input lines still read
$pagetitle = "- Team Pages";
cookiedecode($user);
// guards and input parsing: unchanged, above the wiring call
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\WaiversFactory::class);
$factory->controller()->handleWaiverRequest($user);
```

Conversion recipe, one module at a time:

1. List every `new X(...)` and `\Http\HttpRequest::fromGlobals()` in `index.php`, including those inside `case` arms, `if` branches and functions declared in the file.
2. Create `ibl5/classes/Module/Factories/<ModuleDir>Factory.php`: `final class <ModuleDir>Factory implements \Module\Contracts\ModuleFactoryInterface` with `public function __construct(private readonly \Module\ModuleServices $services) {}`.
3. Move each construction into the factory. Give it one public method per object `index.php` uses, named by role (`controller()`, `view()`, `service()`, `apiHandler()`), and private methods for intermediates. Keep constructor argument order and values identical.
4. Swap shared collaborators: `$mysqli_db` → `$this->services->db()`; `new Repositories\TeamIdentityRepository($mysqli_db)` → `teamIdentity()`; `SalaryCapRepository` → `salaryCap()`; `PlayerLookupRepository` → `playerLookup()`; `new Season\Season($mysqli_db)` → `season()`; `new Utilities\NukeCompat()` → `nukeCompat()`; `\Http\HttpRequest::fromGlobals()` → `request()`; an `$authService` constructor argument → `auth()`; a `$leagueContext` argument → `leagueContext()`.
5. In `index.php`, replace the construction lines with one `$factory = \Module\ModuleServices::current()->factory(...)` line at the position of the first removed `new`, never above any guard. An object built inside a branch stays lazy: call its factory method inside that same branch. Functions declared in `index.php` call `\Module\ModuleServices::current()` in their own body.
6. A shared object that `index.php` itself still reads (a repository a guard calls) is assigned under its existing variable name: `$commonRepo = \Module\ModuleServices::current()->teamIdentity();`.
7. Drop `$mysqli_db` from the `global` line, and the line itself if nothing remains. Every guard, CSRF check, `isAdmin`/`is_admin` branch, `$_POST`/`$_GET` read and output line stays byte-identical.
8. Remove the module from `ModuleCompositionRootRatchetTest::ALLOWLIST`.
9. Never put a superglobal, `$GLOBALS`, `global`, `is_admin()`, an `AuthServiceInterface` method call, or an acting-user parameter in a factory.
