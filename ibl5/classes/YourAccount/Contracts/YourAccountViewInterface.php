<?php

declare(strict_types=1);

namespace YourAccount\Contracts;

/**
 * Contract for YourAccount HTML rendering.
 *
 * Renders authentication pages: login, registration, password reset,
 * email confirmation, and status/error pages.
 */
interface YourAccountViewInterface
{
    /**
     * Render the login page with an optional error message.
     */
    public function renderLoginPage(?string $error): string;

    /**
     * Render the new user registration form.
     */
    public function renderRegisterPage(): string;

    /**
     * Render the registration success page (email sent confirmation).
     */
    public function renderRegistrationCompletePage(string $siteName): string;

    /**
     * Render a registration error page.
     */
    public function renderRegistrationErrorPage(string $error): string;

    /**
     * Render the forgot password form.
     */
    public function renderForgotPasswordPage(): string;

    /**
     * Render the "check your email" page after a password reset request.
     */
    public function renderResetEmailSentPage(): string;

    /**
     * Render the password reset form with hidden selector/token fields.
     */
    public function renderResetPasswordPage(string $selector, string $token): string;

    /**
     * Render the password reset success page.
     */
    public function renderPasswordResetSuccessPage(): string;

    /**
     * Render a password reset error page.
     */
    public function renderPasswordResetErrorPage(string $error): string;

    /**
     * Render the email activation success page.
     */
    public function renderActivationSuccessPage(string $username): string;

    /**
     * Render an activation error page.
     *
     * @param string $errorType Either 'mismatch' or 'expired'
     */
    public function renderActivationErrorPage(string $errorType): string;
}
