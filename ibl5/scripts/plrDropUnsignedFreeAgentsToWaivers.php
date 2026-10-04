<?php

// ── CLI-only guard (security constraint 4) — must stay the FIRST executable
//    statement: a web hit must be refused before any resource is touched.
//    Paired with a <Files> deny in ibl5/scripts/.htaccess (defense in depth).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

$rawArgv = $_SERVER['argv'] ?? [];
$cliArgs = is_array($rawArgv) ? array_values(array_filter(array_slice($rawArgv, 1), 'is_string')) : [];

try {
    $session = \PlrParser\PlrBulkEditSession::fromCliArgs('drop-unsigned-fa', $cliArgs);
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}

try {
    $plrFile = $session->open();
} catch (\RuntimeException $e) {
    fwrite(STDERR, 'Aborted before any write: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $session->isDryRun()
    ? "DRY RUN: IBL5.plr opened read-only; no bytes will be written.\n"
    : 'Backup written and verified: ' . $session->backupPath() . "\n";
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $name = trim(addslashes(substr($line, 4, 32)));
    $pid = substr($line, 38, 6);
    $teamid = (int) substr($line, 44, 2); // Ensure teamid is an integer
    $currentContractYear = substr($line, 290, 2);
    $totalContractYears = substr($line, 292, 2);
    $contractOwnedBy = substr($line, 331, 2);

    if ($teamid != 0
        AND is_numeric($teamid)
        AND $currentContractYear == 0
        AND $currentContractYear == $totalContractYears
    ) {
        echo $line . "<br>";
        
        echo "teamid = $teamid<br>";
        fseek($plrFile, -565, SEEK_CUR);
        echo "teamid check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $teamid = " 0";
        $session->write($plrFile, $teamid);
        // fseek($plrFile, +2, SEEK_CUR);
        echo "$name's new teamid = " . $teamid . "<br>";
        echo "<br>";

        echo "contractOwnedBy = $contractOwnedBy<br>";
        fseek($plrFile, 285, SEEK_CUR);
        echo "contractOwnedBy check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $contractOwnedBy = " 0";
        $session->write($plrFile, $contractOwnedBy);
        // fseek($plrFile, +2, SEEK_CUR);
        echo "$name's new contractOwnedBy = " . $contractOwnedBy . "<br>";
        echo "<br>";
    }

}
fclose($plrFile);

echo $session->isDryRun() ? "done (dry run: no bytes written)." : "done.";
