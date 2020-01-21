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

$web3 = new Web3(new \Web3\Providers\HttpProvider(new \Web3\RequestManagers\HttpRequestManager($argv[1], 10)));

$ob = new StorageContract($web3->getProvider());


echo "Acquiring AMFEIX index\n";

$ob->getFundPerformace(function ($index) {
    $currentCompound = 1;
    $currentMonth = 0;
    $realCompound = 1;
    $realMonth = 0;

    $totalCompound = 1;
    $totalYear = 0;

    $lastIndex = null;

    foreach ($index as $i => $entry) {
        if($i > 0 and date("Y-m", $entry["timestamp"]) !== date("Y-m", $index[$i - 1]["timestamp"])){
            echo "=== Total ".date("Y-m F", $index[$i - 1]["timestamp"]).": Sum of values ".number_format($currentMonth, 3)."% (".number_format($realMonth, 3)."%) / Compounded growth ".number_format(($currentCompound - 1) * 100, 3)."% (".number_format(($realCompound - 1) * 100, 3)."%) ===\n\n";
            $currentCompound = 1;
            $currentMonth = 0;
            $realCompound = 1;
            $realMonth = 0;
        }

        $currentCompound *= 1 + ($entry["value"] / 100);
        $currentMonth += $entry["value"];

        $totalCompound *= 1 + ($entry["value"] / 100);
        $totalYear += $entry["value"];

        $realValue = $entry["value"] > 0 ? $entry["value"] / 0.8 : $entry["value"];

        $realCompound *= 1 + ($realValue / 100);
        $realMonth += $realValue;
        echo date("Y-m-d H:i:s", $entry["timestamp"]) . " : " . $entry["value"] . "%".($entry["value"] > 0 ? " (".number_format($realValue, 3)."%)" : "")."\n";
        $lastIndex = $i;
    }

    if($lastIndex !== null){
        echo "=== Ongoing ".date("Y-m F", $index[$lastIndex]["timestamp"]).": Sum of values ".number_format($currentMonth, 3)."% (".number_format($realMonth, 3)."%) / Compounded growth ".number_format(($currentCompound - 1) * 100, 3)."% (".number_format(($realCompound - 1) * 100, 3)."%) ===\n\n";
        echo "=== TOTAL: Sum of values ".number_format($totalYear, 3)."% / Compounded growth ".number_format(($totalCompound - 1) * 100, 3)."% ===\n\n";
    }

});
