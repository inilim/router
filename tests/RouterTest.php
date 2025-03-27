<?php

namespace Inilim\Router\Test;

use Inilim\Dump\Dump;
use Inilim\Router\Router;
use Inilim\Request\Request;
use Inilim\Router\Test\TestCase;
use Inilim\Router\Test\ForTest\RouterTestController;
use Inilim\Router\Test\ForTest\RouterTestWithConstructController;

Dump::init();

class RouterTest extends TestCase
{
    function setUp(): void
    {
        // Clear SCRIPT_NAME because bramus/router tries to guess the subfolder the script is run in
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        // Default request method to GET
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Default SERVER_PROTOCOL method to HTTP/1.1
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    }

    function testRouteWithoutMethodClass()
    {
        $_SERVER['REQUEST_URI'] = '/show/';

        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/show/', RouterTestWithConstructController::class);

        ob_start();

        $router->run();
        $this->assertEquals('__construct', ob_get_contents());

        // cleanup
        ob_end_clean();
    }

    function testMethod_isOverrideHead()
    {
        $router = new Router(Request::createFromGlobals());
        $method = new \ReflectionMethod(
            Router::class,
            'isOverrideHead'
        );
        $method->setAccessible(true);

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->overrideRequest($router);
        $this->assertTrue($method->invoke($router));

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->overrideRequest($router);
        $this->assertFalse($method->invoke($router));

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->overrideRequest($router);
        $this->assertFalse($method->invoke($router));
    }

    function testMethod_setHandleParamsController()
    {
        $_SERVER['REQUEST_URI'] = '/show/123/abc';
        $router = new Router(Request::createFromGlobals());

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $router->setHandleParamsController(function ($params, $request) {

            $this->assertIsArray($params);
            $this->assertEquals(['123', 'abc'], $params);
            $this->assertInstanceOf(Request::class,  $request);

            $params[0] = \intval($params[0]);
            $params[1] = \strtoupper($params[1]);

            return $params;
        });
        $router->route('GET', '/show/{_INT_}/{_LETTERS_}', function ($val1, $val2) {
            $this->assertEquals(123, $val1);
            $this->assertEquals('ABC', $val2);
        });
        $router->run();
    }

    function testMethod_setHandleParamsMiddleware()
    {
        $_SERVER['REQUEST_URI'] = '/show/123/abc';
        $router = new Router(Request::createFromGlobals());

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $router->setHandleParamsMiddleware(function ($params, $request) {

            $this->assertIsArray($params);
            $this->assertEquals(['123', 'abc'], $params);
            $this->assertInstanceOf(Request::class,  $request);

            $params[0] = \intval($params[0]);
            $params[1] = \strtoupper($params[1]);

            return $params;
        });

        $router->middleware('GET', '/show/{_INT_}/{_LETTERS_}', function ($val1, $val2) {
            $this->assertEquals(123, $val1);
            $this->assertEquals('ABC', $val2);
        });

        $router->route('GET', '/show/{_INT_}/{_LETTERS_}', function ($val1, $val2) {
            $this->assertEquals('123', $val1);
            $this->assertEquals('abc', $val2);
        });
        $router->run();
    }

