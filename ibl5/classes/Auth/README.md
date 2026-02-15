# Auth Module

## Overview

The Auth module handles user authentication for IBL5, wrapping the [delight-im/auth](https://github.com/delight-im/PHP-Auth) library behind the existing `AuthServiceInterface`. This provides modern auth features while maintaining backward compatibility with 100+ legacy PHP-Nuke callsites.

## Architecture

```
Auth/
├── Contracts/
│   └── AuthServiceInterface.php  # Interface for all auth operations
├── AuthService.php               # Implementation wrapping delight-im/auth
└── README.md                     # This file

Database/
└── PdoConnection.php             # PDO singleton for delight-im/auth
```

## Features

| Feature | Status | Notes |
|---------|--------|-------|
| Login (username/password) | Active | Via `attempt()` with bcrypt verification |
| Registration | Active | Via `register()` with email verification |
| Email verification | Active | Via `confirmEmail()` with selector/token |
| Password reset | Active | Via `forgotPassword()` / `resetPassword()` |
| Remember me | Active | 30-day cookie via `attempt($user, $pass, $duration)` |
| Login throttling | Active | Built into delight-im/auth |
| Admin role checking | Active | Via `isAdmin()` checking `Role::ADMIN` bitmask |
| CSRF protection | Active | Via `CsrfGuard` on all auth forms |
| Legacy `$cookie` compat | Active | Via `getCookieArray()` |
| Legacy `$userinfo` compat | Active | Via `getUserInfo()` |

## Database Tables

Authentication data is stored in `auth_*` tables (managed by delight-im/auth):

| Table | Purpose |
|-------|---------|
| `auth_users` | Core accounts (email, password, roles_mask) |
| `auth_users_confirmations` | Email verification tokens |
| `auth_users_remembered` | Remember-me tokens |
| `auth_users_resets` | Password reset tokens |
| `auth_users_throttling` | Login rate limiting |

Profile data remains in `nuke_users`. IDs match 1:1 between `auth_users.id` and `nuke_users.user_id`.

## Admin Roles

Admin status is determined by the `roles_mask` column in `auth_users`:

```php
// Role::ADMIN = 1 (bit 0)
$authService->isAdmin(); // checks if current user has ADMIN role
```

The `nuke_authors` table is deprecated — admin authentication now goes through the same `AuthService` as regular users, with an additional role check.

## Legacy Compatibility

The following global functions continue to work unchanged:

| Function | Behavior |
|----------|----------|
| `is_user($user)` | Delegates to `$authService->isAuthenticated()` |
| `is_admin($admin)` | Delegates to `$authService->isAdmin()` |
| `cookiedecode($user)` | Delegates to `$authService->getCookieArray()` |
| `getusrinfo($user)` | Delegates to `$authService->getUserInfo()` |

## Migration

See `migrations/034_create_auth_tables.sql` and `migrations/035_migrate_users_to_auth.sql` for the data migration steps.
