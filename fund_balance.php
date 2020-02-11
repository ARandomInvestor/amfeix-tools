<?php

require "vendor/autoload.php";

use ARandomInvestor\AMFEIX\StorageContract;
use Web3\Web3;
use \ARandomInvestor\AMFEIX\provider\bitcoin\Blockchain_com;
use function \Denpa\Bitcoin\to_bitcoin;

date_default_timezone_set("UTC");

if($argc < 3){
    echo "Usage: " . escapeshellarg(PHP_BINARY) . " " . escapeshellarg($argv[0]) ." <HTTP Web3 endpoint> <Investor Address ...>\n";
    echo "\t<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/\n";
    echo "\t<Investor Address ...>: The public address on Ethereum tied to your investor account. You can find this on the browser's console log on AMFEIX site. Do NOT use any private key or seed here. You can have multiple of them.\n";
    exit(1);
}

$web3 = new Web3(new \Web3\Providers\HttpProvider(new \Web3\RequestManagers\HttpRequestManager($argv[1], 10)));

$ob = new StorageContract($web3->getProvider(), new Blockchain_com());



$balances = [];

for($addr = 2; $addr < $argc; ++$addr){
    $investorAddress = trim($argv[$addr]);

    if($investorAddress === ""){
        echo "Please provide an (ETH) Investor Address tied to contract.\n";
        exit(1);
    }

    $investor = new \ARandomInvestor\AMFEIX\InvestorAccount($investorAddress, $ob);
    echo "Querying Investor Address $investorAddress\n";

    $investor->getBalance(function ($balance) use($investor, &$balances, $argc){
        $balances[$investor->getAddress()] = $balance;
        if($argc > 3){
            echo "LIFETIME TOTAL / Initial Investment: BTC " . to_bitcoin($balance["total"]["initial"]) . " / Balance: BTC " . to_bitcoin($balance["total"]["balance"]) . " / growth: BTC " . to_bitcoin($balance["total"]["growth"]) . " / profit " . number_format($balance["total"]["yield"] * 100, 3) . "% / Performance fees (already deducted): BTC " . to_bitcoin($balance["total"]["fee"]) . "\n";
            echo "CURRENT / Initial Investment: BTC " . to_bitcoin($balance["current"]["initial"]) . " / Balance: BTC " . to_bitcoin($balance["current"]["balance"]) . " / growth: BTC " . to_bitcoin($balance["current"]["growth"]) . " / profit " . number_format($balance["current"]["yield"] * 100, 3) . "% / Performance fees (already deducted): BTC " . to_bitcoin($balance["current"]["fee"]) . "\n\n";
        }
    });
}

$all = [
    "total" => [
        "initial" => 0,
        "balance" => 0,
        "growth" => 0,
        "yield" => 0,
        "fee" => 0,
    ],
    "current" => [
        "initial" => 0,
        "balance" => 0,
        "growth" => 0,
        "yield" => 0,
        "fee" => 0,
    ],
];

$totalIndex = [];

foreach ($balances as $address => $balance){
    foreach ($balance["transactions"] as $tx){
        echo "\n";
        echo "tx " . $tx["txid"] ." @ " . date("Y-m-d H:i:s", $tx["timestamp"]) . " / ".($tx["signature"] === "referer" ? "REFERRAL BTC " . to_bitcoin($tx["referral_value"]) : "BTC " . to_bitcoin($tx["value"])) . ($tx["exit_timestamp"] !== PHP_INT_MAX ? " / WITHDRAWN" : "") ."\n";
        if ($tx["last_interest"] === null) {
            continue;
        }
        echo "\tcompounded BTC " . to_bitcoin($tx["balance"]) . " @ " . date("Y-m-d H:i:s", $tx["last_interest"]) . " / growth BTC " . to_bitcoin(($tx["balance"] - $tx["value"])) . " / profit " . ($tx["value"] === 0 ? 0 : round((($tx["balance"] - $tx["value"]) / $tx["value"]) * 100, 3)) . "%\n";
    }

    foreach ($balance["index"] as $entry) {
        $totalIndex[$entry["timestamp"]] = $entry;
    }

    foreach ($balance["total"] as $k => $v){
        $all["total"][$k] += $v;
    }

    foreach ($balance["current"] as $k => $v){
        $all["current"][$k] += $v;
    }
}

$all["total"]["yield"] = ($all["total"]["balance"] - $all["total"]["initial"]) / $all["total"]["initial"];
$all["current"]["yield"] = ($all["current"]["balance"] - $all["current"]["initial"]) / $all["current"]["initial"];


ksort($totalIndex);

echo "\n\n";
echo "LIFETIME TOTAL / Initial Investment: BTC " . to_bitcoin($all["total"]["initial"]) . " / Balance: BTC " . to_bitcoin($all["total"]["balance"]) . " / growth: BTC " . to_bitcoin($all["total"]["growth"]) . " / profit " . number_format($all["total"]["yield"] * 100, 3) . "% / Performance fees (already deducted): BTC " . to_bitcoin($all["total"]["fee"]) . "\n\n";
echo "CURRENT / Initial Investment: BTC " . to_bitcoin($all["current"]["initial"]) . " / Balance: BTC " . to_bitcoin($all["current"]["balance"]) . " / growth: BTC " . to_bitcoin($all["current"]["growth"]) . " / profit " . number_format($all["current"]["yield"] * 100, 3) . "% / Performance fees (already deducted): BTC " . to_bitcoin($all["current"]["fee"]) . "\n";
echo "\n\n";

foreach ($totalIndex as $entry) {
    echo date("Y-m-d H:i:s", $entry["timestamp"]) . " : " . $entry["value"] . "%".($entry["value"] > 0 ? " (".number_format($entry["value"] / 0.8, 3)."%)" : "")."\n";
}


