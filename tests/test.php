<?php

require_once \dirname(__DIR__) . '/vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;
use Inilim\Router\Router;

Dump::init();


$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/show/qwerty_uiopasdfghjklzxcvbnm';
$router = new \Inilim\Router\Router(Request::createFromGlobals());

$router->route('GET', '/show/{letters}', static function ($string) {
    echo 'letters: ' . $string;
});

$router->run();


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
