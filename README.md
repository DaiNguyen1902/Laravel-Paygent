# Paygent-Laravel
How to install
```
composer require laravel-paygent/mdk
```
Add these below variables to the .env file:
```
PAYGENT_ENV=local
PAYGENT_MERCHANT_ID=
PAYGENT_CONNECT_ID=
PAYGENT_CONNECT_PASSWORD=
PAYGENT_TOKEN=
PAYGENT_PEM=
PAYGENT_CRT=
PAYGENT_TOKEN_HASH_KEY=
PAYGENT_TELEGRAM_VERSION=1.0
```
Add to the config/services.php file
```
'paygent' => [
    'env' => env('PAYGENT_ENV', 'local'),
    'merchant_id' => env('PAYGENT_MERCHANT_ID', ''),
    'connect_id' => env('PAYGENT_CONNECT_ID', ''),
    'connect_password' => env('PAYGENT_CONNECT_PASSWORD', ''),
    'token' => env('PAYGENT_TOKEN', ''),
    'pem' => app_path() . env('PAYGENT_PEM', ''),
    'crt' => app_path() . env('PAYGENT_CRT', ''),
    'telegram_version' => env('PAYGENT_TELEGRAM_VERSION', '1.0'),
]
```
Add to the config/logging.php file
```
'paygent' => [
    'driver' => 'daily',
    'path' => storage_path('logs/paygent/paygent.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 100,
],
```
How to make onetime payment request via Credit Card:
```
$result = app('paygent')->makeCreditCardPayment([
    "token" => "<credit card token>",
    "trading_id" => "<order id is stored in DB>",
    "payment_amount" => "<amount>",
]);
```
How to make payment request via ATM:
```
$result = app('paygent')->makeATM_PaymentRequest([
    "trading_id" => "<order id is stored in DB>",
    "payment_amount" => "<amount>",
    "customer_name" => "<customer name>",
    "customer_family_name" => "<customer familyname>",
    "payment_detail" => "payment detail",
    "payment_detail_kana" => "payment detail kana",
]);
```
How to make payment request via Convenience Store (number system):
```
$result = app('paygent')->makeConvenienceStorePaymentRequest([
    "trading_id" => "<order id is stored in DB>",
    "payment_amount" => "<amount>",
    "customer_name" => "<customer name>",
    "customer_family_name" => "<customer familyname>",
    "customer_tel" => "<customer telephone>",
    "cvs_company_id" => "<convenience store company id>", // '00C002' => 'Lawson' | '00C004' => 'Ministop' | '00C005' => 'FamilyMart' | '00C014' => 'DailyYamazaki' | '00C016' => 'SeicoMart'
]);
```
How to make payment request via NetBanking:
```
$result = app('paygent')->makeATM_PaymentRequest([
    "trading_id" => "<order id is stored in DB>",
    "amount" => "<amount>",
    "customer_name" => "<customer name>",
    "customer_family_name" => "<customer familyname>",
    "claim_kana" => "claim kana",
    "claim_kanji" => "claim kanji",
]);
```
How to custom send payment request
```
$paygent = app('paygent')->getPaygent();
$paygent->reqPut("<parameter name>", <parameter value>);
...
$result = $paygent->post();
// Response data is returned
$data = $paygent->resNext();
```
