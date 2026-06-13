<?php

declare(strict_types=1);

namespace NotificationSettings\Contracts;

interface NotificationPrefsServiceInterface
{
    /**
     * Get the current notification preferences for a user as a flat map.
     *
     * When no preference row exists, returns a map of defaults derived from
     * NotificationPref::cases() and defaultEnabled(). When a row exists, returns
     * its column values cast to int.
     *
     * Keys are the NotificationPref backed string values (= column names).
     * Values are always real PHP ints: 1 (enabled) or 0 (disabled).
     *
     * @see \NotificationSettings\NotificationSettingsService::getPrefsForUser()
     *
     * @return array<string, int>
     */
    public function getPrefsForUser(int $userId): array;

    /**
     * Save notification preferences from a form submission.
     *
     * $submittedKeys contains the backed string values of checked checkboxes.
     * Every NotificationPref case is evaluated: checked → 1, absent → 0.
     * Unknown keys in $submittedKeys are silently ignored.
     * Emits an audit log entry on success.
     *
     * @see \NotificationSettings\NotificationSettingsService::savePrefsForUser()
     *
     * @param list<string> $submittedKeys Backed string values of checked preference cases
     */
    public function savePrefsForUser(int $userId, array $submittedKeys): void;
}
