<?php

namespace ARandomInvestor\AMFEIX\provider\bitcoin;

class Blockchain_com implements BitcoinProvider{

    private $cache = [];

    public function clearCache(){
        $this->cache = [];
    }

    protected function getCache($k){
        return (isset($this->cache[$k]) and $this->cache[$k][1] >= time()) ? $this->cache[$k][0] : null;
    }

    protected function setCache($k, $v, $age = 15){
        $this->cache[$k] = [$v, time() + $age];
    }

    public function getTransaction(string $txid) : ?array{

        if(($cache = $this->getCache("getTransaction{".$txid."}")) !== null){
            return $cache;
        }

        $json = @json_decode(file_get_contents("https://blockchain.info/rawtx/" . $txid . "?cors=true"), true);
        if(!is_array($json)){
            return null;
        }

        $this->setCache("getTransaction{".$txid."}", $json);

        return $json;
    }
}