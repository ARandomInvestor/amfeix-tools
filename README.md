AMFEIX Tools
============

**DISCLAIMER**: This is a community project and is not part of AMFEIX or supported by them. You should NOT use any kind of private key or seeds here. These tools work using public Ethereum network contracts and information, and do not need them.

## Requirements
PHP > 7.1 and [Composer](https://getcomposer.org/). Run `$ composer update` to install other dependencies afterwards.

### Ethereum request endpoint
To run calls against the Contract you need either a local Ethereum node (like [geth](https://ethereum.github.io/go-ethereum/), or [parity](https://www.parity.io/)) that can run such queries, or set up a [free infura.io account](https://infura.io/) (select Core FREE when registering) and run calls against its API.

There is also more information on the [web3py info page](https://web3py.readthedocs.io/en/stable/node.html)



If you end up using a local node, *ETHEREUM_API_NODE_URL* will look something like this `http://localhost:8545`.


Otherwise if you use infura.io, you will end up with this *ETHEREUM_API_NODE_URL* after replacing *YOUR_INFURA_API_KEY* with your *PROJECT ID*:  `https://mainnet.infura.io/v3/YOUR_INFURA_API_KEY`

You might have to whitelist contract address `0xb0963da9baef08711583252f5000Df44D4F56925` under project security, which is AMFEIX Storage contract address.


## Example: fund_balance.php
```
Usage: php fund_balance.php <HTTP Web3 endpoint> <Investor Address>
	<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/
	<Investor Address>: The public address on Ethereum tied to your investor account. You can find this on the browser's console log on AMFEIX site. Do NOT use any private key or seed here.
```


You can call this script to generate a balance for your investor account and list related performances of AMFEIX. You will need your *Investor Address*, which you can find on your browser's developer tools console after logging into AMFEIX portal. It should be outputted as part of the first few lines in the console.

```
$ php fund_balance.php "ETHEREUM_API_NODE_URL" "0x6507dd87a08adbffde9343e65936d93bcdfa95f7"
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


## Example: fund_performance.php
```
Usage: php fund_performance.php <HTTP Web3 endpoint>
	<HTTP Web3 endpoint>: Could be your local node, or a remote one like https://infura.io/
```

You can call this script to list the performances of AMFEIX. 

```
$ php fund_performance.php "ETHEREUM_API_NODE_URL"
```

It will produce an output similar to this
```
[...]
2019-11-01 23:04:17 : 0%
2019-11-02 22:38:42 : 0.07%
2019-11-03 22:25:46 : 0.18%
2019-11-04 22:56:11 : 0.03%
2019-11-05 23:27:16 : 0%
2019-11-06 22:28:42 : 0.05%
2019-11-07 22:55:01 : -0.02%
2019-11-08 23:00:34 : 1.59%
2019-11-09 23:18:56 : 0%
2019-11-10 23:28:12 : 1.02%
2019-11-11 23:14:04 : 0.11%
2019-11-12 23:12:58 : 0%
2019-11-13 23:15:45 : 0%
2019-11-14 22:57:57 : 0.15%
2019-11-15 22:58:02 : 0.48%
2019-11-16 23:09:38 : 0%
2019-11-17 23:05:18 : 0.1%
2019-11-18 23:03:08 : 2.16%
2019-11-19 22:53:20 : -0.25%
2019-11-20 22:52:38 : 0%
2019-11-21 23:19:30 : 0.98%
2019-11-22 23:12:24 : 0.65%
2019-11-23 23:09:16 : 0%
2019-11-24 23:06:49 : -0.18%
2019-11-25 22:52:33 : 1.08%
2019-11-26 23:08:12 : 0%
2019-11-27 23:04:45 : 0.95%
2019-11-28 22:56:22 : 0%
2019-11-29 23:07:21 : 0.53%
2019-11-30 23:06:36 : -0.88%
=== Total 2019-11 November: Sum of values 8.80% / Compounded growth 9.128% ===

2019-12-01 23:05:40 : 0%
2019-12-02 23:05:33 : 0.1%
2019-12-03 23:00:57 : 0%
2019-12-04 23:14:00 : 0.2%
2019-12-05 23:12:02 : 0.63%
2019-12-06 23:09:06 : 0.1%
2019-12-07 23:07:21 : 0%
2019-12-08 23:06:23 : 0%
2019-12-09 23:01:37 : 0.27%
2019-12-10 21:30:05 : 0.12%
2019-12-11 21:36:59 : 0%
2019-12-12 21:03:09 : 0.06%
2019-12-13 21:14:13 : 0%
2019-12-14 21:22:44 : 0.19%
2019-12-15 21:14:56 : 0.06%
2019-12-16 21:29:55 : 0.64%
2019-12-17 21:38:13 : 0.67%
2019-12-18 22:11:36 : 0.59%
2019-12-19 22:11:00 : 0.81%
2019-12-20 21:36:45 : 0%
2019-12-21 21:06:19 : 0%
2019-12-22 20:05:05 : 0.24%
2019-12-23 21:44:58 : 0.32%
2019-12-24 19:50:30 : 0%
2019-12-25 19:23:24 : 0%
2019-12-26 22:37:27 : 0%
2019-12-27 21:14:02 : 0%
2019-12-28 22:28:28 : 0.11%
2019-12-29 21:59:07 : 0.15%
2019-12-30 21:46:36 : -0.12%
2019-12-31 21:30:01 : 0%
=== Total 2019-12 December: Sum of values 5.14% / Compounded growth 5.260% ===

2020-01-01 21:26:52 : 0%
2020-01-02 21:56:31 : 0.95%
2020-01-03 22:43:56 : 0.3%
2020-01-04 22:06:06 : 0%
2020-01-05 22:02:16 : 0.07%
2020-01-06 21:51:24 : 0.13%
2020-01-07 21:58:52 : 0.96%
2020-01-08 22:16:47 : -0.09%
```

### Tips
Feel like tipping? Why though, this could have been made by anyone. Better invest it back.

If you still feel like it: `3KPEV9dAS7fHEAigkW9TQdNQKPko6cGrbY`
