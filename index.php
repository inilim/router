<?php

require_once __DIR__ . '/vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;
use Inilim\Router\Router;

Dump::init();

$m = [
    static function () {
        echo 'm1';
    },
    static function () {
        echo 'm2';
    },
    static function () {
        echo 'm3';
    },
];

$router = new Router(Request::createFromGlobals());

$router->route('GET', '/tests/test.php', static function () {
    // d(func_get_args());
}, ...$m);

$router->run();
