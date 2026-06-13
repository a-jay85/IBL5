<?php

declare(strict_types=1);

namespace Notifications;

use Notifications\Contracts\NotificationRepositoryInterface;
use Notifications\Contracts\NotificationServiceInterface;

/**
 * @see NotificationServiceInterface
 *
 * Thin write path over NotificationRepository — the single extension seam every
 * dispatch point calls to record an in-app GM notification.
 */
class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
    ) {
    }

    /**
     * @see NotificationServiceInterface::notify()
     */
    public function notify(int $teamId, string $type, string $message, ?string $link = null): void
    {
        $this->repository->insert($teamId, $type, $message, $link);
    }
}
