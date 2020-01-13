<?php

namespace ARandomInvestor\AMFEIX;

class InvestorAccount{

    private $address;
    private $contract;

    public function __construct(string $address, StorageContract $contract) {
        $this->address = $address;
        $this->contract = $contract;
    }

    public function getTransactions(callable $result) {
        $this->contract->getTxs($this->address, function ($values) use ($result) {
            $txs = [];
            foreach($values as $v){
                if($v["action"] == 0){
                    $txs[$v["txid"]] = $v;
                    $txs[$v["txid"]]["exit_timestamp"] = PHP_INT_MAX;
                }else if ($v["action"] == 1){
                    $txs[$v["txid"]]["exit_timestamp"] = $v["timestamp"];
                }
            }

            $result($txs);
        });
    }

    public function getTransactionsWithInterest(array $index, callable $result){
        $this->getTransactions(function ($txs) use($index, $result){
            foreach ($txs as &$tx){
                $compoundedValue = 1;
                $tx["last_interest"] = null;
                foreach ($index as $entry) {
                    //TODO: check why this is not like this: if($entry["timestamp"] < $tx["timestamp"] or ($entry["timestamp"] >= $tx["timestamp"] and $lastTime < $tx["timestamp"])){
                    //^ seems like this is intended by fund, how nice of them

                    if ($entry["timestamp"] > $tx["exit_timestamp"]) {
                        break;
                    }

                    if ($entry["timestamp"] < $tx["timestamp"]) {
                        continue;
                    }

                    $tx["last_interest"] = $entry["timestamp"];

                    $compoundedValue += $compoundedValue * ($entry["value"] / 100);
                }

                $tx["interest"] = $compoundedValue;
                if($tx["address"] === "referer"){
                    $tx["fee"] = 0;
                }else{
                    $tx["fee"] = $compoundedValue * 0.2;
                }
            }

            $result($txs);

        });
    }

    public function getBalance(callable $result){

    }
}