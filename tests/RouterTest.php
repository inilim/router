<?php

namespace Inilim\Router\Test;

use Inilim\Dump\Dump;
use Inilim\Request\Request;
use Inilim\Router\Test\TestCase;
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

        $router = new \Inilim\Router\Router(Request::createFromGlobals());
        $router->route('GET', '/show/', RouterTestWithConstructController::class);

        ob_start();

        $router->run();
        $this->assertEquals('__construct', ob_get_contents());

        // cleanup
        ob_end_clean();
    }

    function testMarkPatternLetter()
    {
        $router = new \Inilim\Router\Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/qwertyuiopasdfghjklzxcvbnm';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($string) {
            echo 'letters: ' . $string;
        });
        $router->run();
        $this->assertEquals('letters: qwertyuiopasdfghjklzxcvbnm', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/qwerty_uiopasdfghjklzxcvbnm';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($string) {
            echo 'letters: ' . $string;
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/QWERTYUIOPASDFGHJKLZXCVBNM';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($string) {
            echo 'letters: ' . $string;
        });
        $router->run();
        $this->assertEquals('letters: QWERTYUIOPASDFGHJKLZXCVBNM', ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/Привет';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_LETTERS_}', static function ($string) {
            echo 'letters: ' . $string;
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
        $router = new \Inilim\Router\Router(Request::createFromGlobals());

        ob_start();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/string';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_}', static function ($string) {
            echo '$string';
        });
        $router->run();
        $this->assertEmpty(ob_get_contents());
        ob_clean();

        // ---------------------------------------------
        // Assert
        // ---------------------------------------------

        $_SERVER['REQUEST_URI'] = '/show/1726378213';
        $this->overrideRequest($router);
        $router->route('GET', '/show/{_NUMBERS_}', static function ($string) {
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
        $router->route('GET', '/show/{_NUMBERS_}', static function ($string) {
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
        $router->route('GET', '/show/{_NUMBERS_}', static function ($string) {
            echo 'numbers: ' . $string;
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
}
