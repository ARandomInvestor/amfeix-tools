<?php

require "vendor/autoload.php";

use ARandomInvestor\AMFEIX\StorageContract;
use Web3\Web3;
use \ARandomInvestor\AMFEIX\provider\bitcoin\Blockchain_com;
use function \Denpa\Bitcoin\to_bitcoin;

date_default_timezone_set("UTC");

if($argc < 3){
    echo "Usage: " . escapeshellarg(PHP_BINARY) . " " . escapeshellarg($argv[0]) ." <HTTP Web3 endpoint> <Investor Address>\n";
    echo "\t<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/\n";
    echo "\t<Investor Address>: The public address on Ethereum tied to your investor account. You can find this on the browser's console log on AMFEIX site. Do NOT use any private key or seed here.\n";
    exit(1);
}

$web3 = new Web3($argv[1]);

$ob = new StorageContract($web3->getProvider(), new Blockchain_com());

$investorAddress = trim($argv[2]);

if($investorAddress === ""){
    echo "Please provide an (ETH) Investor Address tied to contract.\n";
    exit(1);
}
echo "Acquiring valid Deposit Addresses\n";
$ob->getDepositAddresses(function($depositAddresses) use($ob, $investorAddress){
  array_map(function($a){echo "Deposit Address: " . $a . "\n";}, $depositAddresses);

  echo "Acquiring AMFEIX index\n";
  $ob->getFundPerformace(function ($index) use ($ob, $investorAddress, $depositAddresses) {
    echo "Processing investor $investorAddress\n";

    $investor = new \ARandomInvestor\AMFEIX\InvestorAccount($investorAddress, $ob);
    $investor->getTransactionsWithInterest($index, function ($transactions) use ($ob, $index, $depositAddresses){
        $currentValue = 0;
        $currentCompounded = 0;
        $currentFees = 0;
        $totalValue = 0;
        $totalCompounded = 0;
        $totalFees = 0;
        $firstInvestment = PHP_INT_MAX;
        $lastInvestment = [null, 0];

        foreach($transactions as &$tx){
            $data = $ob->getBitcoin()->getTransaction($tx["txid"]);
            foreach ($data["out"] as $o) {
                if (in_array($o["addr"], $depositAddresses, true)) {
                    $tx["value"] = $o["value"];
                }
            }

            if (!isset($tx["value"])) {
                continue;
            }


            if ($tx["timestamp"] < $firstInvestment) {
                $firstInvestment = $tx["timestamp"];
            }
            if (($lastInvestment[0] === null or $lastInvestment[1] !== 0) and $tx["exit_timestamp"] !== PHP_INT_MAX and $tx["exit_timestamp"] > $lastInvestment[1]) {
                $lastInvestment = [$tx, $tx["exit_timestamp"]];
            }else if($tx["exit_timestamp"] === PHP_INT_MAX){
                $lastInvestment = [$tx, 0];
            }

            echo "\n";
            echo "tx " . $tx["txid"] ." @ " . date("Y-m-d H:i:s", $tx["timestamp"]) . " / ".($tx["address"] === "referer" ? "REFERRAL" : "BTC " . to_bitcoin($tx["value"])) . ($tx["exit_timestamp"] !== PHP_INT_MAX ? " / WITHDRAWN" : "") ."\n";

            if($tx["address"] === "referer"){
                $compoundedValue = (($tx["interest"] * $tx["value"]) - $tx["value"]) * 0.1;
                $totalCompounded += $compoundedValue;
                if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                    $currentCompounded += $compoundedValue;
                }
                $tx["value"] = 0;
            }else{
                $compoundedValue = $tx["interest"] * $tx["value"];

                $totalCompounded += $compoundedValue;
                $totalValue += $tx["value"];
                $totalFees += $tx["fee"] * $tx["value"];

                if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                    $currentCompounded += $compoundedValue;
                    $currentValue += $tx["value"];
                    $currentFees += $tx["fee"] * $tx["value"];
                }
            }


            if ($tx["last_interest"] === null) {
                continue;
            }


            echo "\tcompounded BTC " . to_bitcoin($compoundedValue) . " @ " . date("Y-m-d H:i:s", $tx["last_interest"]) . " / growth BTC " . to_bitcoin(($compoundedValue - $tx["value"])) . " " . ($tx["value"] === 0 ? 0 : round((($compoundedValue - $tx["value"]) / $tx["value"]) * 100, 3)) . "%\n";
        }

        echo "\n\n";
        echo "LIFETIME TOTAL / Initial Investment: BTC " . to_bitcoin($totalValue) . " / Balance: BTC " . to_bitcoin($totalCompounded) . " / growth: BTC " . to_bitcoin($totalCompounded - $totalValue) . " " . ($totalValue === 0 ? 0 : number_format((($totalCompounded - $totalValue) / $totalValue) * 100, 3)) . "% / Profit fees: BTC " . to_bitcoin($totalFees) . "\n\n";
        echo "CURRENT / Initial Investment: BTC " . to_bitcoin($currentValue) . " / Balance: BTC " . to_bitcoin($currentCompounded) . " / growth: BTC " . to_bitcoin($currentCompounded - $currentValue) . " " . ($currentValue === 0 ? 0 : number_format((($currentCompounded - $currentValue) / $currentValue) * 100, 3)) . "% / Profit fees: BTC " . to_bitcoin($currentFees) . "\n";
        echo "\n\n";

        foreach ($index as $entry) {
            if ($entry["timestamp"] < $firstInvestment) {
                continue;
            }
            if($lastInvestment[1] !== 0 and $entry["timestamp"] > $lastInvestment[1]){
                break;
            }
            echo date("Y-m-d H:i:s", $entry["timestamp"]) . " : " . $entry["value"] . "%\n";
        }
    });
  });
});
