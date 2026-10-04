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
    $session = \PlrParser\PlrBulkEditSession::fromCliArgs('advance-exp', $cliArgs);
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
    $exp = substr($line, 286, 2);
    if ($pid != 0 AND is_numeric($exp)) {
        echo $line . "<br>";
        echo "$name's original years of experience = " . $exp . "<br>";
        
        echo "exp = $exp<br>";
        fseek($plrFile, -323, SEEK_CUR);
        echo "exp check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $exp++;
        if ($exp < 10) {
            $exp = " " . $exp;
        }
        $session->write($plrFile, substr((string) $exp, 0, 2));
        echo "$name's new years of experience = " . $exp . "<br>";
        echo "<br>";
        
        fseek($plrFile, +321, SEEK_CUR);
    }

}
fclose($plrFile);

echo $session->isDryRun() ? "done (dry run: no bytes written)." : "done.";
