# Paygent-Laravel
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
How to get paygent instance:
```
$paygent = app('paygent')->getPaygent();
```
