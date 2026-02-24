<?php

declare(strict_types=1);

namespace YourAccount;

use Utilities\CsrfGuard;
use Utilities\HtmlSanitizer;

/**
 * Renders Breeze-inspired auth pages: Login, Register, Forgot Password,
 * Activation status, Logout, and error/status pages.
 *
 * All pages share a centered card layout using .auth-page > .auth-card.ibl-card
 * with the existing IBL5 design system components.
 */
class YourAccountView
{
    /**
     * Render the basketball logo icon shown above auth cards.
     */
    private function renderLogo(): string
    {
        return '<div class="auth-logo">'
            . '<div class="auth-logo__icon">'
            . '<svg viewBox="0 0 24 24" fill="currentColor">'
            . '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/>'
            . '<path d="M12 2C12 12 12 12 12 22" stroke="currentColor" stroke-width="1.5"/>'
            . '<path d="M2 12C12 12 12 12 22 12" stroke="currentColor" stroke-width="1.5"/>'
            . '<path d="M4.5 4.5C8 8 8 16 4.5 19.5" stroke="currentColor" stroke-width="1.5" fill="none"/>'
            . '<path d="M19.5 4.5C16 8 16 16 19.5 19.5" stroke="currentColor" stroke-width="1.5" fill="none"/>'
            . '</svg>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render a user icon SVG for input fields.
     */
    private function userIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'
            . '</svg>';
    }

