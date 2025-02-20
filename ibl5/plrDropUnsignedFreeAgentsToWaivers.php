<?php

require 'mainfile.php';

$plrFile = fopen("IBL5.plr", "rb+");
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $name = trim(addslashes(substr($line, 4, 32)));
    $pid = substr($line, 38, 6);
    $tid = substr($line, 44, 2);
    $currentContractYear = substr($line, 290, 2);
    $totalContractYears = substr($line, 292, 2);

    if ($tid != 0
        AND is_numeric($tid)
        AND $currentContractYear == 0
        AND $currentContractYear == $totalContractYears
    ) {
        echo $line . "<br>";
        echo "$name's original teamID = " . $tid . "<br>";
        
        echo "tid = $tid<br>";
        fseek($plrFile, -565, SEEK_CUR);
        echo "tid check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $tid = " 0";
        fwrite($plrFile, $tid, 2);
        echo "$name's new teamID = " . $tid . "<br>";
        echo "<br>";
        
        fseek($plrFile, +563, SEEK_CUR);
    }

}
fclose($plrFile);

echo "done.";
