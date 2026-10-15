<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\AdminChatStatus;
use SimRecap\ParkPolicy;

final class AdminChatStatusTest extends TestCase
{
    /**
     * The waiting template fills {time} from blocked_until. Dropping {time}
     * leaves only "the next reset" and fails here.
     */
    public function testWaitingMessageNamesRetryTime(): void
    {
        $blocked = '2026-10-15 21:47:00';
        $time = (new \DateTimeImmutable($blocked))->format('g:i A T');

        $message = AdminChatStatus::message('waiting', 812, ['last_cause' => 'usage_limit', 'blocked_until' => $blocked], 'actions');

        self::assertStringContainsString('Sim 812', $message);
        self::assertStringContainsString('usage limit', $message);
        self::assertStringContainsString("at {$time}.", $message);
    }

    public function testHandedMessageNamesBothHosts(): void
    {
        $toMac = AdminChatStatus::message('handed_mac', 812, ['last_cause' => 'auth'], 'actions');
        self::assertStringContainsString("GitHub couldn't log in to Claude", $toMac);
        self::assertStringContainsString('Handing it to the Mac.', $toMac);

        $toActions = AdminChatStatus::message('handed_actions', 812, ['last_cause' => 'transport'], 'mac');
        self::assertStringContainsString("the Mac couldn't reach the league server", $toActions);
        self::assertStringContainsString('Handing it to GitHub.', $toActions);
    }

    public function testUnavailableMessageNamesHumanFixPerCause(): void
    {
        $prompt = AdminChatStatus::message('unavailable', 812, ['last_cause' => 'prompt'], 'actions');
        $auth = AdminChatStatus::message('unavailable', 812, ['last_cause' => 'auth'], 'mac');
        $flaky = AdminChatStatus::message('unavailable', 812, ['last_cause' => 'flaky'], 'mac');

        self::assertStringContainsString('not available tonight', $prompt);
        self::assertStringContainsString('needs a developer', $prompt);
        self::assertStringContainsString('Renew CLAUDE_CODE_OAUTH_TOKEN', $auth);
        self::assertStringContainsString('Retries ran out (got a bad result from Claude)', $flaky);
    }

    public function testRecoveredMessageNamesEarlierProblem(): void
    {
        $message = AdminChatStatus::message('recovered', 812, ['last_cause' => 'transport'], 'mac');

        self::assertStringContainsString('Sim 812 recaps are posted.', $message);
        self::assertStringContainsString("couldn't reach the league server", $message);
    }

    public function testNoTemplateContainsMention(): void
    {
        $rows = [['last_cause' => null, 'blocked_until' => null]];
        foreach (ParkPolicy::CAUSES as $cause) {
            $rows[] = ['last_cause' => $cause, 'blocked_until' => '2026-10-15 21:47:00'];
        }
        foreach (ParkPolicy::NOTIFY_STATES as $state) {
            foreach (ParkPolicy::HOSTS as $host) {
                foreach ($rows as $row) {
                    self::assertStringNotContainsString('@', AdminChatStatus::message($state, 812, $row, $host), "{$state} on {$host} must not mention anyone");
                }
            }
        }
    }
}
