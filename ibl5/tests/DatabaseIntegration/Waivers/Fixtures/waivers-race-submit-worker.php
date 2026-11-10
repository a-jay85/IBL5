<?php
declare(strict_types=1);
// Child process for WaiversTwoConnectionSubmitRaceTest: runs one waiver add on its own connection.
// argv: <team name> <player id>. Env: DB_HOST, DB_USER, DB_PASS, DB_NAME.
// stdout line 1: this connection's CONNECTION_ID(). stdout line 2: json_encode(submit() result).
define('PHPUNIT_RUNNING', true);
require dirname(__DIR__, 3) . '/bootstrap.php';

if ($argc !== 3 || !is_numeric($argv[2])) {
    fwrite(STDERR, "usage: waivers-race-submit-worker.php <team name> <player id>\n");
    exit(2);
}

$env = [];
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $name) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        fwrite(STDERR, "missing env var $name\n");
        exit(2);
    }
    $env[$name] = $value;
}

try {
    $conn = new \mysqli();
    $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
    $conn->real_connect($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
    if ($conn->connect_errno !== 0) {
        fwrite(STDERR, 'Database connection failed: ' . $conn->connect_error . "\n");
        exit(1);
    }
    $conn->query('SET SESSION innodb_lock_wait_timeout = 20');

    $idResult = $conn->query('SELECT CONNECTION_ID() AS id');
    if (!$idResult instanceof \mysqli_result) {
        throw new \RuntimeException('CONNECTION_ID() query failed');
    }
    $idRow = $idResult->fetch_assoc();
    if (!is_array($idRow)) {
        throw new \RuntimeException('CONNECTION_ID() returned no row');
    }
    // Printed before anything can block, so the parent can watch this thread's lock state.
    fwrite(STDOUT, (string) $idRow['id'] . "\n");
    fflush(STDOUT);

    // Same values as the parent test's createStub(Season) so A and B take the same branches.
    $season = new class ($conn) extends \Season\Season {
        public function __construct(\mysqli $db)
        {
            parent::__construct($db);
        }

        public function advancesContractYears(): bool
        {
            return false;
        }

        public function isOffseasonPhase(): bool
        {
            return false;
        }
    };

    $teamIdentityRepo = new \Repositories\TeamIdentityRepository($conn);
    $processor = new \Waivers\WaiversProcessor(
        new \Waivers\WaiversRepository($conn),
        $teamIdentityRepo,
        new \Repositories\PlayerLookupRepository($conn),
        new \Waivers\WaiversValidator(),
        new \Topics\News\NewsRepository($conn),
        $conn,
        new \Psr\Log\NullLogger(),
        $season,
        null,
        static function (): \Mail\Contracts\MailServiceInterface {
            throw new \LogicException('worker must not send mail');
        },
        static function (): void {
        }
    );
    $service = new \Waivers\WaiversSubmissionService(
        $processor,
        new \Repositories\SalaryCapRepository($conn),
        $season,
        new \Waivers\WaiversRosterSlotCounter(new \Team\TeamQueryRepository($conn)),
        $teamIdentityRepo,
        new \Waivers\WaiversTeamLockRepository($conn)
    );

    $result = $service->submit(['Action' => 'add', 'Player_ID' => $argv[2]], $argv[1]);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}
