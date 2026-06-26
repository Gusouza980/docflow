<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| Garante que variáveis do .env local não sobrescrevam o ambiente isolado
| de testes antes da aplicação Laravel ser carregada.
|
*/

$cachedConfig = __DIR__.'/../bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    unlink($cachedConfig);
}

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:OzXDw6JakC1Q3ScvuT20S+ew4JPjV7ba6TiF9XeQB4E=',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'mysql',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'testing',
    'DB_USERNAME' => 'sail',
    'DB_PASSWORD' => 'password',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
];

foreach ($testingEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
