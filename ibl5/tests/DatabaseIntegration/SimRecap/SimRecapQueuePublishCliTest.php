<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimPublishProgressRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Child-process pin for the Discord publisher hooks of `scripts/simRecapQueue.php`:
 * the done-notify enqueue and the publish-enqueue, publish-title and publish-script verbs.
 *
 * Launches the script as a real child process. The child is a separate connection, so every
 * seed row is committed (autocommit) and removed again in tearDown. CI has no IBLbot, so the
 * poke after an enqueue fails; the enqueue must survive that.
 *
 * SECURITY: the bootstrap defines PHPUNIT_RUNNING before requiring the script,
 * so Discord::postToChannel() short-circuits and no live POST can fire.
 */
#[Group('database')]
final class SimRecapQueuePublishCliTest extends DatabaseTestCase
{
    private const SIM_DONE = 990201;
    private const SIM_HEAT = 990202;
    private const SIM_PENDING = 990203;
    private const SIM_TITLE_BLANK = 990204;
    private const SIM_TITLE_SPACE = 990205;
    private const SIM_TITLE_EARLY = 990206;
    private const SIM_RETRY = 990207;
    private const SIM_MISSING = 990208;
    private const SIM_FLAGS = 990209;
    private const SIM_SCRIPT = 990210;
    private const SCRIPT_GAME_DATE = '2025-01-03';

    /** @var list<int> */
    private const OWNED_SIMS = [
        self::SIM_DONE, self::SIM_HEAT, self::SIM_PENDING, self::SIM_TITLE_BLANK, self::SIM_TITLE_SPACE,
        self::SIM_TITLE_EARLY, self::SIM_RETRY, self::SIM_MISSING, self::SIM_FLAGS,
    ];

    private SimPublishProgressRepository $progress;
    private string $scriptPath;
    private ?string $originalPhase = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Commit the empty transaction DatabaseTestCase opened so every write
        // below is visible to the child process on its own connection.
        $this->db->commit();

        $this->progress = new SimPublishProgressRepository($this->db);

        $path = realpath(__DIR__ . '/../../../scripts/simRecapQueue.php');
        self::assertNotFalse($path, 'simRecapQueue.php must exist at the expected path');
        $this->scriptPath = $path;

