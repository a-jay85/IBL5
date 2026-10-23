<?php

declare(strict_types=1);

namespace YourAccount;

use Security\CsrfGuard;
use Security\HtmlSanitizer;

/**
 * Renders the sign-in page.
 */
class LoginView
{
    use YourAccountIcons;

    /**
     * Render the login page.
     *
     * @param string|null $error Error message to display, or null for no error
     */
    public function renderLoginPage(?string $error): string
    {
        ob_start();
        ?>
<div class="auth-page">
    <?= HtmlSanitizer::trusted($this->renderLogo()) ?>
    <div class="auth-card d-card d-card-border bg-base-100 shadow-sm">
        <div class="auth-card__header">
            <h1 class="d-card-title justify-center">Sign In</h1>
            <p class="auth-card__subtitle">Welcome back to IBL</p>
        </div>
        <div class="d-card-body">
            <?php if ($error !== null): ?>
                <div class="d-alert d-alert-error" role="alert">
                    <?= HtmlSanitizer::trusted($this->renderErrorMessage($error)) ?>
                </div>
            <?php endif; ?>

            <form action="modules.php?name=YourAccount" method="post" hx-boost="false">
                <div class="ibl-form-group">
                    <label class="d-label" for="login-username">Username</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->userIcon()) ?>
                        <input type="text" name="username" id="login-username" class="d-input w-full auth-input--with-icon" maxlength="25" required placeholder="Enter your username" autocomplete="username">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="d-label" for="login-password">Password</label>
                    <div class="auth-input-wrapper">
                        <?= HtmlSanitizer::trusted($this->lockIcon()) ?>
                        <input type="password" name="user_password" id="login-password" class="d-input w-full auth-input--with-icon" maxlength="20" required placeholder="Enter your password" autocomplete="current-password">
                    </div>
                </div>

                <div class="ibl-form-group">
                    <label class="auth-checkbox-label">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                </div>

                <?= CsrfGuard::generateToken('login') ?>
                <input type="hidden" name="op" value="login">
                <button type="submit" class="d-btn d-btn-primary d-btn-block">Sign In</button>
            </form>
        </div>
        <div class="auth-card__footer">
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

    private function renderErrorMessage(string $error): string
    {
        return nl2br(HtmlSanitizer::e($error));
    }
}
