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
    $session = \PlrParser\PlrBulkEditSession::fromCliArgs('advance-bird', $cliArgs);
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
    $exp = substr($line, 286, 2);
    $bird = substr($line, 288, 2);
    $contractYear1 = substr($line, 298, 4);
    if ($pid != 0 
        AND $teamid != 0
        AND $contractYear1 != 0
        AND $bird <= $exp
    ) {
        echo $line . "<br>";
        echo "$name's original bird years = " . $bird . "<br>";
        
        fseek($plrFile, -321, SEEK_CUR);
        echo "bird check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $bird = sprintf("%2d", (int) $bird + 1);
        $session->write($plrFile, substr($bird, 0, 2));
        echo "$name's new bird years = " . $bird . "<br>";
        echo "<br>";
        
        fseek($plrFile, +319, SEEK_CUR);
    }

}
fclose($plrFile);

echo $session->isDryRun() ? "done (dry run: no bytes written)." : "done.";
