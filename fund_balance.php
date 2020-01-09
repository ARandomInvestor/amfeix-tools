<?php

require "vendor/autoload.php";

use ARandomInvestor\AMFEIX\StorageContract;
use Web3\Web3;

date_default_timezone_set("UTC");

if($argc < 3){
    echo "Usage: " . escapeshellarg(PHP_BINARY) . " " . escapeshellarg($argv[0]) ." <HTTP Web3 endpoint> <Investor Address>\n";
    echo "\t<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/\n";
    echo "\t<Investor Address>: The public address on Ethereum tied to your investor account. You can find this on the browser's console log on AMFEIX site. Do NOT use any private key or seed here.\n";
    exit(1);
}

$web3 = new Web3($argv[1]);

$ob = new StorageContract($web3->getProvider());

$investorAddress = trim($argv[2]);

if($investorAddress === ""){
    echo "Please provide an (ETH) Investor Address tied to contract.\n";
    exit(1);
}

echo "Acquiring AMFEIX index\n";

$ob->getFundPerformace(function ($index) use ($ob, $investorAddress) {
    echo "Processing investor $investorAddress\n";

    $ob->getTxCount($investorAddress, function ($num) use ($ob, $investorAddress, $index) {
        $txs = [];
        echo "Fetching $num tx\n";
        for ($n = 0; $n < $num; ++$n) {
            $ob->getTx($investorAddress, $n, function ($v) use ($num, $n, &$txs, $index) {
                echo "Processing BTC tx " . ($v["action"] == 0 ? "IN " : ($v["action"] == 1 ? "OUT" : "UNKNOWN")) . " ".  $v["txid"] ."\n";
                if($v["action"] == 0){
                    $json = json_decode(file_get_contents("https://blockchain.info/rawtx/" . $v["txid"] . "?cors=true"), true);
                    foreach ($json["out"] as $o) {
                        if ($o["addr"] === StorageContract::STORAGE_BITCOIN_WALLET) {
                            $v["value"] = $o["value"];
                        }
                    }
                    $txs[$v["txid"]] = $v;
                    $txs[$v["txid"]]["exit_timestamp"] = PHP_INT_MAX;
                }else if ($v["action"] == 1){
                    $txs[$v["txid"]]["exit_timestamp"] = $v["timestamp"];
                }


                if ($n === ($num - 1)) { //Done with all

                    $totalValue = 0;
                    $totalCompounded = 0;
                    $firstInvestment = PHP_INT_MAX;
                    $lastInvestment = 0;

                    foreach ($txs as $n => $tx) {
                        if (!isset($tx["value"])) {
                            continue;
                        }
                        echo "\n";
                        echo "tx " . $tx["txid"] . " @ " . date("Y-m-d H:i:s", $tx["timestamp"]) . " / BTC " . number_format($tx["value"] / 100000000, 8) . "\n";

                        $compoundedValue = $tx["value"];
                        $lastEntry = null;
                        if ($tx["timestamp"] < $firstInvestment) {
                            $firstInvestment = $tx["timestamp"];
                        }
                        if ($tx["exit_timestamp"] !== PHP_INT_MAX and $tx["exit_timestamp"] > $lastInvestment) {
                            $lastInvestment = $tx["exit_timestamp"];
                        }

                        $lastTime = 0;

                        foreach ($index as $entry) {
                            //TODO: check why this is not like this: if($entry["timestamp"] < $tx["timestamp"] or ($entry["timestamp"] >= $tx["timestamp"] and $lastTime < $tx["timestamp"])){
                            //^ seems like this is intended by fund, how nice of them

                            if ($entry["timestamp"] > $tx["exit_timestamp"]) {
                                break;
                            }

                            if ($entry["timestamp"] < $tx["timestamp"]) {
                                $lastTime = $entry["timestamp"];
                                continue;
                            }

                            $lastEntry = $entry;

                            $compoundedValue += $compoundedValue * ($entry["value"] / 100);
                        }

                        if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                            $totalCompounded += $compoundedValue;
                            $totalValue += $tx["value"];
                        }


                        if ($lastEntry === null) {
                            continue;
                        }


                        echo "\tcompounded BTC " . number_format($compoundedValue / 100000000, 8) . " @ " . date("Y-m-d H:i:s", $lastEntry["timestamp"]) . " / growth BTC " . number_format(($compoundedValue - $tx["value"]) / 100000000, 8) . " " . round((($compoundedValue - $tx["value"]) / $tx["value"]) * 100, 3) . "%\n";
                    }

                    echo "\n\n";
                    echo "CURRENT / Current Initial Investment: BTC " . number_format($totalValue / 100000000, 8) . " / Current: BTC " . number_format($totalCompounded / 100000000, 8) . " / growth: BTC " . number_format(($totalCompounded - $totalValue) / 100000000, 8) . " " . ($totalValue === 0 ? 0 : number_format((($totalCompounded - $totalValue) / $totalValue) * 100, 3)) . "% / Profit fees: BTC " . number_format((($totalCompounded - $totalValue) / 100000000) * 0.2, 8) . "\n";
                    echo "\n\n";

                    foreach ($index as $entry) {
                        if ($entry["timestamp"] < $firstInvestment) {
                            continue;
                        }
                        if($entry["timestamp"] > $lastInvestment){
                            break;
                        }
                        echo date("Y-m-d H:i:s", $entry["timestamp"]) . " : " . $entry["value"] . "%\n";
                    }
                }

            });
        }
    });
});