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
echo "Acquiring valid Deposit Addresses\n";
$ob->getDepositAddresses(function($depositAddresses) use($ob, $investorAddress){
  array_map(function($a){echo "Deposit Address: " . $a . "\n";}, $depositAddresses);

  echo "Acquiring AMFEIX index\n";
  $ob->getFundPerformace(function ($index) use ($ob, $investorAddress, $depositAddresses) {
    echo "Processing investor $investorAddress\n";
    $ob->getTxs($investorAddress, function($values) use ($ob, $investorAddress, $depositAddresses, $index){
      $txs = [];

      foreach($values as $v){
        echo "Processing BTC tx " . ($v["action"] == 0 ? "IN " : ($v["action"] == 1 ? "OUT" : "UNKNOWN")) . " ".  $v["txid"] . " @ " . date("Y-m-d H:i:s", $v["timestamp"]) ."\n";
        if($v["action"] == 0){
            $json = json_decode(file_get_contents("https://blockchain.info/rawtx/" . $v["txid"] . "?cors=true"), true);
            foreach ($json["out"] as $o) {
                if (in_array($o["addr"], $depositAddresses, true)) {
                    $v["value"] = $o["value"];
                }
            }
            $txs[$v["txid"]] = $v;
            $txs[$v["txid"]]["exit_timestamp"] = PHP_INT_MAX;
        }else if ($v["action"] == 1){
            $txs[$v["txid"]]["exit_timestamp"] = $v["timestamp"];
        }
      }


      $totalValue = 0;
      $totalCompounded = 0;
      $totalFees = 0;
      $firstInvestment = PHP_INT_MAX;
      $lastInvestment = [null, 0];

      foreach ($txs as $n => $tx) {
          if (!isset($tx["value"])) {
              continue;
          }
          echo "\n";
          echo "tx " . $tx["txid"] ." @ " . date("Y-m-d H:i:s", $tx["timestamp"]) . " / ".($tx["address"] === "referer" ? "REFERRAL" : "BTC " . number_format($tx["value"] / 100000000, 8)) . ($tx["exit_timestamp"] !== PHP_INT_MAX ? " / WITHDRAWN" : "") ."\n";

          $compoundedValue = $tx["value"];
          $lastEntry = null;
          if ($tx["timestamp"] < $firstInvestment) {
              $firstInvestment = $tx["timestamp"];
          }
          if (($lastInvestment[0] === null or $lastInvestment[1] !== 0) and $tx["exit_timestamp"] !== PHP_INT_MAX and $tx["exit_timestamp"] > $lastInvestment[1]) {
              $lastInvestment = [$tx, $tx["exit_timestamp"]];
          }else if($tx["exit_timestamp"] === PHP_INT_MAX){
              $lastInvestment = [$tx, 0];
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

          if($tx["address"] === "referer"){
              $compoundedValue = ($compoundedValue - $tx["value"]) * 0.1;
              $tx["value"] = 0;
              if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                  $totalCompounded += $compoundedValue;
              }
          }else{
              if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                  $totalCompounded += $compoundedValue;
                  $totalValue += $tx["value"];
                  $totalFees += ($compoundedValue - $tx["value"]) * 0.2;
              }
          }


          if ($lastEntry === null) {
              continue;
          }


          echo "\tcompounded BTC " . number_format($compoundedValue / 100000000, 8) . " @ " . date("Y-m-d H:i:s", $lastEntry["timestamp"]) . " / growth BTC " . number_format(($compoundedValue - $tx["value"]) / 100000000, 8) . " " . ($tx["value"] === 0 ? 0 : round((($compoundedValue - $tx["value"]) / $tx["value"]) * 100, 3)) . "%\n";
      }

      echo "\n\n";
      echo "CURRENT / Current Initial Investment: BTC " . number_format($totalValue / 100000000, 8) . " / Current: BTC " . number_format($totalCompounded / 100000000, 8) . " / growth: BTC " . number_format(($totalCompounded - $totalValue) / 100000000, 8) . " " . ($totalValue === 0 ? 0 : number_format((($totalCompounded - $totalValue) / $totalValue) * 100, 3)) . "% / Profit fees: BTC " . number_format(($totalFees / 100000000), 8) . "\n";
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
