<?php

declare(strict_types=1);

namespace YourAccount;

/**
 * Renders Breeze-inspired auth pages: Login, Register, Forgot Password,
 * Activation status, Logout, and error/status pages.
 *
 * All pages share a centered card layout using .auth-page > .auth-card.ibl-card
 * with the existing IBL5 design system components.
 *
 * Thin delegating facade over the per-flow sub-views: LoginView, RegistrationView,
 * PasswordResetView, ActivationView. Keeps the public API and constructor signature
 * unchanged so consumers (e.g. modules/YourAccount/index.php) require no changes.
 */
class YourAccountView implements Contracts\YourAccountViewInterface
{
    private LoginView $loginView;
    private RegistrationView $registrationView;
    private PasswordResetView $passwordResetView;
    private ActivationView $activationView;

    public function __construct()
    {
        $this->loginView = new LoginView();
        $this->registrationView = new RegistrationView();
        $this->passwordResetView = new PasswordResetView();
        $this->activationView = new ActivationView();
    }

    /**
     * Render the login page with an optional error message.
     */
    public function renderLoginPage(?string $error): string
    {
        return $this->loginView->renderLoginPage($error);
    }

    /**
     * Render the new user registration form.
     */
    public function renderRegisterPage(): string
    {
        return $this->registrationView->renderRegisterPage();
    }

    /**
     * Render the registration success page (email sent confirmation).
     */
    public function renderRegistrationCompletePage(string $siteName): string
    {
        return $this->registrationView->renderRegistrationCompletePage($siteName);
    }

    /**
     * Render a registration error page.
     */
    public function renderRegistrationErrorPage(string $error): string
    {
        return $this->registrationView->renderRegistrationErrorPage($error);
    }

    /**
     * Render the forgot password form.
     */
    public function renderForgotPasswordPage(): string
    {
        return $this->passwordResetView->renderForgotPasswordPage();
    }

    /**
     * Render the "check your email" page after a password reset request.
     */
    public function renderResetEmailSentPage(): string
    {
        return $this->passwordResetView->renderResetEmailSentPage();
    }

    /**
     * Render the password reset form with hidden selector/token fields.
     */
    public function renderResetPasswordPage(string $selector, string $token): string
    {
        return $this->passwordResetView->renderResetPasswordPage($selector, $token);
    }

    /**
     * Render the password reset success page.
     */
    public function renderPasswordResetSuccessPage(): string
    {
        return $this->passwordResetView->renderPasswordResetSuccessPage();
    }

    /**
     * Render a password reset error page.
     */
    public function renderPasswordResetErrorPage(string $error): string
    {
        return $this->passwordResetView->renderPasswordResetErrorPage($error);
    }

    /**
     * Render the email activation success page.
     */
    public function renderActivationSuccessPage(string $username): string
    {
        return $this->activationView->renderActivationSuccessPage($username);
    }

    /**
     * Render an activation error page.
     *
     * @param string $errorType Either 'mismatch' or 'expired'
     */
    public function renderActivationErrorPage(string $errorType): string
    {
        return $this->activationView->renderActivationErrorPage($errorType);
    }
}
