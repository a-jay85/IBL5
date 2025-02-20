<?php

require 'mainfile.php';

$plrFile = fopen("IBL5.plr", "rb+");
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $name = trim(addslashes(substr($line, 4, 32)));
    $pid = substr($line, 38, 6);
    $tid = substr($line, 44, 2);
    $exp = substr($line, 286, 2);
    $bird = substr($line, 288, 2);
    $contractYear1 = substr($line, 298, 4);
    if ($pid != 0 
        AND $tid != 0
        AND $contractYear1 != 0
        AND $bird <= $exp) {
        echo $line . "<br>";
        echo "$name's original bird years = " . $bird . "<br>";
        
        fseek($plrFile, -321, SEEK_CUR);
        echo "bird check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $bird++;
        if ($bird < 10) {
            $bird = " " . $bird;
        }
        fwrite($plrFile, $bird, 2);
        echo "$name's new bird years = " . $bird . "<br>";
        echo "<br>";
        
        fseek($plrFile, +319, SEEK_CUR);
    }

}
fclose($plrFile);

echo "done.";
