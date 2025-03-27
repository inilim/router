<?php

require_once \dirname(__DIR__) . '/vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;
use Inilim\Router\Router;

Dump::init();





de($_SERVER);

de();

$_SERVER['REQUEST_URI'] = '/tests/123/';

class Test
{
    function __invoke()
    {
        d(func_get_args());
    }
}


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

$router->route('GET', 'tests/{int}', 'Test@__invoke', ...$m);

// de($router);

$router->run();
