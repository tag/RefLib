<?php

use RefLib\RefLib as RefLib;

require ("bootstrap.php");

$rl = new RefLib();
$rl->importFile(__DIR__.'/data/endnote.xml');
echo (count($rl->refs) == 1988 ? 'PASS' : 'FAIL') . ' - '.count($rl->refs)."/1998 references read from EndNote XML file\n";
