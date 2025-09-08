<?php

require 'mainfile.php';

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
