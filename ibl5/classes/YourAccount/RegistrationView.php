<?php

declare(strict_types=1);

namespace YourAccount;

use Security\CsrfGuard;
use Security\HtmlSanitizer;

/**
 * Renders the registration flow pages: form, completion, and error.
 */
class RegistrationView
{
    use YourAccountIcons;

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
    <?= HtmlSanitizer::trusted($this->renderLogo()) ?>
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Create Account</h1>
            <p class="ibl-card__subtitle">Join the IBL community</p>
        </div>
        <div class="ibl-card__body">
            <form action="modules.php?name=YourAccount" method="post" hx-boost="false">
                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="register-username">Username</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->userIcon()) ?>
                        <input type="text" name="username" id="register-username" class="ibl-input auth-input--with-icon" maxlength="25" required placeholder="Choose a username" autocomplete="username">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label ibl-label--required" for="register-email">Email Address</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->emailIcon()) ?>
                        <input type="email" name="user_email" id="register-email" class="ibl-input auth-input--with-icon" maxlength="255" required placeholder="Enter your email" autocomplete="email">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label" for="register-password">Password</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->lockIcon()) ?>
                        <input type="password" name="user_password" id="register-password" class="ibl-input auth-input--with-icon" maxlength="40" placeholder="Choose a password" autocomplete="new-password">
                    </div>
                    <div class="ibl-form-help">Leave blank to auto-generate a password.</div>
                </div>

                <div class="ibl-form-group">
                    <label class="ibl-label" for="register-password2">Confirm Password</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->lockIcon()) ?>
                        <input type="password" name="user_password2" id="register-password2" class="ibl-input auth-input--with-icon" maxlength="40" placeholder="Re-enter your password" autocomplete="new-password">
                    </div>
                </div>

                <?= CsrfGuard::generateToken('register') ?>
                <input type="hidden" name="op" value="finish">
                <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">Create Account</button>
            </form>

            <div class="auth-info-text mt-4">
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
        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__body">
            <div class="auth-status">
                <div class="auth-status__icon auth-status__icon--success">
                    <?= HtmlSanitizer::trusted($this->checkIcon()) ?>
                </div>
                <div class="auth-status__title"><?= HtmlSanitizer::e($siteName) ?> Account Created</div>
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
        ob_start();
        ?>
<div class="auth-page">
    <div class="auth-card ibl-card">
        <div class="ibl-card__header">
            <h1 class="ibl-card__title">Registration Error</h1>
        </div>
        <div class="ibl-card__body">
            <div class="ibl-alert ibl-alert--error"><?= HtmlSanitizer::e($error) ?></div>
            <div class="auth-links">
                <a href="modules.php?name=YourAccount&amp;op=new_user">Try again</a>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
