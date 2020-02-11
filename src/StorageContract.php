<?php

namespace ARandomInvestor\AMFEIX;


use ARandomInvestor\AMFEIX\provider\bitcoin\BitcoinProvider;
use phpseclib\Math\BigInteger;
use Web3\Contract;
use Web3\Providers\Provider;

class StorageContract {
    const STORAGE_CONTRACT_ADDRESS = "0xb0963da9baef08711583252f5000Df44D4F56925";

    private $contract;
    private $btc;
    private $debug = false;

    private $cache = [];

    public function __construct(Provider $provider, BitcoinProvider $btc = null) {
        $ContractMeta = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "abi" . DIRECTORY_SEPARATOR . self::STORAGE_CONTRACT_ADDRESS . ".json"));
        $this->contract = new Contract($provider, $ContractMeta);
        $this->contract->at(self::STORAGE_CONTRACT_ADDRESS);
        $this->btc = $btc;
    }

    public function setDebug(bool $debug){
        $this->debug = $debug;
    }

    public function getContract() : Contract{
        return $this->contract;
    }

    public function getBitcoin() : ?BitcoinProvider{
        return $this->btc;
    }

    private static function getComplement(BigInteger $n, $bits = 256) {
        $mask = (new BigInteger(chr(0b10000000) . str_repeat("\x00", floor($bits / 8) - 1), 256));
        return $n->copy()->bitwise_and($mask)->multiply(new BigInteger(-1))->add($n->copy()->bitwise_and($mask->copy()->bitwise_not()));
    }

    public function clearCache(){
        $this->cache = [];
    }

    protected function getCache($k){
        return (isset($this->cache[$k]) and $this->cache[$k][1] >= time()) ? $this->cache[$k][0] : null;
    }

    protected function setCache($k, $v, $age = 15){
        $this->cache[$k] = [$v, time() + $age];
    }


    /**
     * Returned performance values include 20% performance fee applied
     *
     * @param callable $return
     */
    public function getFundPerformace(callable $return) {
        if($this->debug){
            echo "Fetching AMFEIX Performance Index\n";
        }

        if(($cache = $this->getCache("getFundPerformace")) !== null){
            $return($cache);
            return;
        }

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
                $index[] = ["timestamp" => (int)$date->toString(), "value" => bcdiv(self::getComplement($performances[$i], 256)->toString(), 100000000, 2)];
            }

            $this->setCache("getFundPerformace", $index);

            $return($index);
        });
    }

    /**
     * @param callable $return
     */
    public function getAUM(callable $return) {
        if($this->debug){
            echo "Fetching AMFEIX AUM\n";
        }

        if(($cache = $this->getCache("getAUM")) !== null){
            $return($cache);
            return;
        }

        $this->contract->call("aum", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }
            /** @var BigInteger[] $values */

            $this->setCache("getAUM", $values[0]->toString());

            $return($values[0]->toString());
        });
    }


    /**
     * NOTE: this method takes a long time. You might want to set a longer connection timeout on Web3 connection.
     * @param callable $return
     */
    public function getInvestors(callable $return) {
        if($this->debug){
            echo "Fetching all investor addresses\n";
        }

        if(($cache = $this->getCache("getInvestors")) !== null){
            $return($cache);
            return;
        }

        $this->contract->call("getAllInvestors", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }
            /** @var string[][] $values */

            $this->setCache("getInvestors", $values[0]);

            $return($values[0]);
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
        if(($cache = $this->getCache("getDepositAddressCount")) !== null){
            $return($cache);
            return;
        }

        $this->contract->call("fundDepositAddressesLength", function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var BigInteger[] $values */

            $this->setCache("getDepositAddressCount", $values[0]->toString());

            $return($values[0]->toString());
        });
    }

    /**
     * @param int $n Address number to get
     * @param callable $return
     */
    public function getDepositAddress(int $n, callable $return) {
        if($this->debug){
            echo "Fetching deposit address $n\n";
        }

        if(($cache = $this->getCache("getDepositAddress{".$n."}")) !== null){
            $return($cache);
            return;
        }

        $this->contract->call("fundDepositAddresses", $n, function ($err, $values) use ($n, $return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var string[] $values */

            if($this->debug){
                echo "Deposit address ".$values[0]."\n";
            }

            $this->setCache("getDepositAddress{".$n."}", $values[0]);

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
    public function getFeeAddress(int $n, callable $return) {
        $this->contract->call("feeAddresses", $n, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var string[] $values */

            if($this->debug){
                echo "Fee address ".$values[0]."\n";
            }
            $return($values[0]);
        });
    }

    public function getFeeAddresses(callable $return){
      $this->getAllValues([$this, "getFeeAddressCount"], [$this, "getFeeAddress"], $return);
    }

    /**
     * @param string $address
     * @param callable $return
     */
    public function getTxCount(string $address, callable $return) {
        $this->contract->call("ntx", $address, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            /** @var BigInteger[] $values */
            $return($values[0]->toString());
        });
    }

    /**
     * @param string $address
     * @param int $n Transaction number to get
     * @param callable $return
     */
    public function getTx(string $address, int $n, callable $return) {

        if($this->debug){
            echo "Fetching transaction $n for address ".$address."\n";
        }
        $this->contract->call("getTx", $address, $n, function ($err, $values) use ($return) {
            if ($err !== null) {
                throw $err;
            }

            $tx = ["txid" => $values[0], "pubkey" => $values[1], "signature" => $values[2], "action" => $values[3]->toString(), "timestamp" => $values[4]->toString(),];

            if($this->debug){
                echo "Fetched tx " . ($tx["action"] == 0 ? "IN " : ($tx["action"] == 1 ? "OUT" : "UNKNOWN")) . " ".  $tx["txid"] . " @ " . date("Y-m-d H:i:s", $tx["timestamp"]) ."\n";
            }
            $return($tx);
        });
    }

    public function getTxs($address, callable $return){
      $this->getAllValues([$this, "getTxCount"], [$this, "getTx"], $return, $address);
    }


    /**
     * Tries to obtain and decode contract ABI from public AMFEIX json webpack
     * @param $url
     * @throws \Exception
     */
    private function tryObtainContractABI($url){
        $src = file_get_contents($url);
        if(preg_match($d = "#abi:(\\[.+\\]),metadata#s", $src, $matches) > 0){
            $json = preg_replace(["#:!0#", "#:!1#", "#([\\{,])([a-zA-Z0-9]+)([:])#"], [":true", ":false", '$1"$2"$3'], $matches[1]);
            $data = json_decode($json, true);

            if(!is_array($data)){
                throw new \Exception("Unable to parse found ABI segment");
            }
        }

        throw new \Exception("Unable to find ABI segment");
    }
}
