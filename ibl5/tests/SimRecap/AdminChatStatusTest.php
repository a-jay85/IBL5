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

    public function testStartedMessageNamesGitHubActionsHost(): void
    {
        self::assertSame(
            'Sim 740 recap started on GitHub Actions.',
            AdminChatStatus::message('started', 740, [], 'actions')
        );
    }

    public function testStartedMessageNamesTheMacHost(): void
    {
        self::assertSame(
            'Sim 740 recap started on the Mac.',
            AdminChatStatus::message('started', 740, [], 'mac')
        );
    }

    /** The start line is a bare announcement: the cause words never leak into it. */
    public function testStartedMessageIgnoresTheCause(): void
    {
        self::assertSame(
            'Sim 740 recap started on GitHub Actions.',
            AdminChatStatus::message('started', 740, ['last_cause' => 'flaky'], 'actions')
        );
    }

    /** The start line carries neither cause words nor the viewer link, even when both are supplied. */
    public function testStartedMessageNeverCarriesTheViewerLink(): void
    {
        self::assertSame(
            'Sim 740 recap started on GitHub Actions.',
            AdminChatStatus::message('started', 740, ['last_cause' => 'flaky'], 'actions', 'https://example.test/x')
        );
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

    public function testPostedAndRecoveredMessagesCarryTheViewerLink(): void
    {
        $url = 'https://iblhoops.net/ibl5/simSummaries.php?sim=812';

        $posted = AdminChatStatus::message('posted', 812, [], 'actions', $url);
        $recovered = AdminChatStatus::message('recovered', 812, ['last_cause' => 'flaky'], 'mac', $url);

        self::assertSame("Sim 812 recaps are posted. Review them: {$url}", $posted);
        self::assertStringEndsWith(" Review them: {$url}", $recovered);
    }

    public function testFailureMessagesNeverCarryTheViewerLink(): void
    {
        $url = 'https://iblhoops.net/ibl5/simSummaries.php?sim=812';

        $retrying = AdminChatStatus::message('retrying', 812, ['last_cause' => 'flaky'], 'actions', $url);

        self::assertStringNotContainsString($url, $retrying);
        self::assertSame('Sim 812 recaps are posted.', AdminChatStatus::message('posted', 812, [], 'actions'));
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
