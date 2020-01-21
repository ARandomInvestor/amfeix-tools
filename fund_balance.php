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

$web3 = new Web3(new \Web3\Providers\HttpProvider(new \Web3\RequestManagers\HttpRequestManager($argv[1], 10)));

$ob = new StorageContract($web3->getProvider(), new Blockchain_com());

$investorAddress = trim($argv[2]);

if($investorAddress === ""){
    echo "Please provide an (ETH) Investor Address tied to contract.\n";
    exit(1);
}

//$ob->setDebug(true);

$investor = new \ARandomInvestor\AMFEIX\InvestorAccount($investorAddress, $ob);
echo "Querying Investor Address $investorAddress\n";

$investor->getBalance(function ($balance){
    foreach ($balance["transactions"] as $tx){
        echo "\n";
        echo "tx " . $tx["txid"] ." @ " . date("Y-m-d H:i:s", $tx["timestamp"]) . " / ".($tx["address"] === "referer" ? "REFERRAL" : "BTC " . to_bitcoin($tx["value"])) . ($tx["exit_timestamp"] !== PHP_INT_MAX ? " / WITHDRAWN" : "") ."\n";
        if ($tx["last_interest"] === null) {
            continue;
        }
        echo "\tcompounded BTC " . to_bitcoin($tx["balance"]) . " @ " . date("Y-m-d H:i:s", $tx["last_interest"]) . " / growth BTC " . to_bitcoin(($tx["balance"] - $tx["value"])) . " / profit " . ($tx["value"] === 0 ? 0 : round((($tx["balance"] - $tx["value"]) / $tx["value"]) * 100, 3)) . "%\n";
    }

    echo "\n\n";
    echo "LIFETIME TOTAL / Initial Investment: BTC " . to_bitcoin($balance["total"]["initial"]) . " / Balance: BTC " . to_bitcoin($balance["total"]["balance"]) . " / growth: BTC " . to_bitcoin($balance["total"]["growth"]) . " / profit " . number_format($balance["total"]["yield"] * 100, 3) . "% / Performance fees (already deducted): BTC " . to_bitcoin($balance["total"]["fee"]) . "\n\n";
    echo "CURRENT / Initial Investment: BTC " . to_bitcoin($balance["current"]["initial"]) . " / Balance: BTC " . to_bitcoin($balance["current"]["balance"]) . " / growth: BTC " . to_bitcoin($balance["current"]["growth"]) . " / profit " . number_format($balance["current"]["yield"] * 100, 3) . "% / Performance fees (already deducted): BTC " . to_bitcoin($balance["current"]["fee"]) . "\n";
    echo "\n\n";

    foreach ($balance["index"] as $entry) {
        echo date("Y-m-d H:i:s", $entry["timestamp"]) . " : " . $entry["value"] . "%".($entry["value"] > 0 ? " (".number_format($entry["value"] / 0.8, 3)."%)" : "")."\n";
    }
});
