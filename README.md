AMFEIX Tools
============

**DISCLAIMER**: This is a community project and is not part of AMFEIX or supported by them. You should NOT use any kind of private key or seeds here. These tools work using public Ethereum network contracts and information, and do not need them.

## Requirements
PHP > 7.1 and Composer. Run `$ composer update` to install other dependencies.

## Example: fund_balance.php
```
Usage: php fund_balance.php <HTTP Web3 endpoint> <Investor Address>
	<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/
	<Investor Address>: The public address on Ethereum tied to your investor account. You can find this on the browser's console log on AMFEIX site. Do NOT use any private key or seed here.
```


You can call this script to generate a balance for your investor account and list related performances of AMFEIX. You will need your *Investor Address*, which you can find on your browser's developer tools console after logging into AMFEIX portal. It should be outputted as part of the first few lines in the console.
If you don't want to setup a local node, you can get a [free infura.io account](https://infura.io/) (select Core FREE when registering) and run calls against its API.
```
$ php fund_balance.php "https://mainnet.infura.io/v3/YOUR_INFURA_API_KEY" "0x6507dd87a08adbffde9343e65936d93bcdfa95f7"
```

You can also use a local node, like so
```
$ php fund_balance.php "http://localhost:8545" "0x6507dd87a08adbffde9343e65936d93bcdfa95f7"
```

It will produce an output similar to this
```
Acquiring AMFEIX index
Processing investor 0x6507dd87a08adbffde9343e65936d93bcdfa95f7
Fetching 4 tx
Processing BTC tx IN  70af10fd4a3d732468d15e95a677ac8d79cd3304d6bf3300c040fec967f1242c
Processing BTC tx OUT 70af10fd4a3d732468d15e95a677ac8d79cd3304d6bf3300c040fec967f1242c
Processing BTC tx IN  b327b3a2e75499b61ef01d1e92ab5bef64e8210808be3c5ba3c73f4b22493342
Processing BTC tx OUT b327b3a2e75499b61ef01d1e92ab5bef64e8210808be3c5ba3c73f4b22493342

tx 70af10fd4a3d732468d15e95a677ac8d79cd3304d6bf3300c040fec967f1242c @ 2019-06-24 16:36:21 / BTC 0.00350000
	compounded BTC 0.00353010 @ 2019-06-25 19:49:43 / growth BTC 0.00003010 0.86%

tx b327b3a2e75499b61ef01d1e92ab5bef64e8210808be3c5ba3c73f4b22493342 @ 2019-06-27 11:34:50 / BTC 0.00303000
	compounded BTC 0.00301500 @ 2019-06-27 21:04:41 / growth BTC -0.00001500 -0.495%


CURRENT / Current Initial Investment: BTC 0.00000000 / Current: BTC 0.00000000 / growth: BTC 0.00000000 0% / Profit fees: BTC 0.00000000


2019-06-24 20:42:52 : 0.86%
2019-06-25 19:49:43 : 0%
2019-06-26 22:14:12 : 2.59%
2019-06-27 21:04:41 : -0.495%
```