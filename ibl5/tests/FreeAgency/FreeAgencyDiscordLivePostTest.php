<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyDiscordDispatcherInterface;
use FreeAgency\FreeAgencyAdminProcessor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Opt-in end-to-end check: really posts the Day 12 signings story to Discord.
 *
 * Runs the same chunking and dispatch loop production uses, but delivers each
 * chunk over the wire so a human can eyeball the result in the testing server.
 *
 * HOW TO RUN (from ibl5/):
 *
 *     IBL5_DISCORD_LIVE=1 vendor/bin/phpunit --group discord-live
 *
 * `bin/test unit -- ...` will NOT work here: it forwards trailing arguments as
 * test file paths, not as phpunit flags, so `--group` is read as a filename.
 *
 * Skipped everywhere else. `discord-live` is excluded in phpunit.xml and the
 * env var must be set explicitly, so neither CI nor a plain local run can post.
 *
 * SAFETY — three independent guards, all of which must pass before any HTTP:
 *   1. IBL5_DISCORD_LIVE=1 must be set.
 *   2. config/discord.config.php must define a `testing` webhook.
 *   3. The resolved URL must not equal any other webhook in that config, so a
 *      `testing` key mistakenly pointed at a league channel aborts the test.
 *
 * This test never calls Discord\Discord. That class hard-returns under PHPUnit
 * by design (Discord::isPhpUnit()), and that guard is deliberately left intact
 * — every other test in the suite depends on it. The webhook POST here is made
 * directly by the test's own dispatcher instead.
 *
 * The webhook URL is a secret: it is read into a variable and never asserted
 * on, echoed, or included in a failure message.
 */
#[Group('discord-live')]
class FreeAgencyDiscordLivePostTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    private const DAY12_NEWS_SID = 4401;

    private string $webhookUrl = '';

    protected function setUp(): void
    {
        if (getenv('IBL5_DISCORD_LIVE') !== '1') {
            // phpunit-hygiene-allow: opt-in live Discord post, only runs when IBL5_DISCORD_LIVE=1 is set explicitly
            self::markTestSkipped('Set IBL5_DISCORD_LIVE=1 to really post to the testing Discord server');
        }

        $this->webhookUrl = $this->resolveTestingWebhook();
    }

    public function testPostsRealDay12StoryToTestingDiscordServer(): void
    {
        $homeText = file_get_contents(self::FIXTURES . '/fa-day12-hometext.txt');
        self::assertIsString($homeText, 'Day 12 fixture must be readable');

        $dispatcher = $this->makeLiveDispatcher($this->webhookUrl);

        $repository = self::createStub(FreeAgencyAdminRepositoryInterface::class);
        $repository->method('executeSigningsTransactionally')
            ->willReturn(['successCount' => 80, 'errorCount' => 0, 'newsSid' => self::DAY12_NEWS_SID]);

        $processor = new FreeAgencyAdminProcessor($repository, new MockDatabase(), null, $dispatcher);
        $result = $processor->executeSignings(
            12,
            [$this->makeMinimalSigning()],
            'FA Day 12',
            $homeText,
            'Body text'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(
            [1946, 1899, 1997, 1929],
            $dispatcher->lengths,
            'Live post must deliver the same four messages the unit tests pin'
        );
        $this->assertCount(4, $dispatcher->statuses, 'Every chunk must have been POSTed');
        foreach ($dispatcher->statuses as $index => $status) {
            // Discord answers webhook POSTs with 204 today; accept any 2xx so a
            // future success code does not fail this test for a non-defect.
            $this->assertGreaterThanOrEqual(200, $status, "Message {$index} was rejected by Discord");
            $this->assertLessThan(300, $status, "Message {$index} was rejected by Discord");
        }
    }

    /**
     * Read the testing webhook, refusing to proceed if it could be a league channel.
     */
    private function resolveTestingWebhook(): string
    {
        $configPath = __DIR__ . '/../../config/discord.config.php';
        self::assertFileExists($configPath, 'config/discord.config.php is required for a live post');

        /** @var array{webhooks?: array<string, string>} $config */
        $config = require $configPath;
        $webhooks = $config['webhooks'] ?? [];

        self::assertArrayHasKey('testing', $webhooks, "config/discord.config.php has no 'testing' webhook");

        $url = $webhooks['testing'];
        self::assertNotSame('', $url, "The 'testing' webhook is empty");

        foreach ($webhooks as $name => $candidate) {
            if ($name === 'testing') {
                continue;
            }
            self::assertNotSame(
                $url,
                $candidate,
                "The 'testing' webhook is the same URL as the '{$name}' webhook — refusing to post"
            );
        }

        return $url;
    }

    /**
     * A dispatcher that really POSTs each chunk, recording sizes and HTTP statuses.
     *
     * @return FreeAgencyDiscordDispatcherInterface&object{lengths: list<int>, statuses: list<int>}
     */
    private function makeLiveDispatcher(string $webhookUrl): object
    {
        return new class ($webhookUrl) implements FreeAgencyDiscordDispatcherInterface {
            /** @var list<int> */
            public array $lengths = [];

            /** @var list<int> */
            public array $statuses = [];

            public function __construct(private string $webhookUrl)
            {
            }

            public function dispatch(string $message): void
            {
                $payload = json_encode(['content' => $message]);
                if ($payload === false) {
                    throw new \RuntimeException('Failed to encode Discord payload');
                }

                $handle = curl_init($this->webhookUrl);
                if ($handle === false) {
                    throw new \RuntimeException('Failed to initialise cURL');
                }

                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_TIMEOUT, 15);

                $response = curl_exec($handle);
                $error = curl_error($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if ($response === false) {
                    // Never interpolate the URL — it is a secret.
                    throw new \RuntimeException("Discord POST failed: {$error}");
                }

                $this->lengths[] = mb_strlen($message);
                $this->statuses[] = $status;

                // Discord rate-limits webhooks; space out a multi-chunk post.
                usleep(500_000);
            }
        };
    }

    /**
     * @return array{playerId: int, teamId: int, teamName: string, offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int}, offerYears: int, offerTotal: float, usedMle: bool, usedLle: bool}
     */
    private function makeMinimalSigning(): array
    {
        return [
            'playerId' => 1,
            'teamId' => 10,
            'teamName' => 'Miami',
            'offers' => [
                'offer1' => 500,
                'offer2' => 0,
                'offer3' => 0,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
            ],
            'offerYears' => 1,
            'offerTotal' => 500.0,
            'usedMle' => false,
            'usedLle' => false,
        ];
    }
}
