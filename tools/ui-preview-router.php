<?php

// Local manual UI preview; force the disposable DB on every HTTP request.
$preview = realpath(__DIR__.'/../storage/app/ui-audit/preview.sqlite');
if (! $preview) {
    http_response_code(503);
    exit('Prepare the isolated UI preview first.');
}
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $preview, 'APP_ENV' => 'local', 'APP_URL' => 'http://localhost:8011'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
chdir(__DIR__.'/../public');

return require __DIR__.'/../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
