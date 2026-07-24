---
description: Account management workflows: registration, login, password reset, activation, and the GM account dashboard.
last_verified: 2026-07-24
---

# YourAccount

Provides all GM account management flows through `YourAccountService`, which delegates to `AuthService` for registration, login, password reset, and account activation. Each flow has a dedicated view class: `RegistrationView`, `LoginView`, `PasswordResetView`, and `ActivationView`. `YourAccountView` renders the main account dashboard for authenticated GMs. Entry point: `ibl5/modules/YourAccount/index.php`.

| Class | Role |
|---|---|
| `YourAccountService` | Orchestrates registration, login, reset, and activation |
| `YourAccountRepository` | Database access for account data |
| `YourAccountView` | Renders the authenticated GM dashboard |
| `RegistrationView` | Renders the registration form |
| `LoginView` | Renders the login form |
| `PasswordResetView` | Renders the password reset form |
| `ActivationView` | Renders the account activation flow |
| `YourAccountIcons` | Icon constants used across account views |
