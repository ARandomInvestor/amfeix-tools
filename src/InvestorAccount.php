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
        if($this->contract->getBitcoin() === null){
            throw new \Exception('$this->contract->getBitcoin() === null');
        }

        $this->contract->getDepositAddresses(function($depositAddresses) use ($result) {
            $this->contract->getFundPerformace(function ($index) use ($result, $depositAddresses) {
                $this->getTransactionsWithInterest($index, function ($transactions) use ($result, $index, $depositAddresses){


                    $currentValue = 0;
                    $currentCompounded = 0;
                    $currentFees = 0;
                    $totalValue = 0;
                    $totalCompounded = 0;
                    $totalFees = 0;
                    $firstInvestment = PHP_INT_MAX;
                    $lastInvestment = [null, 0];

                    foreach($transactions as &$tx){
                        $data = $this->contract->getBitcoin()->getTransaction($tx["txid"]);
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

                        if($tx["address"] === "referer"){
                            $compoundedValue = (($tx["interest"] * $tx["value"]) - $tx["value"]) * 0.1;
                            $totalCompounded += $compoundedValue;
                            $tx["balance"] = $compoundedValue;
                            if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                                $currentCompounded += $compoundedValue;
                            }
                            $tx["value"] = 0;
                        }else{
                            $compoundedValue = $tx["interest"] * $tx["value"];

                            $totalCompounded += $compoundedValue;
                            $totalValue += $tx["value"];
                            $totalFees += $tx["fee"] * ($compoundedValue - $tx["value"]);
                            $tx["balance"] = $compoundedValue;

                            if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                                $currentCompounded += $compoundedValue;
                                $currentValue += $tx["value"];
                                $currentFees += $tx["fee"] * ($compoundedValue - $tx["value"]);
                            }
                        }
                    }

                    $relatedIndex = [];

                    foreach ($index as $entry) {
                        if ($entry["timestamp"] < $firstInvestment) {
                            continue;
                        }
                        if($lastInvestment[1] !== 0 and $entry["timestamp"] > $lastInvestment[1]){
                            break;
                        }
                        $relatedIndex[] = $entry;
                    }

                    $balance = [
                        "current" => [
                            "initial" => $currentValue,
                            "balance" => $currentCompounded,
                            "growth" => $currentCompounded - $currentValue,
                            "yield" => $currentValue === 0 ? 0 : ($currentCompounded - $currentValue) / $currentValue,
                            "fee" => $currentFees,
                        ],
                        "total" => [
                            "initial" => $totalValue,
                            "balance" => $totalCompounded,
                            "growth" => $totalCompounded - $totalValue,
                            "yield" => $totalValue === 0 ? 0 : ($totalCompounded - $totalValue) / $totalValue,
                            "fee" => $totalFees,
                        ],
                        "transactions" => $transactions,
                        "index" => $relatedIndex,
                    ];

                    $result($balance);
                });
            });
        });
    }
}