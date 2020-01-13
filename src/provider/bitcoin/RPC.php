<?php

namespace ARandomInvestor\AMFEIX\provider\bitcoin;

use Denpa\Bitcoin\Client;
use function Denpa\Bitcoin\to_satoshi;

class RPC implements BitcoinProvider{

    private $client;
    public function __construct(Client $client) {
        $this->client = $client;
    }

    public function getTransaction(string $txid) : ?array{
        //TODO: make this more compatible with other outputs
        $tx = $this->client->getrawtransaction($txid, true)->toArray();
        $tx["inputs"] = $tx["vin"];
        $tx["out"] = $tx["vout"];
        $tx["ver"] = $tx["version"];
        $tx["lock_time"] = $tx["locktime"];
        foreach ($tx["out"] as &$output){
            $output["value"] = (int) to_satoshi($output["value"]);
            $output["addr"] = $output["scriptPubKey"]["addresses"][0];
        }

        unset($tx["vin"], $tx["vout"], $tx["version"], $tx["locktime"]);
        return $tx;
    }
}