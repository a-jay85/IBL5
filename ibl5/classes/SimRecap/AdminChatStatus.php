<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Builds the #admin-chat status line for a sim recap state change.
 *
 * Pure: every message comes from fixed templates, enum lookups, the sim
 * number, and the row's blocked_until. Callers only send enums over ssh, so
 * neither host can put free text into the channel. No template contains '@'.
 */
final class AdminChatStatus
{
    private const HOST_WORDS = [
        'actions' => 'GitHub',
        'mac' => 'the Mac',
    ];

    private const CAUSE_WORDS = [
        'auth' => "couldn't log in to Claude",
        'transport' => "couldn't reach the league server",
        'flaky' => 'got a bad result from Claude',
        'usage_limit' => 'hit the Claude usage limit',
        'prompt' => "couldn't build the prompt",
    ];

    private const FIX_WORDS = [
        'prompt' => 'The recap prompt or exemplar file is broken and needs a developer.',
        'auth' => 'Claude login failed on both GitHub and the Mac. Renew CLAUDE_CODE_OAUTH_TOKEN.',
    ];

    /**
     * @param array{last_cause?: ?string, blocked_until?: ?string} $row
     * @param string $viewerUrl Recap viewer link for the posted/recovered lines; '' omits it.
     */
    public static function message(string $state, int $sim, array $row, string $host, string $viewerUrl = ''): string
    {
        ParkPolicy::assertNotifyState($state);
        ParkPolicy::assertHost($host);
        $cause = $row['last_cause'] ?? null;
        $causeWords = self::CAUSE_WORDS[$cause ?? ''] ?? 'ran into a problem';
        $time = self::formatTime($row['blocked_until'] ?? null);
        $link = $viewerUrl === '' ? '' : " Review them: {$viewerUrl}";

        return match ($state) {
            'posted' => "Sim {$sim} recaps are posted.{$link}",
            'recovered' => "Sim {$sim} recaps are posted. The earlier problem ({$causeWords}) was worked around.{$link}",
            'retrying' => "Sim {$sim} recap hit a glitch on " . self::HOST_WORDS[$host] . '. '
                . ($time === null ? 'Retrying now.' : "Retrying at {$time}."),
            'waiting' => "Sim {$sim} recap hit the Claude usage limit. It will retry by itself at "
                . ($time ?? 'the next reset') . '.',
            'handed_mac', 'handed_actions' => "Sim {$sim} recap: " . self::HOST_WORDS[$host] . " {$causeWords}. Handing it to "
                . self::HOST_WORDS[$state === 'handed_mac' ? 'mac' : 'actions'] . '.',
            default => "Sim {$sim} recaps are not available tonight. "
                . (self::FIX_WORDS[$cause ?? ''] ?? "Retries ran out ({$causeWords}). Check the sim-recap logs."),
        };
    }

    private static function formatTime(?string $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime);
        if ($parsed === false) {
            return null;
        }

        return $parsed->format('g:i A T');
    }
}
