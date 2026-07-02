<?php

declare(strict_types=1);

namespace YourAccount;

use Security\HtmlSanitizer;

/**
 * Renders account activation success and error pages.
 */
class ActivationView
{
    use YourAccountIcons;

    /**
     * Render the activation success page.
     */
    public function renderActivationSuccessPage(string $username): string
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
                <div class="auth-status__title">Account Activated</div>
                <div class="auth-status__message">
                    <strong><?= HtmlSanitizer::e($username) ?></strong>, your account has been activated successfully. You can now sign in.
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
     * Render an activation error page.
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
                    <?= HtmlSanitizer::trusted($this->errorIcon()) ?>
                </div>
                <div class="auth-status__title">Activation Error</div>
                <div class="auth-status__message"><?= HtmlSanitizer::e($message) ?></div>
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
}