        $result = $this->db->query(
            "SELECT `setting_value` FROM `ibl_settings` WHERE `setting_key` = 'Current Season Phase' LIMIT 1"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->originalPhase = is_array($row) && is_string($row['setting_value']) ? $row['setting_value'] : null;
        $this->setSeasonPhase('Regular Season');
        $this->deleteOwnedRows();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->db)) {
                $this->deleteOwnedRows();
                if ($this->originalPhase !== null) {
                    $this->setSeasonPhase($this->originalPhase);
                }
            }
        } catch (\Throwable) {
            // Best-effort — connection may be in an unrecoverable state.
        }
        parent::tearDown();
    }

    // ── Done-notify trigger ────────────────────────────────────────────────────

    public function testDoneNotifyEnqueuesRegularSeasonSim(): void
    {
        $this->seedSummary(self::SIM_DONE, 'done');

        $result = $this->runScript(['notify', '--sim=' . self::SIM_DONE, '--host=mac', '--event=done']);

        // The exit code is the pre-change one: a failed poke (no IBLbot in CI) never changes it.
        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertSame('notify', $json['cmd']);

        $row = $this->progress->findForSim(self::SIM_DONE);
        self::assertNotNull($row, 'a done notify in a publishable phase must enqueue the sim');
        self::assertNotNull($row['enqueued_at']);
        self::assertNull($row['finished_at']);
    }

    public function testDoneNotifySkipsHeatSim(): void
    {
        $this->setSeasonPhase('HEAT');
        $this->seedSummary(self::SIM_HEAT, 'done');

        $result = $this->runScript(['notify', '--sim=' . self::SIM_HEAT, '--host=mac', '--event=done']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        self::assertNull($this->progress->findForSim(self::SIM_HEAT));
    }

    public function testNonDoneNotifyDoesNotEnqueue(): void
    {
        // A sim that is not done yet: notify reports skipped and enqueues nothing.
        $this->seedSummary(self::SIM_PENDING, 'pending');
        $pending = $this->runScript(['notify', '--sim=' . self::SIM_PENDING, '--host=mac', '--event=done']);
        self::assertSame(0, $pending['exit_code'], 'stderr: ' . $pending['stderr']);
        self::assertNull($this->progress->findForSim(self::SIM_PENDING));

        // A non-done event is refused by the parser before anything runs.
        $this->seedSummary(self::SIM_DONE, 'done');
        $failed = $this->runScript(['notify', '--sim=' . self::SIM_DONE, '--host=mac', '--event=failed']);
        self::assertSame(1, $failed['exit_code']);
        self::assertNull($this->progress->findForSim(self::SIM_DONE));
    }

    // ── publish-title ──────────────────────────────────────────────────────────

    public function testPublishTitleRejectsBlankTitle(): void
    {
        $result = $this->runScript(['publish-title', '--sim=' . self::SIM_TITLE_BLANK, '--title=   ']);

        self::assertSame(2, $result['exit_code']);
        self::assertNull($this->progress->findForSim(self::SIM_TITLE_BLANK));
    }

    public function testPublishTitleRejectsSpaceFormFlag(): void
    {
        $result = $this->runScript(['publish-title', '--sim=' . self::SIM_TITLE_SPACE, '--title', 'BRIDE']);

        self::assertSame(2, $result['exit_code']);
        self::assertNull($this->progress->findForSim(self::SIM_TITLE_SPACE));
    }

    public function testPublishTitleBeforeDoneDoesNotEnqueue(): void
    {
        $result = $this->runScript(['publish-title', '--sim=' . self::SIM_TITLE_EARLY, '--title=BRIDE']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $row = $this->progress->findForSim(self::SIM_TITLE_EARLY);
        self::assertNotNull($row);
        self::assertSame('BRIDE', $row['title_override']);
        self::assertNull($row['enqueued_at'], 'an override set before the sim is done must not start a drip');
    }

    public function testPublishTitleClearRemovesTheOverride(): void
    {
        $this->runScript(['publish-title', '--sim=' . self::SIM_TITLE_EARLY, '--title=BRIDE']);

        $result = $this->runScript(['publish-title', '--sim=' . self::SIM_TITLE_EARLY, '--clear']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $row = $this->progress->findForSim(self::SIM_TITLE_EARLY);
        self::assertNotNull($row);
        self::assertNull($row['title_override']);
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function rejectedPublishArgvProvider(): array
    {
        return [
            'title and clear together' => [['publish-title', '--sim=990209', '--title=BRIDE', '--clear']],
            'neither title nor clear'  => [['publish-title', '--sim=990209']],
            'title over 100 chars'     => [['publish-title', '--sim=990209', '--title=' . str_repeat('x', 101)]],
            'unknown flag'             => [['publish-title', '--sim=990209', '--title=BRIDE', '--foo=1']],
            'title on another verb'    => [['publish-enqueue', '--sim=990209', '--title=BRIDE']],
            'retry on another verb'    => [['publish-script', '--sim=990209', '--retry']],
            'missing sim'              => [['publish-enqueue']],
        ];
    }

    /**
     * @param list<string> $argv
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedPublishArgvProvider')]
    public function testPublishVerbsRejectBadArgvWithExit2(array $argv): void
    {
        $result = $this->runScript($argv);

        self::assertSame(2, $result['exit_code'], 'stderr: ' . $result['stderr']);
        self::assertNull($this->progress->findForSim(self::SIM_FLAGS));
    }

    // ── publish-enqueue ────────────────────────────────────────────────────────

    public function testPublishEnqueueEnqueuesInPublishablePhaseAndSkipsHeat(): void
    {
        $result = $this->runScript(['publish-enqueue', '--sim=' . self::SIM_DONE]);
        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        self::assertSame('enqueued', $this->decode($result['stdout'])['result']);
        $row = $this->progress->findForSim(self::SIM_DONE);
        self::assertNotNull($row);
        self::assertNotNull($row['enqueued_at']);

        $this->setSeasonPhase('HEAT');
        $skipped = $this->runScript(['publish-enqueue', '--sim=' . self::SIM_HEAT]);
        self::assertSame(0, $skipped['exit_code'], 'stderr: ' . $skipped['stderr']);
        self::assertSame('skipped: phase HEAT', $this->decode($skipped['stdout'])['result']);
        self::assertNull($this->progress->findForSim(self::SIM_HEAT));
    }

    public function testPublishEnqueueRetryResetsExhaustedRow(): void
    {
        $this->progress->enqueue(self::SIM_RETRY);
        $this->setRawProgress(self::SIM_RETRY, 5, '0b6f3f0e-6f43-4f0c-9d1a-2c3b4d5e6f70');

        $result = $this->runScript(['publish-enqueue', '--sim=' . self::SIM_RETRY, '--retry']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $row = $this->progress->findForSim(self::SIM_RETRY);
        self::assertNotNull($row);
        self::assertSame(0, $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNotNull($row['enqueued_at']);
    }

    public function testPublishEnqueueRetryOnMissingRowExits1(): void
    {
        $result = $this->runScript(['publish-enqueue', '--sim=' . self::SIM_MISSING, '--retry']);

        self::assertSame(1, $result['exit_code']);
        self::assertStringContainsString('not found or finished', $result['stderr']);
        self::assertNull($this->progress->findForSim(self::SIM_MISSING));
    }

    // ── publish-script ─────────────────────────────────────────────────────────

    public function testPublishScriptPrintsStepsJson(): void
    {
        // A done summary with one displayable game recap (its box-score rows exist). The
        // override stands in for the season ordinal, which the seeded date may not yield.
        $this->seedSummary(self::SIM_SCRIPT, 'done');
        $this->seedGameRecap(self::SIM_SCRIPT);
        $this->progress->setTitleOverride(self::SIM_SCRIPT, 'CLI TEST TITLE');

        $result = $this->runScript(['publish-script', '--sim=' . self::SIM_SCRIPT]);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertSame(self::SIM_SCRIPT, $json['sim']);
        self::assertSame('CLI TEST TITLE', $json['title']);
        self::assertIsArray($json['steps']);
        self::assertNotSame([], $json['steps']);
        $first = $json['steps'][0];
        self::assertIsArray($first);
        self::assertSame('thread_start', $first['kind']);

        // Read-only: the verb claimed nothing.
        $row = $this->progress->findForSim(self::SIM_SCRIPT);
        self::assertNotNull($row);
        self::assertNull($row['claim_token']);
        self::assertNull($row['enqueued_at']);
    }

    public function testPublishScriptExits1WhenSimHasNoSummary(): void
    {
        $result = $this->runScript(['publish-script', '--sim=' . self::SIM_MISSING]);

        self::assertSame(1, $result['exit_code']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function deleteOwnedRows(): void
    {
        $ids = implode(', ', self::OWNED_SIMS);
        $this->db->query('DELETE FROM `ibl_sim_publish_progress` WHERE `sim` IN (' . $ids . ', ' . self::SIM_SCRIPT . ')');
        $all = $ids . ', ' . self::SIM_SCRIPT;
        $this->db->query('DELETE FROM `ibl_sim_game_recaps` WHERE `sim` IN (' . $all . ')');
        $this->db->query('DELETE FROM `ibl_sim_summaries` WHERE `sim` IN (' . $all . ')');
        $this->db->query(
            "DELETE FROM `ibl_box_scores_teams` WHERE `game_date` = '" . self::SCRIPT_GAME_DATE . "'"
            . ' AND `visitor_teamid` = 1 AND `home_teamid` = 2'
        );
    }

    private function setSeasonPhase(string $phase): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `ibl_settings` SET `setting_value` = ? WHERE `setting_key` = \'Current Season Phase\''
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $phase);
        self::assertTrue($stmt->execute());
        $stmt->close();
    }

    private function seedGameRecap(int $sim): void
    {
        $date = self::SCRIPT_GAME_DATE;
        $seeded = $this->db->query(
            "INSERT INTO `ibl_box_scores_teams` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`)"
            . " VALUES ('" . $date . "', 1, 2, 1, 'PubVstr'), ('" . $date . "', 1, 2, 1, 'PubHome')"
        );
        self::assertNotFalse($seeded, 'box-score fixture must seed');
        $recap = $this->db->query(
            "INSERT INTO `ibl_sim_game_recaps`"
            . " (`sim`, `season_year`, `game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `sort_order`, `recap_text`)"
            . " VALUES (" . $sim . ", 2025, '" . $date . "', 1, 2, 1, 0, 'A fixture game recap.')"
        );
        self::assertNotFalse($recap, 'game recap fixture must seed');
    }

    private function seedSummary(int $sim, string $status): void
    {
        $stmt = $this->db->prepare('INSERT INTO `ibl_sim_summaries` (`sim`, `status`) VALUES (?, ?)');
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $sim, $status);
        self::assertTrue($stmt->execute());
        $stmt->close();
    }

    private function setRawProgress(int $sim, int $attempts, string $token): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `ibl_sim_publish_progress` SET `attempts` = ?, `claim_token` = ? WHERE `sim` = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('isi', $attempts, $token, $sim);
        self::assertTrue($stmt->execute());
        $stmt->close();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $stdout): array
    {
        $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        /** @var array<string, mixed> $json */
        return $json;
    }

    /**
     * Launch simRecapQueue.php as a child process via a bootstrap tmpfile.
     *
     * @param list<string> $argv Arguments forwarded to the script
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runScript(array $argv): array
    {
        $bootstrapFile = tempnam(sys_get_temp_dir(), 'sim-recap-publish-bootstrap-');
        if ($bootstrapFile === false) {
            self::fail('tempnam must succeed');
        }

        $bootstrapContent = '<?php' . "\n"
            . "define('PHPUNIT_RUNNING', true);\n"
            . 'require ' . var_export($this->scriptPath, true) . ";\n";

        try {
            if (file_put_contents($bootstrapFile, $bootstrapContent) === false) {
                self::fail('Failed to write bootstrap file');
            }

            $envVars = getenv();
            $env = is_array($envVars) ? $envVars : [];

            $command = array_merge([PHP_BINARY, $bootstrapFile], $argv);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, null, $env);
            if ($process === false) {
                self::fail('proc_open failed to launch the child process');
            }

            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            return [
                'exit_code' => $exitCode,
                'stdout'    => is_string($stdout) ? $stdout : '',
                'stderr'    => is_string($stderr) ? $stderr : '',
            ];
        } finally {
            @unlink($bootstrapFile);
        }
    }
}
