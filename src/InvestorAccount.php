<?php

namespace ARandomInvestor\AMFEIX;

class InvestorAccount{

    private $address;
    private $contract;

    public function __construct(string $address, StorageContract $contract) {
        $this->address = $address;
        $this->contract = $contract;
        bcscale(10);
    }

    public function getAddress() : string {
        return $this->address;
    }

    /**
     * @param callable $result
     */
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

    /**
     * @param array $index
     * @param callable $result
     */
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

                    $compoundedValue = bcadd($compoundedValue, bcmul($compoundedValue, bcdiv($entry["value"], 100)));
                }

                $tx["interest"] = $compoundedValue;

                //Interest value includes 20% performance fee
                if($tx["address"] === "referer"){
                    $tx["fee"] = 0;
                }else{
                    $tx["fee"] = bcmul(bcdiv($compoundedValue, "0.8"), "0.2"); //TODO: make this correct. Performance fee is not applied when yield is <= 0
                }
            }

            $result($txs);

        });
    }

    /**
     * @param callable $result
     * @throws \Exception
     */
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
                            $compoundedValue = bcdiv(bcsub(bcmul($tx["interest"], $tx["value"]), $tx["value"]), 10); //TODO: why is this not ((profit) / 0.8) * 0.1
                            if((0 === strncmp('-', (string) $compoundedValue, 1))){
                                //TODO: AMFEIX BUG If overall result is negative, value is positive instead???
                                $compoundedValue = (string)substr($compoundedValue, 1);
                            }
                            $totalCompounded = bcadd($totalCompounded, $compoundedValue);
                            $tx["balance"] = $compoundedValue;
                            if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                                $currentCompounded = bcadd($currentCompounded, $compoundedValue);
                            }
                            $tx["value"] = 0;
                        }else{
                            $compoundedValue = bcmul($tx["interest"], $tx["value"]);

                            $totalCompounded = bcadd($totalCompounded, $compoundedValue);
                            $totalValue = bcadd($totalValue, $tx["value"]);
                            $totalFees = bcadd($totalFees, bcmul($tx["fee"], bcsub($compoundedValue, $tx["value"])));
                            $tx["balance"] = $compoundedValue;

                            if($tx["exit_timestamp"] === PHP_INT_MAX){ //Not exited yet
                                $currentCompounded = bcadd($currentCompounded, $compoundedValue);
                                $currentValue = bcadd($currentValue, $tx["value"]);
                                $currentFees = bcadd($currentFees, bcmul($tx["fee"], bcsub($compoundedValue, $tx["value"])));
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
                            "growth" => bcsub($currentCompounded, $currentValue),
                            "yield" => $currentValue === 0 ? 0 : bcdiv(bcsub($currentCompounded, $currentValue), $currentValue),
                            "fee" => $currentFees,
                        ],
                        "total" => [
                            "initial" => $totalValue,
                            "balance" => $totalCompounded,
                            "growth" => bcsub($totalCompounded, $totalValue),
                            "yield" => $totalValue === 0 ? 0 : bcdiv(bcsub($totalCompounded, $totalValue), $totalValue),
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