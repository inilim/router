<?php

require_once __DIR__ . '/vendor/autoload.php';

use Inilim\Dump\Dump;
use Inilim\Router\Router;
use Inilim\Request\Request;

Dump::init();

$router = new Router(new Request());
$headers = $router->request->getHeaders();
de($headers);

$router->get('/', static function () {});


$router->run();
// de($a);
