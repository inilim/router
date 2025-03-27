<?php

require_once \dirname(__DIR__) . '/vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Request\Request;
use Inilim\Router\Router;

Dump::init();


$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/show/{_INT_}/{_LETTERS_}';
$router = new Router(Request::createFromGlobals());

// ---------------------------------------------
// Assert
// ---------------------------------------------
$router->setHandleParamsController(function ($params, $request) {

    // $this->assertEquals(['123', 'abc'], $params);
    // $this->assertIsArray($params);
    // $this->assertInstanceOf(Request::class,  $request);

    return $params;
});
$router->route('GET', '/show/123/abc', function ($val1, $val2) {
    de(123123);
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
