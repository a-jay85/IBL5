<?php

declare(strict_types=1);

namespace NotificationSettings;

use NotificationSettings\Contracts\NotificationPrefsViewInterface;
use Security\HtmlSanitizer;

/**
 * NotificationSettingsView - Renders the GM notification preferences form
 *
 * Checkboxes are grouped into "Event notifications" and "Digests & reminders".
 * Unchecked boxes are not submitted by the browser; the service treats absent keys as 0.
 *
 * @see NotificationPrefsViewInterface For method contracts
 */
class NotificationSettingsView implements NotificationPrefsViewInterface
{
    /**
     * @see NotificationPrefsViewInterface::renderForm()
     *
     * @param array<string, int> $prefs Map of preference key => 0|1
     */
    public function renderForm(array $prefs, bool $justSaved): string
    {
        $eventCases = [];
        $digestCases = [];

        foreach (NotificationPref::cases() as $case) {
            if (str_starts_with($case->value, 'notify_')) {
                $eventCases[] = $case;
            } else {
                $digestCases[] = $case;
            }
        }

        ob_start();
        ?>
<?php if ($justSaved): ?>
<div class="ibl-alert ibl-alert--success">Preferences saved.</div>
<?php endif; ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Notification Preferences</h2>
    </div>
    <div class="ibl-card__body">
        <form method="post" action="modules.php?name=NotificationSettings&amp;op=save">
            <?= \Security\CsrfGuard::generateToken('notification_prefs_save') ?>

            <fieldset class="ibl-form-group">
                <legend class="ibl-label">Event notifications</legend>
                <?php foreach ($eventCases as $case): ?>
                <div class="ibl-form-group">
                    <label class="ibl-label">
                        <input type="checkbox"
                               name="<?= HtmlSanitizer::e($case->value) ?>"
                               value="1"
                               <?= ($prefs[$case->value] ?? 0) === 1 ? 'checked' : '' ?>>
                        <?= HtmlSanitizer::e($case->label()) ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </fieldset>

            <fieldset class="ibl-form-group">
                <legend class="ibl-label">Digests &amp; reminders</legend>
                <?php foreach ($digestCases as $case): ?>
                <div class="ibl-form-group">
                    <label class="ibl-label">
                        <input type="checkbox"
                               name="<?= HtmlSanitizer::e($case->value) ?>"
                               value="1"
                               <?= ($prefs[$case->value] ?? 0) === 1 ? 'checked' : '' ?>>
                        <?= HtmlSanitizer::e($case->label()) ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </fieldset>

            <button type="submit" class="ibl-btn ibl-btn--primary">Save preferences</button>
        </form>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
