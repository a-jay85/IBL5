<?php

require "../classes/Player.php";

$seasonPhasePreseason = 'Preseason';
$seasonPhaseHEAT = 'HEAT';
$seasonPhaseRegularSeason = 'Regular Season';
$seasonPhaseFreeAgency = 'Free Agency';

$playerOptionableFirstRoundRookieInFreeAgency = [
    'draftround' => 1,
    'exp' => 2,
    'cy4' => 0
];

$playerOptionableSecondRoundRookieInFreeAgency = [
    'draftround' => 2,
    'exp' => 1,
    'cy3' => 0
];

$playerOptionableFirstRoundRookieBeforeRegularSeason = [
    'draftround' => 1,
    'exp' => 3,
    'cy4' => 0
];

$playerOptionableSecondRoundRookieBeforeRegularSeason = [
    'draftround' => 2,
    'exp' => 2,
    'cy3' => 0
];

$playerOptionedFirstRoundRookie = [
    'draftround' => 1,
    'exp' => 4,
    'cy3' => 369,
    'cy4' => 738
];

$playerOptionedSecondRoundRookie = [
    'draftround' => 2,
    'exp' => 3,
    'cy3' => 51,
    'cy4' => 102
];