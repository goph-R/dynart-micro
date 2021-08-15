<?php

// This file should be in your document root folder!

require_once __DIR__ . '/vendor/autoload.php'; 

use Dynart\Micro\App;

$app = new App(['config.ini.php']);

$app->route('/', function(App $app) {
    return $app->view()->layout('index');
});

$app->route('/example/?', function(App $app, $parameter) {
    return $app->view()->layout('index', [
        'parameter' => $parameter
    ]);
});

$app->run();