    function testMethod_prepareMethod()
    {
        $router = new Router(Request::createFromGlobals());
        $method = new \ReflectionMethod(
            Router::class,
            'prepareMethod'
        );
        $method->setAccessible(true);

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $this->assertEquals($method->invoke($router, 'ALL'), 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD');
        $this->assertEquals($method->invoke($router, 'GET'), 'GET');
        $this->assertEquals($method->invoke($router, 'HEAD'), 'HEAD');
        $this->assertEquals($method->invoke($router, 'DELETE'), 'DELETE');
    }

    function testMethod_getClassHandle()
    {
        $_SERVER['REQUEST_URI'] = '/show/and/';
        $router = new Router(Request::createFromGlobals());

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $router->route(
            'GET',
            '/show/and/',
            RouterTestController::class . '@empty'
        );
        $router->run();
        $this->assertEquals($router->getClassHandle(), RouterTestController::class);

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $router->route(
            'GET',
            '/show/and/',
            static function () {}
        );
        $router->run();
        $this->assertEquals($router->getClassHandle(), \Closure::class);
    }

    function testMethod_getNumHundledMiddleware()
    {
        $_SERVER['REQUEST_URI'] = '/show/and/';
        $router = new Router(Request::createFromGlobals());

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        // $this->overrideRequest($router);
        $router->route(
            'GET',
            '/show/and/',
            static function () {}, // c
            static function () {}, // m
            static function () {}, // m
            static function () {}, // m
        );
        $router->run();

        $this->assertEquals($router->getNumHundledMiddleware(), 3);
    }

    function testHttpMethodOverrideHead()
    {
        // Fake the request method to being POST and override it
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $router = new Router(Request::createFromGlobals());

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $this->assertEquals($router->getRequestMethod(), 'HEAD');

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $method = new \ReflectionMethod(
            Router::class,
            'getRequestMethodWithOverride'
        );

        $method->setAccessible(true);

        $this->assertEquals($method->invoke($router), 'GET');
    }

    function testMarkPatternLetters()
    {
        $router = new Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/qwertyuiopasdfghjklzxcvbnm';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($value) {
            echo 'letters: ' . $value;
        });
        $router->run();
        $this->assertEquals('letters: qwertyuiopasdfghjklzxcvbnm', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/qwerty_uiopasdfghjklzxcvbnm';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($value) {
            echo 'letters: ' . $value;
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/QWERTYUIOPASDFGHJKLZXCVBNM';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($value) {
            echo 'letters: ' . $value;
        });
        $router->run();
        $this->assertEquals('letters: QWERTYUIOPASDFGHJKLZXCVBNM', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/Привет';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($value) {
            echo 'letters: ' . $value;
        });
        $router->run();
        $this->assertEquals('letters: Привет', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Clean
        // ---------------------------------------------

        // cleanup
        ob_end_clean();
    }

    function testMarkPatternNumbers()
    {
        $router = new Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/string';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_}', static function ($value) {
            echo '$value';
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/1726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_}', static function ($value) {
            echo 'numbers: ' . $value;
        });
        $router->run();
        $this->assertEquals('numbers: 1726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/01923892391023891203912381726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_}', static function ($value) {
            echo 'numbers: ' . $value;
        });
        $router->run();
        $this->assertEquals('numbers: 01923892391023891203912381726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/-01923892391023891203912381726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_}', static function ($value) {
            echo 'numbers: ' . $value;
        });
        $router->run();
        $this->assertEquals('numbers: -01923892391023891203912381726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Clean
        // ---------------------------------------------

        // cleanup
        ob_end_clean();
    }

    function testMarkPatternNumbersUnsigned()
    {
        $router = new Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/string';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_UNSIGNED_}', static function ($value) {
            echo '$value';
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/1726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_UNSIGNED_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEquals('numbers: 1726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/01923892391023891203912381726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_UNSIGNED_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEquals('numbers: 01923892391023891203912381726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/-01923892391023891203912381726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_UNSIGNED_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Clean
        // ---------------------------------------------

        // cleanup
        ob_end_clean();
    }

    function testMarkPatternInt()
    {
        $router = new Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/string';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_}', static function ($value) {
            echo '$value';
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/1726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEquals('numbers: 1726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/09876';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/-26378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEquals('numbers: -26378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Clean
        // ---------------------------------------------

        // cleanup
        ob_end_clean();
    }

    function testMarkPatternIntUnsigned()
    {
        $router = new Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/string';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_UNSIGNED_}', static function ($value) {
            echo '$value';
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/1726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_UNSIGNED_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEquals('numbers: 1726378213', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/09876';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_UNSIGNED_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/-26378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_INT_UNSIGNED_}', static function ($string) {
            echo 'numbers: ' . $string;
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Clean
        // ---------------------------------------------

        // cleanup
        ob_end_clean();
    }
}
