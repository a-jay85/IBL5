<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

$processor = new FreeAgency\FreeAgencyProcessor($db);
echo $processor->processOfferSubmission($_POST);

