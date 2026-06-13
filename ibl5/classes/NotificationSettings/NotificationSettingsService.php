<?php

declare(strict_types=1);

namespace NotificationSettings;

use NotificationSettings\Contracts\NotificationPrefsRepositoryInterface;
use NotificationSettings\Contracts\NotificationPrefsServiceInterface;

/**
 * NotificationSettingsService - Business logic for GM notification preferences
 *
 * @see NotificationPrefsServiceInterface For method contracts
 */
class NotificationSettingsService implements NotificationPrefsServiceInterface
{
    private NotificationPrefsRepositoryInterface $repository;

    public function __construct(NotificationPrefsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see NotificationPrefsServiceInterface::getPrefsForUser()
     *
     * @return array<string, int>
     */
    public function getPrefsForUser(int $userId): array
    {
        $row = $this->repository->findByUserId($userId);

        if ($row === null) {
            $defaults = [];
            foreach (NotificationPref::cases() as $case) {
                $defaults[$case->value] = $case->defaultEnabled() ? 1 : 0;
            }
            return $defaults;
        }

        $prefs = [];
        foreach (NotificationPref::cases() as $case) {
            $prefs[$case->value] = (int) $row[$case->column()];
        }
        return $prefs;
    }

    /**
     * @see NotificationPrefsServiceInterface::savePrefsForUser()
     */
    public function savePrefsForUser(int $userId, array $submittedKeys): void
    {
        $values = [];
        foreach (NotificationPref::cases() as $case) {
            $values[$case->column()] = in_array($case->value, $submittedKeys, true) ? 1 : 0;
        }

        $this->repository->savePrefs($userId, $values);

        \Logging\LoggerFactory::getChannel('audit')->info('notification_prefs_saved', [
            'action' => 'notification_prefs_saved',
            'user_id' => $userId,
        ]);
    }
}