    /**
     * Render a lock icon SVG for password fields.
     */
    private function lockIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>'
            . '</svg>';
    }

    /**
     * Render an email icon SVG for email fields.
     */
    private function emailIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>'
            . '</svg>';
    }

    /**
     * Render a key/code icon SVG for confirmation code fields.
     */
    private function keyIcon(): string
    {
        return '<svg class="auth-input__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>'
            . '</svg>';
    }

    /**
     * Render a checkmark icon for success status.
     */
    private function checkIcon(): string
    {
        return '<svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
            . '</svg>';
    }

    /**
     * Render an X icon for error status.
     */
    private function errorIcon(): string
    {
        return '<svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'
            . '</svg>';
    }

    /**
     * Render an info icon for informational status.
     */
    private function infoIcon(): string
    {
        return '<svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
            . '</svg>';
    }

    /**
     * Render the CAPTCHA section for forms.
     */
    private function renderCaptchaSection(int $randomNum): string
    {
        return '<div class="ibl-form-group">'
            . '<label class="ibl-label" for="auth-gfx-check">Security Code</label>'
            . '<div style="margin-bottom: var(--space-2);">'
            . '<img src="?gfx=gfx&amp;random_num=' . $randomNum . '" alt="Security Code" style="border: 1px solid var(--gray-300); border-radius: var(--radius-md);">'
            . '</div>'
            . '<div class="auth-input-wrapper">'
            . $this->keyIcon()
            . '<input type="text" name="gfx_check" id="auth-gfx-check" class="ibl-input auth-input--with-icon" size="7" maxlength="6" required placeholder="Enter security code">'
            . '</div>'
            . '<input type="hidden" name="random_num" value="' . $randomNum . '">'
            . '</div>';
    }

    /**
     * Render the login page.
     *
     * @param string|null $error Error message to display, or null for no error
     * @param int $randomNum Random number for CAPTCHA
     * @param bool $showCaptcha Whether to show the CAPTCHA field
     */
    public function renderLoginPage(?string $error, int $randomNum, bool $showCaptcha): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <?= $this->renderLogo() ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Sign In</h1>
            <p class="ibl-card__subtitle">Welcome back to IBL</p>
        </div>
        <div class="ibl-card__body">
            <?php if ($error !== null): ?>
                <div class="ibl-alert ibl-alert--error">
                    <?php $sanitizedError = \Utilities\HtmlSanitizer::safeHtmlOutput($error); ?>
                    <?= nl2br($sanitizedError) ?>
                </div>
            <?php endif; ?>

            <form action="modules.php?name=YourAccount" method="post">
                <div class="ibl-form-group">
                    <label class="ibl-label" for="login-username">Username</label>
                    <div class="auth-input-wrapper">
                        <?= $this->userIcon() ?>
                        <input type="text" name="username" id="login-username" class="ibl-input auth-input--with-icon" maxlength="25" required placeholder="Enter your username" autocomplete="username">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label" for="login-password">Password</label>
                    <div class="auth-input-wrapper">
                        <?= $this->lockIcon() ?>
                        <input type="password" name="user_password" id="login-password" class="ibl-input auth-input--with-icon" maxlength="20" required placeholder="Enter your password" autocomplete="current-password">
                    </div>
                </div>

                <?php if ($showCaptcha): ?>
                    <?= $this->renderCaptchaSection($randomNum) ?>
                <?php endif; ?>

                <div class="ibl-form-group">
                    <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                        <input type="checkbox" name="remember_me" value="1">
                        <span style="font-size: 1rem; color: var(--gray-600);">Remember me</span>
                    </label>
                </div>

                <?= CsrfGuard::generateToken('login') ?>
                <input type="hidden" name="op" value="login">
                <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">Sign In</button>
            </form>
        </div>
        <div class="ibl-card__footer">
            <div class="auth-links">
                <a href="modules.php?name=YourAccount&amp;op=pass_lost">Forgot password?</a>
                <span class="auth-links__divider">|</span>
                <a href="modules.php?name=YourAccount&amp;op=new_user">Create an account</a>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the registration page.
     *
     * Posts directly to op=finish (single-step registration). delight-auth
     * provides built-in throttling, so no CAPTCHA is needed.
     */
    public function renderRegisterPage(): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <?= $this->renderLogo() ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Create Account</h1>
            <p class="ibl-card__subtitle">Join the IBL community</p>
        </div>
        <div class="ibl-card__body">
            <form action="modules.php?name=YourAccount" method="post">
                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="register-username">Username</label>
                    <div class="auth-input-wrapper">
                        <?= $this->userIcon() ?>
                        <input type="text" name="username" id="register-username" class="ibl-input auth-input--with-icon" maxlength="25" required placeholder="Choose a username" autocomplete="username">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="register-email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <?= $this->emailIcon() ?>
                        <input type="email" name="user_email" id="register-email" class="ibl-input auth-input--with-icon" maxlength="255" required placeholder="Enter your email" autocomplete="email">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label" for="register-password">Password</label>
                    <div class="auth-input-wrapper">
                        <?= $this->lockIcon() ?>
                        <input type="password" name="user_password" id="register-password" class="ibl-input auth-input--with-icon" maxlength="40" placeholder="Choose a password" autocomplete="new-password">
                    </div>
                    <div class="ibl-form-help">Leave blank to auto-generate a password.</div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label" for="register-password2">Confirm Password</label>
                    <div class="auth-input-wrapper">
                        <?= $this->lockIcon() ?>
                        <input type="password" name="user_password2" id="register-password2" class="ibl-input auth-input--with-icon" maxlength="40" placeholder="Re-enter your password" autocomplete="new-password">
                    </div>
                </div>

                <?= CsrfGuard::generateToken('register') ?>
                <input type="hidden" name="op" value="finish">
                <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">Create Account</button>
            </form>

            <div style="margin-top: var(--space-4); font-size: 1rem; color: var(--gray-500); line-height: 1.5; text-align: center;">
                You will receive an email with an activation link to complete your registration.
            </div>
        </div>
        <div class="ibl-card__footer">
            <div class="auth-links">
                <a href="modules.php?name=YourAccount">Already have an account? Sign in</a>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the registration complete page (email sent, check inbox).
     */
    public function renderRegistrationCompletePage(string $siteName): string
    {
        $safeSiteName = HtmlSanitizer::safeHtmlOutput($siteName);

        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--success">
                    <?= $this->checkIcon() ?>
                </div>
                <div class="auth-status__title"><?= $safeSiteName ?> Account Created</div>
                <div class="auth-status__message">
                    An activation email has been sent.<br><br>
                    Please click the link in your email to activate your account.<br><br>
                    Thanks for joining!
                </div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount" class="ibl-btn ibl-btn--primary">Sign In</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a registration error page.
     */
    public function renderRegistrationErrorPage(string $error): string
    {
        $safeError = HtmlSanitizer::safeHtmlOutput($error);

        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Registration Error</h1>
        </div>
        <div class="ibl-card__body">
            <div class="ibl-alert ibl-alert--error"><?= $safeError ?></div>
            <div class="auth-links">
                <a href="modules.php?name=YourAccount&amp;op=new_user">Try again</a>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the forgot password page (email-based reset via delight-auth).
     */
    public function renderForgotPasswordPage(): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <?= $this->renderLogo() ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Reset Password</h1>
            <p class="ibl-card__subtitle">We'll email you a reset link</p>
        </div>
        <div class="ibl-card__body">
            <div style="margin-bottom: var(--space-4); font-size: 1rem; color: var(--gray-600); line-height: 1.5;">
                Enter your email address and we'll send you a link to reset your password.
            </div>

            <form action="modules.php?name=YourAccount" method="post">
                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="reset-email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <?= $this->emailIcon() ?>
                        <input type="email" name="user_email" id="reset-email" class="ibl-input auth-input--with-icon" maxlength="255" required placeholder="Enter your email address" autocomplete="email">
                    </div>
                </div>

                <?= CsrfGuard::generateToken('forgot_password') ?>
                <input type="hidden" name="op" value="mailpasswd">
                <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">Send Reset Link</button>
            </form>
        </div>
        <div class="ibl-card__footer">
            <div class="auth-links">
                <a href="modules.php?name=YourAccount">Remember your password? Sign in</a>
                <span class="auth-links__divider">|</span>
                <a href="modules.php?name=YourAccount&amp;op=new_user">Need an account?</a>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the "reset email sent" status page.
     *
     * Generic message for security — does not reveal whether the email exists.
     */
    public function renderResetEmailSentPage(): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--info">
                    <?= $this->infoIcon() ?>
                </div>
                <div class="auth-status__title">Check Your Email</div>
                <div class="auth-status__message">
                    If an account exists with that email address, we've sent a password reset link. Please check your inbox and follow the instructions.
                </div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount" class="ibl-btn ibl-btn--primary">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the reset password form (selector/token from email link).
     */
    public function renderResetPasswordPage(string $selector, string $token): string
    {
        $safeSelector = HtmlSanitizer::safeHtmlOutput($selector);
        $safeToken = HtmlSanitizer::safeHtmlOutput($token);

        ob_start();
        ?>
<div class="auth-page">
    <?= $this->renderLogo() ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Reset Password</h1>
            <p class="ibl-card__subtitle">Enter your new password</p>
        </div>
        <div class="ibl-card__body">
            <form action="modules.php?name=YourAccount" method="post">
                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="reset-new-password">New Password</label>
                    <div class="auth-input-wrapper">
                        <?= $this->lockIcon() ?>
                        <input type="password" name="new_password" id="reset-new-password" class="ibl-input auth-input--with-icon" maxlength="60" required placeholder="Enter new password" autocomplete="new-password">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="reset-confirm-password">Confirm Password</label>
                    <div class="auth-input-wrapper">
                        <?= $this->lockIcon() ?>
                        <input type="password" name="new_password2" id="reset-confirm-password" class="ibl-input auth-input--with-icon" maxlength="60" required placeholder="Re-enter new password" autocomplete="new-password">
                    </div>
                </div>

                <input type="hidden" name="selector" value="<?= $safeSelector ?>">
                <input type="hidden" name="token" value="<?= $safeToken ?>">
                <?= CsrfGuard::generateToken('reset_password') ?>
                <input type="hidden" name="op" value="do_reset_password">
                <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">Reset Password</button>
            </form>
        </div>
        <div class="ibl-card__footer">
            <div class="auth-links">
                <a href="modules.php?name=YourAccount">Back to Sign In</a>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the password reset success page.
     */
    public function renderPasswordResetSuccessPage(): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--success">
                    <?= $this->checkIcon() ?>
                </div>
                <div class="auth-status__title">Password Changed</div>
                <div class="auth-status__message">
                    Your password has been reset successfully. You can now sign in with your new password.
                </div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount" class="ibl-btn ibl-btn--primary">Sign In</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a password reset error page.
     */
    public function renderPasswordResetErrorPage(string $error): string
    {
        $safeError = HtmlSanitizer::safeHtmlOutput($error);

        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--error">
                    <?= $this->errorIcon() ?>
                </div>
                <div class="auth-status__title">Reset Error</div>
                <div class="auth-status__message"><?= $safeError ?></div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount&amp;op=pass_lost" class="ibl-btn ibl-btn--primary">Try Again</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the activation success page.
     */
    public function renderActivationSuccessPage(string $username): string
    {
        $safeUsername = HtmlSanitizer::safeHtmlOutput($username);

        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--success">
                    <?= $this->checkIcon() ?>
                </div>
                <div class="auth-status__title">Account Activated</div>
                <div class="auth-status__message">
                    <strong><?= $safeUsername ?></strong>, your account has been activated successfully. You can now sign in.
                </div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount" class="ibl-btn ibl-btn--primary">Sign In</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render the activation error page.
     *
     * @param string $errorType One of 'mismatch' or 'expired'
     */
    public function renderActivationErrorPage(string $errorType): string
    {
        if ($errorType === 'mismatch') {
            $message = 'The activation code does not match. Please check your email and try again.';
        } else {
            $message = 'This activation link has expired or is invalid. Registration links expire after 24 hours. Please register again.';
        }

        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--error">
                    <?= $this->errorIcon() ?>
                </div>
                <div class="auth-status__title">Activation Error</div>
                <div class="auth-status__message"><?= $message ?></div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount&amp;op=new_user" class="ibl-btn ibl-btn--primary">Register Again</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a "user not found" error page for password reset.
     */
    public function renderUserNotFoundPage(): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--error">
                    <?= $this->errorIcon() ?>
                </div>
                <div class="auth-status__title">User Not Found</div>
                <div class="auth-status__message">
                    Sorry, no user was found with that username. Please check your spelling and try again.
                </div>
                <div class="auth-status__action">
                    <a href="modules.php?name=YourAccount&amp;op=pass_lost" class="ibl-btn ibl-btn--primary">Try Again</a>
                </div>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
