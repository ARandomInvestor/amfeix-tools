<?php

namespace ARandomInvestor\AMFEIX;


use phpseclib\Math\BigInteger;
use Web3\Contract;
use Web3\Providers\Provider;

class StorageContract {
    const STORAGE_CONTRACT_ADDRESS = "0xb0963da9baef08711583252f5000Df44D4F56925";

    private $contract;

    public function __construct(Provider $provider) {
        $ContractMeta = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "Storage.json"));
        $this->contract = new Contract($provider, $ContractMeta);
        $this->contract->at(self::STORAGE_CONTRACT_ADDRESS);
    }

    private static function getComplement(BigInteger $n, $bits = 256) {
        $mask = (new BigInteger(chr(0b10000000) . str_repeat("\x00", floor($bits / 8) - 1), 256));
        return $n->copy()->bitwise_and($mask)->multiply(new BigInteger(-1))->add($n->copy()->bitwise_and($mask->copy()->bitwise_not()));
    }

    public function getFundPerformace(callable $return) {
        $this->contract->call("getAll", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }
            /** @var BigInteger[] $dates */
            /** @var BigInteger[] $performances */
            $dates = $values["t"];
            $performances = $values["a"];
            $index = [];
            foreach ($dates as $i => $date) {
                $index[] = ["timestamp" => (int)$date->toString(), "value" => self::getComplement($performances[$i], 256)->toString() / 100000000];
            }

            $return($index);
        });
    }

    /**
     * @param callable $return
     */
    public function getAUM(callable $return) {
        $this->contract->call("aum", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }
            /** @var BigInteger[] $values */
            $return($values[0]->toString());
        });
    }


    /**
     * NOTE: this method takes a long time. You might want to set a longer connection timeout on Web3 connection.
     * @param callable $return
     */
    public function getInvestors(callable $return) {
      $this->contract->call("getAllInvestors", function ($err, $values) use ($return) {
          if ($err !== null) {
              throw $err;
          }
          /** @var string[] $values */
          $return($values);
      });
    }

    private function getAllValues(callable $count, callable $getter, callable $result, ...$args) {
      $count(...$args, ...[function($count) use ($getter, $result, $args){
        $list = [];
        if($count > 0){
          for($n = 0; $n < $count; ++$n){
            $getter(...$args, ...[$n, function($value) use (&$list, $result, $n, $count){
              $list[] = $value;
              if ($n === ($count - 1)) { //Done with all
                $result($list);
              }
            }]);
          }
        }else{
          $result($list);
        }

      }]);
    }

    /**
     * @param callable $return
     */
    public function getDepositAddressCount(callable $return) {
        $this->contract->call("fundDepositAddressesLength", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var BigInteger[] $values */
            $return($values[0]->toString());
        });
    }

    /**
     * @param int $n Address number to get
     * @param callable $return
     */
    public function getDepositAddress($n, callable $return) {
        $this->contract->call("fundDepositAddresses", $n, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var string[] $values */
            $return($values[0]);
        });
    }

    public function getDepositAddresses(callable $return){
      $this->getAllValues([$this, "getDepositAddressCount"], [$this, "getDepositAddress"], $return);
    }

    /**
     * @param callable $return
     */
    public function getFeeAddressCount(callable $return) {
        $this->contract->call("feeAddressesLength", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var BigInteger[] $values */
            $return($values[0]->toString());
        });
    }

    /**
     * @param int $n Address number to get
     * @param callable $return
     */
    public function getFeeAddress($n, callable $return) {
        $this->contract->call("feeAddresses", $n, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var string[] $values */
            $return($values[0]);
        });
    }

    public function getFeeAddresses(callable $return){
      $this->getAllValues([$this, "getFeeAddressCount"], [$this, "getFeeAddress"], $return);
    }

    /**
     * @param $address
     * @param callable $return
     */
    public function getTxCount($address, callable $return) {
        $this->contract->call("ntx", $address, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var BigInteger[] $values */
            $return($values[0]->toString());
        });
    }

    /**
     * @param $address
     * @param int $n Transaction number to get
     * @param callable $return
     */
    public function getTx($address, $n, callable $return) {
        $this->contract->call("getTx", $address, $n, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            $return(["txid" => $values[0], "pubkey" => $values[1], "address" => $values[2], "action" => $values[3]->toString(), "timestamp" => $values[4]->toString(),]);
        });
    }

    public function getTxs($address, callable $return){
      $this->getAllValues([$this, "getTxCount"], [$this, "getTx"], $return, $address);
    }
}
