<?php

declare(strict_types=1);

namespace NotificationSettings\Contracts;

interface NotificationPrefsViewInterface
{
    /**
     * Render the notification preferences form.
     *
     * @see \NotificationSettings\Contracts\NotificationPrefsServiceInterface::getPrefsForUser()
     *
     * @param array<string, int> $prefs Map of preference key (column name) => 0|1
     * @param bool $justSaved Whether to show a "saved successfully" confirmation
     */
    public function renderForm(array $prefs, bool $justSaved): string;
}
