<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Child-process pin for the claim verbs of `scripts/simRecapQueue.php`.
 *
 * Launches the script as a real child process and asserts on its JSON stdout
 * and on the row it leaves behind. The child is a separate connection, so
 * every seed row is committed (autocommit) and removed again in tearDown.
 *
 * SECURITY: the bootstrap defines PHPUNIT_RUNNING before requiring the script,
 * so Discord::postToChannel() short-circuits and no live POST can fire.
 */
#[Group('database')]
final class SimRecapQueueClaimCliTest extends DatabaseTestCase
{
    private const SIM_PENDING = 990101;
    private const SIM_DONE = 990102;
    private const SIM_EMPTY_QUEUE = 990103;
    private const SIM_FIRST_CLAIM = 990104;
    private const SIM_AFTER_PARK = 990105;
    private const SIM_CLAIM_BY_SIM = 990106;
    private const SIM_PHASE_OFF_NEXT = 990107;
    private const SIM_PHASE_OFF_BY_SIM = 990108;

    private SimSummaryRepository $repo;
    private string $scriptPath;
    private ?string $originalPhase = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Commit the empty transaction DatabaseTestCase opened so every write
        // below is visible to the child process on its own connection.
        $this->db->commit();

        $this->repo = new SimSummaryRepository($this->db);

        $path = realpath(__DIR__ . '/../../../scripts/simRecapQueue.php');
        self::assertNotFalse($path, 'simRecapQueue.php must exist at the expected path');
        $this->scriptPath = $path;

        // Leave no other pending row, so claim-next can only pick the seeded one.
        $this->db->query("DELETE FROM `ibl_sim_summaries` WHERE `status` = 'pending'");

