<?php

require __DIR__ . '/../mainfile.php';

$plrFile = fopen("IBL5.plr", "rb+");
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
        fwrite($plrFile, $teamid, 2);
        // fseek($plrFile, +2, SEEK_CUR);
        echo "$name's new teamid = " . $teamid . "<br>";
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
