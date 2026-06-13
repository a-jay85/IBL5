<?php

declare(strict_types=1);

namespace Notifications;

use Notifications\Contracts\NotificationRepositoryInterface;
use Security\HtmlSanitizer;

/**
 * Renders the GM notification inbox page.
 *
 * Every dynamic value is wrapped in {@see HtmlSanitizer::e()}. The class builds
 * and RETURNS its HTML (it never echoes), and CSRF tokens are supplied by the
 * caller rather than generated here — one shared `notif_mark` token covers all
 * per-row forms so a 50-row page stays under CsrfGuard's MAX_TOKENS cap.
 *
 * @phpstan-import-type NotificationRow from NotificationRepositoryInterface
 */
class NotificationsView
{
    /**
     * @param list<NotificationRow> $notifications Newest-first notifications
     * @param string $markToken     Shared raw CSRF token for the `notif_mark` forms
     * @param string $markAllToken  Raw CSRF token for the `notif_mark_all` form
     */
    public function render(array $notifications, string $markToken, string $markAllToken): string
    {
        $html = '<h2 class="ibl-title">Notifications</h2>';

        if ($notifications === []) {
            return $html . '<div class="ibl-empty-state">'
                . '<p class="ibl-empty-state__text">You have no notifications.</p></div>';
        }

        $hasUnread = false;
        foreach ($notifications as $n) {
            if ($n['read_at'] === null) {
                $hasUnread = true;
                break;
            }
        }

        if ($hasUnread) {
            $html .= '<form method="post" action="modules.php?name=Notifications&op=mark_all" class="notification-actions">'
                . $this->csrfField($markAllToken)
                . '<button type="submit" class="ibl-btn">Mark all read</button>'
                . '</form>';
        }

        $html .= '<div class="notification-list">';
        foreach ($notifications as $n) {
            $html .= $this->renderRow($n, $markToken);
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param NotificationRow $n
     */
    private function renderRow(array $n, string $markToken): string
    {
        $isUnread = $n['read_at'] === null;
        $cardClass = 'notification-card' . ($isUnread ? ' notification-card--unread' : '');

        $row = '<div class="' . $cardClass . '">';
        $row .= '<div class="notification-card__body">';

        $message = HtmlSanitizer::e($n['message']);
        if ($n['link'] !== null && $n['link'] !== '') {
            $row .= '<a class="notification-card__message" href="' . HtmlSanitizer::e($n['link']) . '">' . $message . '</a>';
        } else {
            $row .= '<span class="notification-card__message">' . $message . '</span>';
        }

        $row .= '<span class="notification-card__time">' . HtmlSanitizer::e($n['created_at']) . '</span>';
        $row .= '</div>';

        if ($isUnread) {
            $row .= '<form method="post" action="modules.php?name=Notifications&op=mark" class="notification-card__action">'
                . $this->csrfField($markToken)
                . '<input type="hidden" name="id" value="' . HtmlSanitizer::e((string) $n['id']) . '">'
                . '<button type="submit" class="ibl-btn ibl-btn--small">Mark read</button>'
                . '</form>';
        }

        $row .= '</div>';

        return $row;
    }

    private function csrfField(string $token): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . HtmlSanitizer::e($token) . '">';
    }
}
