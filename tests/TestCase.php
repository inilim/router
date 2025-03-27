<?php

namespace Inilim\Router\Test;

use Inilim\Tool\Refl;
use Inilim\Router\Router;
use Inilim\Request\Request;

class TestCase extends \PHPUnit\Framework\TestCase
{
    function overrideRequest(Router $router)
    {
        Refl::setValueProp($router, 'request', Request::createFromGlobals());
    }
}
