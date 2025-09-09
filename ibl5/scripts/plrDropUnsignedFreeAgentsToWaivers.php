<?php

require 'mainfile.php';

$plrFile = fopen("IBL5.plr", "rb+");
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $name = trim(addslashes(substr($line, 4, 32)));
    $pid = substr($line, 38, 6);
    $teamID = (int) substr($line, 44, 2); // Ensure teamID is an integer
    $currentContractYear = substr($line, 290, 2);
    $totalContractYears = substr($line, 292, 2);
    $contractOwnedBy = substr($line, 331, 2);

    if ($teamID != 0
        AND is_numeric($teamID)
        AND $currentContractYear == 0
        AND $currentContractYear == $totalContractYears
    ) {
        echo $line . "<br>";
        
        echo "teamID = $teamID<br>";
        fseek($plrFile, -565, SEEK_CUR);
        echo "teamID check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $teamID = " 0";
        fwrite($plrFile, $teamID, 2);
        // fseek($plrFile, +2, SEEK_CUR);
        echo "$name's new teamID = " . $teamID . "<br>";
        echo "<br>";

        echo "contractOwnedBy = $contractOwnedBy<br>";
        fseek($plrFile, 285, SEEK_CUR);
        echo "contractOwnedBy check = " . fread($plrFile, 2) . "<br>";
        fseek($plrFile, -2, SEEK_CUR);

        $contractOwnedBy = " 0";
        fwrite($plrFile, $contractOwnedBy, 2);
        // fseek($plrFile, +2, SEEK_CUR);
        echo "$name's new contractOwnedBy = " . $contractOwnedBy . "<br>";
        echo "<br>";
    }

}
fclose($plrFile);

echo "done.";
