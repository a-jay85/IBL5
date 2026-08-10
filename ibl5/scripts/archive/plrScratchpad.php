<?php

// ── CLI-only guard (security constraint 4) — must stay the FIRST executable
//    statement: a web hit must be refused before any resource is touched.
//    Paired with the directory-wide deny in ibl5/scripts/archive/.htaccess
//    (defense in depth).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

require __DIR__ . '/../../mainfile.php';

$plrFile = fopen("IBL5.plr", "rb+");
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $name = trim(addslashes(substr($line, 4, 32)));
    $pid = substr($line, 38, 6);
    $contractOwnedBy = substr($line, 331, 2);
    if ($pid != 0) {
        echo $line . "<br>";
        echo "$name's contract owner = " . $contractOwnedBy . "<br>";
        
        fseek($plrFile, -278, SEEK_CUR);
        echo "contract owner check = " . fread($plrFile, 2) . "<br>";
        
        fseek($plrFile, +276, SEEK_CUR);
    }
}
fclose($plrFile);

echo "done.";
