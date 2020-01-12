<?php

namespace ARandomInvestor\AMFEIX\provider\bitcoin;

interface BitcoinProvider{
    public function getTransaction(string $txid) : ?array;
}