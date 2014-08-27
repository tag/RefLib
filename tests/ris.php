<?php

use RefLib\RefLib as RefLib;

require ("bootstrap.php");

$rl = new RefLib();
$rl->importFile(__DIR__.'/data/ris.ris');
$got = count($rl->refs);
$want = 101;
echo ($got == $want ? 'PASS' : 'FAIL') . " - 101 references read from RIS file\n";
file_put_contents('temp.txt', $rl->export());
$got = substr_count($rl->export(), "\n");
$want = substr_count(file_get_contents(__DIR__.'/data/ris.ris'), "\n"); // -593- 606;
echo ($got == $want ? 'PASS' : 'FAIL') . " - Same file size out output. Got: $got, Want: $want\n";


$rl->importFile(__DIR__."/data/ris.txt");
$got = count($rl->refs);
$want = 510;
echo ($got == $want ? 'PASS' : 'FAIL') . " - $want references read from RIS file. Got: $got, Want: $want\n";
