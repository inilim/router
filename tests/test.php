<?php

require_once \dirname(__DIR__) . '/vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;
use Inilim\Router\Router;

Dump::init();
$_SERVER['REQUEST_URI'] = '/tests';


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

$router->route('GET', 'tests', static function () {
    // d(func_get_args());
}, ...$m);

$router->run();
