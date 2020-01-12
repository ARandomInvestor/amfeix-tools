<?php

namespace ARandomInvestor\AMFEIX\provider\bitcoin;

class Blockchain_com implements BitcoinProvider{
    public function getTransaction(string $txid) : ?array{
        $json = @json_decode(file_get_contents("https://blockchain.info/rawtx/" . $txid . "?cors=true"), true);
        if(!is_array($json)){
            return null;
        }

        return $json;
    }
}