<?php

use RefLib\RefLib as RefLib;

require ("bootstrap.php");

$rl = new RefLib();
$rl->importFile(__DIR__."/data/csv.csv");

//print_r($rl->refs);
echo (count($rl->refs) == 161 ? 'PASS' : 'FAIL') . " - 161 references read from EndNote CSV file\n";
