<?php

require "vendor/autoload.php";

use ARandomInvestor\AMFEIX\StorageContract;
use Web3\Web3;

date_default_timezone_set("UTC");

if($argc < 2){
    echo "Usage: " . escapeshellarg(PHP_BINARY) . " " . escapeshellarg($argv[0]) ." <HTTP Web3 endpoint>\n";
    echo "\t<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/\n";
    exit(1);
}

$web3 = new Web3($argv[1]);

$ob = new StorageContract($web3->getProvider());


echo "Acquiring AMFEIX index\n";

$ob->getFundPerformace(function ($index) {
    $currentCompound = 1;
    $currentMonth = 0;

    foreach ($index as $i => $entry) {
        if($i > 0 and date("Y-m", $entry["timestamp"]) !== date("Y-m", $index[$i - 1]["timestamp"])){
            echo "=== Total ".date("Y-m F", $index[$i - 1]["timestamp"]).": Sum of values ".number_format($currentMonth, 2)."% / Compounded growth ".number_format(($currentCompound - 1) * 100, 3)."% ===\n\n";
            $currentCompound = 1;
            $currentMonth = 0;
        }
        $currentCompound *= 1 + ($entry["value"] / 100);
        $currentMonth += $entry["value"];
        echo date("Y-m-d H:i:s", $entry["timestamp"]) . " : " . $entry["value"] . "%\n";
    }
});
