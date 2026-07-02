<?php

declare(strict_types=1);

namespace YourAccount;

use Security\CsrfGuard;
use Security\HtmlSanitizer;

/**
 * Renders the password reset flow pages: request, sent, form, success, and error.
 */
class PasswordResetView
{
    use YourAccountIcons;

    /**
     * Render the forgot password request page.
     */
    public function renderForgotPasswordPage(): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <?= HtmlSanitizer::trusted($this->renderLogo()) ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Reset Password</h1>
            <p class="ibl-card__subtitle">We'll email you a reset link</p>
        </div>
        <div class="ibl-card__body">
            <div class="auth-info-text mb-4">
                Enter your email address and we'll send you a link to reset your password.
            </div>

            <form action="modules.php?name=YourAccount" method="post" hx-boost="false">
                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="reset-email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->emailIcon()) ?>
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
     * Render the reset email sent confirmation page.
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
                    <?= HtmlSanitizer::trusted($this->infoIcon()) ?>
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
     * Render the reset password form page.
     */
    public function renderResetPasswordPage(string $selector, string $token): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <?= HtmlSanitizer::trusted($this->renderLogo()) ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Reset Password</h1>
            <p class="ibl-card__subtitle">Enter your new password</p>
        </div>
        <div class="ibl-card__body">
            <form action="modules.php?name=YourAccount" method="post" hx-boost="false">
                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="reset-new-password">New Password</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->lockIcon()) ?>
                        <input type="password" name="new_password" id="reset-new-password" class="ibl-input auth-input--with-icon" maxlength="60" required placeholder="Enter new password" autocomplete="new-password">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="reset-confirm-password">Confirm Password</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->lockIcon()) ?>
                        <input type="password" name="new_password2" id="reset-confirm-password" class="ibl-input auth-input--with-icon" maxlength="60" required placeholder="Re-enter new password" autocomplete="new-password">
                    </div>
                </div>

                <input type="hidden" name="selector" value="<?= HtmlSanitizer::e($selector) ?>">
                <input type="hidden" name="token" value="<?= HtmlSanitizer::e($token) ?>">
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
                    <?= HtmlSanitizer::trusted($this->checkIcon()) ?>
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
        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--error">
                    <?= HtmlSanitizer::trusted($this->errorIcon()) ?>
                </div>
                <div class="auth-status__title">Reset Error</div>
                <div class="auth-status__message"><?= HtmlSanitizer::e($error) ?></div>
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
