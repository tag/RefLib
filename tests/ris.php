<?php

use RefLib\RefLib as RefLib;

require ("bootstrap.php");

$rl = new RefLib();
$rl->importFile(__DIR__.'/data/ris.ris');
$got = count($rl->refs);
$want = 101;
// echo ($got == $want ? 'PASS' : 'FAIL') . " - 101 references read from RIS file\n";

$got = substr_count($rl->export(), "\n");
file_put_contents(__DIR__.'/test_output.ris', $rl->export());

$want = substr_count(file_get_contents(__DIR__.'/data/ris.ris'), "\n"); // -593- 606;
echo ($got == $want ? 'PASS' : 'FAIL') . " - Same file size in output. Got: $got, Want: $want\n";

$got = substr_count($rl->export(), "\n");
echo ($got == $want ? 'PASS' : 'FAIL') . " - Same file size in second output. Got: $got, Want: $want\n";


// $rl->importFile(__DIR__."/data/ris.txt");
// $got = count($rl->refs);
// $want = 510;
// echo ($got == $want ? 'PASS' : 'FAIL') . " - $want references read from RIS file. Got: $got, Want: $want\n";