        // The child reads the phase on its own connection, so the phase is
        // committed here and restored in tearDown. Default to a recaps-on phase.
        $result = $this->db->query(
            "SELECT `setting_value` FROM `ibl_settings` WHERE `setting_key` = 'Current Season Phase' LIMIT 1"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->originalPhase = is_array($row) && is_string($row['setting_value']) ? $row['setting_value'] : null;
        $this->setSeasonPhase('Regular Season');
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->db)) {
                $this->db->query(
                    'DELETE FROM `ibl_sim_summaries` WHERE `sim` IN ('
                    . self::SIM_PENDING . ', ' . self::SIM_DONE . ', ' . self::SIM_EMPTY_QUEUE . ', '
                    . self::SIM_FIRST_CLAIM . ', ' . self::SIM_AFTER_PARK . ', ' . self::SIM_CLAIM_BY_SIM . ', '
                    . self::SIM_PHASE_OFF_NEXT . ', ' . self::SIM_PHASE_OFF_BY_SIM . ')'
                );
                if ($this->originalPhase !== null) {
                    $this->setSeasonPhase($this->originalPhase);
                }
            }
        } catch (\Throwable) {
            // Best-effort — connection may be in an unrecoverable state.
        }
        parent::tearDown();
    }

    // ── Tests ──────────────────────────────────────────────────────────────────

    /**
     * claim-next emits one JSON object carrying the keys the tick reads, and
     * the claimed row records the claiming host.
     */
    public function testClaimNextOutputCarriesTheFixedTickKeys(): void
    {
        $this->seedRow(self::SIM_PENDING, 'pending');

        $result = $this->runScript(['claim-next', '--host=actions']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertTrue($json['ok']);
        self::assertSame('claim-next', $json['cmd']);
        self::assertSame(self::SIM_PENDING, $json['sim']);
        self::assertArrayHasKey('phase', $json);
        self::assertArrayHasKey('recaps_enabled', $json);

        $row = $this->repo->find(self::SIM_PENDING);
        self::assertNotNull($row);
        self::assertSame('actions', $row['claimed_by']);
    }

    /**
     * Boundary: claiming a sim that is not pending reports claimed=false and
     * leaves the notified state alone.
     */
    public function testClaimOfNonPendingSimReportsNotClaimed(): void
    {
        $this->seedRow(self::SIM_DONE, 'done');

        $result = $this->runScript(['claim', '--sim=' . self::SIM_DONE, '--host=mac']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertFalse($json['claimed']);

        $row = $this->repo->find(self::SIM_DONE);
        self::assertNotNull($row);
        self::assertNull($row['notified_state']);
    }

    /**
     * Boundary: with nothing pending, claim-next reports sim null.
     */
    public function testClaimNextOnEmptyQueueReturnsNullSim(): void
    {
        $result = $this->runScript(['claim-next', '--host=mac']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertNull($json['sim']);
    }

    /**
     * The first claim of a pending sim announces it once and records the
     * 'started' notified state; the tick keys stay in the output.
     */
    public function testClaimNextReportsStartedOnFirstClaim(): void
    {
        $this->seedRow(self::SIM_FIRST_CLAIM, 'pending');

        $result = $this->runScript(['claim-next', '--host=actions']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertSame(self::SIM_FIRST_CLAIM, $json['sim']);
        self::assertSame('started', $json['notified']);
        self::assertArrayHasKey('ok', $json);
        self::assertArrayHasKey('cmd', $json);
        self::assertArrayHasKey('sim', $json);
        self::assertArrayHasKey('phase', $json);
        self::assertArrayHasKey('recaps_enabled', $json);

        $row = $this->repo->find(self::SIM_FIRST_CLAIM);
        self::assertNotNull($row);
        self::assertSame('started', $row['notified_state']);
    }

    /**
     * Negative path: a re-claim of a sim that already carries a notified state
     * (a park put it back to pending) does not announce again.
     */
    public function testClaimNextAfterParkReportsUnchanged(): void
    {
        $this->seedRow(self::SIM_AFTER_PARK, 'pending', 'retrying');

        $result = $this->runScript(['claim-next', '--host=actions']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertSame(self::SIM_AFTER_PARK, $json['sim']);
        self::assertSame('unchanged', $json['notified']);

        $row = $this->repo->find(self::SIM_AFTER_PARK);
        self::assertNotNull($row);
        self::assertSame('retrying', $row['notified_state']);
    }

    /**
     * claim --sim announces on the winning claim only; a second claim of the
     * same sim is not claimed and so is not announced.
     */
    public function testClaimBySimAnnouncesOnceAndSkipsTheSecondClaim(): void
    {
        $this->seedRow(self::SIM_CLAIM_BY_SIM, 'pending');
        $argv = ['claim', '--sim=' . self::SIM_CLAIM_BY_SIM, '--host=actions'];

        $first = $this->runScript($argv);

        self::assertSame(0, $first['exit_code'], 'stderr: ' . $first['stderr']);
        $firstJson = $this->decode($first['stdout']);
        self::assertTrue($firstJson['claimed']);
        self::assertSame('started', $firstJson['notified']);

        $second = $this->runScript($argv);

        self::assertSame(0, $second['exit_code'], 'stderr: ' . $second['stderr']);
        $secondJson = $this->decode($second['stdout']);
        self::assertFalse($secondJson['claimed']);
        self::assertSame('skipped', $secondJson['notified']);

        $row = $this->repo->find(self::SIM_CLAIM_BY_SIM);
        self::assertNotNull($row);
        self::assertSame('started', $row['notified_state']);
    }

    /**
     * Boundary: with nothing pending, claim-next attempts no announcement.
     */
    public function testClaimNextOnEmptyQueueReportsSkipped(): void
    {
        $result = $this->runScript(['claim-next', '--host=mac']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertNull($json['sim']);
        self::assertSame('skipped', $json['notified']);
    }

    /**
     * Negative path: in a recaps-off phase claim-next still claims the leftover
     * sim, but posts no start line and leaves notified_state NULL.
     */
    public function testClaimNextInRecapsOffPhaseSkipsTheAnnouncement(): void
    {
        $this->setSeasonPhase('Playoffs');
        $this->seedRow(self::SIM_PHASE_OFF_NEXT, 'pending');

        $result = $this->runScript(['claim-next', '--host=actions']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertSame(self::SIM_PHASE_OFF_NEXT, $json['sim']);
        self::assertFalse($json['recaps_enabled']);
        self::assertSame('skipped', $json['notified']);

        $row = $this->repo->find(self::SIM_PHASE_OFF_NEXT);
        self::assertNotNull($row);
        self::assertSame('actions', $row['claimed_by']);
        self::assertNull($row['notified_state']);
    }

    /**
     * Negative path: claim --sim follows the same phase gate as claim-next.
     */
    public function testClaimBySimInRecapsOffPhaseSkipsTheAnnouncement(): void
    {
        $this->setSeasonPhase('Playoffs');
        $this->seedRow(self::SIM_PHASE_OFF_BY_SIM, 'pending');

        $result = $this->runScript(['claim', '--sim=' . self::SIM_PHASE_OFF_BY_SIM, '--host=mac']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        $json = $this->decode($result['stdout']);
        self::assertTrue($json['claimed']);
        self::assertSame('skipped', $json['notified']);

        $row = $this->repo->find(self::SIM_PHASE_OFF_BY_SIM);
        self::assertNotNull($row);
        self::assertNull($row['notified_state']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

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

    private function seedRow(int $sim, string $status, ?string $notifiedState = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `ibl_sim_summaries` (`sim`, `status`, `notified_state`) VALUES (?, ?, ?)'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('iss', $sim, $status, $notifiedState);
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
        $bootstrapFile = tempnam(sys_get_temp_dir(), 'sim-recap-claim-bootstrap-');
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
