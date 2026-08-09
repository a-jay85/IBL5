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
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

$plrFile = fopen("IBL5.plr", "rb+");
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
        fwrite($plrFile, $exp, 2);
        echo "$name's new years of experience = " . $exp . "<br>";
        echo "<br>";
        
        fseek($plrFile, +321, SEEK_CUR);
    }

}
fclose($plrFile);

echo "done.";
