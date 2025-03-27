<?php

namespace Inilim\Router\Test;

use Inilim\Dump\Dump;
use Inilim\Router\Router;
use Inilim\Request\Request;
use Inilim\Router\Test\TestCase;
use Inilim\Router\Test\ForTest\RouterTestController;

Dump::init();

class RouterBramusTest extends TestCase
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

    function testInit()
    {
        $this->assertInstanceOf(Router::class, new Router(Request::createFromGlobals()));
    }

    function testUri()
    {
        // Fake some data
        $_SERVER['REQUEST_URI'] = '/about/whatever';

        $method = new \ReflectionMethod(
            Router::class,
            'getCurrentUri'
        );

        $method->setAccessible(true);

        $this->assertEquals(
            '/about/whatever',
            $method->invoke(new Router(Request::createFromGlobals()))
        );
    }

    function testStaticRoute()
    {
        $_SERVER['REQUEST_URI'] = '/about';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/about', static function () {
            echo 'about';
        });

        // Test the /about route
        ob_start();
        $router->run();
        $this->assertEquals('about', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testRequestMethods()
    {
        $router = new Router(Request::createFromGlobals());

        // Test GET
        ob_start();
        $_SERVER['REQUEST_URI'] = '/';
        $this->overrideRequest($router);
        $router->route('GET', '/', static function () {
            echo 'get';
        });
        $router->run();
        $this->assertEquals('get', ob_get_contents());

        // Test POST
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->overrideRequest($router);
        $router->route('POST', '/', static function () {
            echo 'post';
        });
        $router->run();
        $this->assertEquals('post', ob_get_contents());

        // Test PUT
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->overrideRequest($router);
        $router->route('PUT', '/', static function () {
            echo 'put';
        });
        $router->run();
        $this->assertEquals('put', ob_get_contents());

        // Test PATCH
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->overrideRequest($router);
        $router->route('PATCH', '/', static function () {
            echo 'patch';
        });
        $router->run();
        $this->assertEquals('patch', ob_get_contents());

        // Test DELETE
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->overrideRequest($router);
        $router->route('DELETE', '/', static function () {
            echo 'delete';
        });
        $router->run();
        $this->assertEquals('delete', ob_get_contents());

        // Test OPTIONS
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $this->overrideRequest($router);
        $router->route('OPTIONS', '/', static function () {
            echo 'options';
        });
        $router->run();
        $this->assertEquals('options', ob_get_contents());

        // Test HEAD
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->overrideRequest($router);
        $router->route('GET', '/', static function () {
            echo 'get';
        });
        $router->run();
        $this->assertEquals('', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testShorthandAny()
    {
        $_SERVER['REQUEST_URI'] = '/';
        $router = new Router(Request::createFromGlobals());
        $handle = static function () {
            echo 'all';
        };

        // Test GET
        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('all', ob_get_contents());

        // Test POST
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('all', ob_get_contents());

        // Test PUT
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('all', ob_get_contents());

        // Test DELETE
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('all', ob_get_contents());

        // Test OPTIONS
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('all', ob_get_contents());

        // Test PATCH
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('all', ob_get_contents());

        // Test HEAD
        ob_clean();
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->overrideRequest($router);
        $router->any('/', $handle);
        $router->run();
        $this->assertEquals('', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRoute()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/(\w+)', function ($name) {
            echo 'Hello ' . $name;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Hello bramus', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithMultiple()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus/sumarb';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/(\w+)/(\w+)', static function ($name, $lastname) {
            echo 'Hello ' . $name . ' ' . $lastname;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Hello bramus sumarb', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesRoutes()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus/sumarb';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/{name}/{lastname}', static function ($name, $lastname) {
            echo 'Hello ' . $name . ' ' . $lastname;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Hello bramus sumarb', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesRoutesWithNonAZCharsInPlaceholderNames()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus/sumarb';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/{arg1}/{arg2}', static function ($arg1, $arg2) {
            echo 'Hello ' . $arg1 . ' ' . $arg2;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Hello bramus sumarb', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesRoutesWithCyrillicCharactersInPlaceholderNames()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus/sumarb';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/{това}/{това}', static function ($arg1, $arg2) {
            echo 'Hello ' . $arg1 . ' ' . $arg2;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Hello bramus sumarb', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesRoutesWithEmojiInPlaceholderNames()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus/sumarb';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/{😂}/{😅}', static function ($arg1, $arg2) {
            echo 'Hello ' . $arg1 . ' ' . $arg2;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Hello bramus sumarb', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesWithCyrillicCharacters()
    {
        $_SERVER['REQUEST_URI'] = '/bg/това';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/bg/{arg}', function ($arg) {
            echo 'BG: ' . $arg;
        });

        ob_start();
        $router->run();
        $this->assertEquals('BG: това', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesWithMultipleCyrillicCharacters()
    {
        $_SERVER['REQUEST_URI'] = '/bg/това/слъг';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/bg/{arg}/{arg}', function ($arg1, $arg2) {
            echo 'BG: ' . $arg1 . ' - ' . $arg2;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('BG: това - слъг', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testCurlyBracesWithEmoji()
    {
        $_SERVER['REQUEST_URI'] = '/emoji/%F0%9F%92%A9'; // 💩

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/emoji/{emoji}', function ($emoji) {
            echo 'Emoji: ' . $emoji;
        });

        // Test the /hello/bramus route
        ob_start();
        $router->run();
        $this->assertEquals('Emoji: 💩', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithOptionalSubpatterns()
    {
        $router = new Router(Request::createFromGlobals());
        $handle = static function ($name = null) {
            echo 'Hello ' . (($name) ? $name : 'stranger');
        };

        // Test the /hello route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/hello';
        $this->overrideRequest($router);
        $router->route('GET', '/hello(/\w+)?', $handle);
        $router->run();
        $this->assertEquals('Hello stranger', ob_get_contents());

        // Test the /hello/bramus route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/hello/bramus';
        $this->overrideRequest($router);
        $router->route('GET', '/hello(/\w+)?', $handle);
        $router->run();
        $this->assertEquals('Hello bramus', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithMultipleSubpatterns()
    {
        $_SERVER['REQUEST_URI'] = '/hello/bramus/page3';

        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/(.*)/page([0-9]+)', static  function ($place, $page) {
            echo 'Hello ' . $place . ' page : ' . $page;
        });

        // Test the /hello/bramus/page3 route
        ob_start();
        $router->run();
        $this->assertEquals('Hello hello/bramus page : 3', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithOptionalNestedSubpatterns()
    {
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $handle = static function ($year = null, $month = null, $day = null, $slug = null) {
            if ($year === null) {
                echo 'Blog overview';

                return;
            }
            if ($month === null) {
                echo 'Blog year overview (' . $year . ')';

                return;
            }
            if ($day === null) {
                echo 'Blog month overview (' . $year . '-' . $month . ')';

                return;
            }
            if ($slug === null) {
                echo 'Blog day overview (' . $year . '-' . $month . '-' . $day . ')';

                return;
            }
            echo 'Blogpost ' . htmlentities($slug) . ' detail (' . $year . '-' . $month . '-' . $day . ')';
        };

        // Test the /blog route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/blog';
        $this->overrideRequest($router);
        $router->route('GET', '/blog(/\d{4}(/\d{2}(/\d{2}(/[a-z0-9_-]+)?)?)?)?', $handle);
        $router->run();
        $this->assertEquals('Blog overview', ob_get_contents());

        // Test the /blog/year route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/blog/1983';
        $this->overrideRequest($router);
        $router->route('GET', '/blog(/\d{4}(/\d{2}(/\d{2}(/[a-z0-9_-]+)?)?)?)?', $handle);
        $router->run();
        $this->assertEquals('Blog year overview (1983)', ob_get_contents());

        // Test the /blog/year/month route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/blog/1983/12';
        $this->overrideRequest($router);
        $router->route('GET', '/blog(/\d{4}(/\d{2}(/\d{2}(/[a-z0-9_-]+)?)?)?)?', $handle);
        $router->run();
        $this->assertEquals('Blog month overview (1983-12)', ob_get_contents());

        // Test the /blog/year/month/day route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/blog/1983/12/26';
        $this->overrideRequest($router);
        $router->route('GET', '/blog(/\d{4}(/\d{2}(/\d{2}(/[a-z0-9_-]+)?)?)?)?', $handle);
        $router->run();
        $this->assertEquals('Blog day overview (1983-12-26)', ob_get_contents());

        // Test the /blog/year/month/day/slug route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/blog/1983/12/26/bramus';
        $this->overrideRequest($router);
        $router->route('GET', '/blog(/\d{4}(/\d{2}(/\d{2}(/[a-z0-9_-]+)?)?)?)?', $handle);
        $router->run();
        $this->assertEquals('Blogpost bramus detail (1983-12-26)', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithNestedOptionalSubpatterns()
    {
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $handle = static function ($name1 = null, $name2 = null) {
            echo 'Hello ' . (($name1) ? $name1 : 'stranger') . ' ' . (($name2) ? $name2 : 'stranger');
        };

        // Test the /hello/bramus route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/hello/bramus';
        $this->overrideRequest($router);
        $router->route('GET', '/hello(/\w+(/\w+)?)?', $handle);
        $router->run();
        $this->assertEquals('Hello bramus stranger', ob_get_contents());

        // Test the /hello/bramus/bramus route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/hello/bramus/bramus';
        $this->overrideRequest($router);
        $router->route('GET', '/hello(/\w+(/\w+)?)?', $handle);
        $router->run();
        $this->assertEquals('Hello bramus bramus', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithWildcard()
    {
        // Test the /hello/bramus route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/hello/bramus';
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '(.*)', static function ($name) {
            echo 'Hello ' . $name;
        });
        $router->run();
        $this->assertEquals('Hello hello/bramus', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testDynamicRouteWithPartialWildcard()
    {
        // Test the /hello/bramus route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/hello/bramus/sumarb';
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/hello/(.*)', static function ($name) {
            echo 'Hello ' . $name;
        });
        $router->run();
        $this->assertEquals('Hello bramus/sumarb', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function test404()
    {
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $handle = static function () {
            echo 'home';
        };
        $_404handle = static function () {
            echo 'route not found';
        };

        // Test the /hello route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/';
        $this->overrideRequest($router);
        $router->route('GET', '/', $handle);
        $router->set404($_404handle);
        $router->run();
        $this->assertEquals('home', ob_get_contents());

        // Test the /hello/bramus route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/foo';
        $this->overrideRequest($router);
        $router->route('GET', '/', $handle);
        $router->set404($_404handle);
        $router->run();
        $this->assertEquals('route not found', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function test404WithManualTrigger()
    {
        // Test the / route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/';
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/', static function () use ($router) {
            $router->trigger404();
        });
        $router->set404(static function () {
            echo 'route not found';
        });
        $router->run();
        $this->assertEquals('route not found', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testBeforeRouterMiddleware()
    {
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->middleware('GET|POST', '/.*', static function () {
            echo 'before ';
        });
        $router->route('GET', '/about', static function () {
            echo 'about';
        });

        ob_start();

        // Test the /about route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/about';
        $this->overrideRequest($router);
        $router->run();
        $this->assertEquals('before about', ob_get_contents());

        // Test the /post route
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/post';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $router->middleware('GET|POST', '/.*', static function () {
            echo 'before ';
        });
        $router->route('GET', '/post', static function () {
            echo 'post';
        });

        $this->overrideRequest($router);
        $router->run();
        $this->assertEquals('before post', ob_get_contents());

        // clear routes after run
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/about';
        $this->overrideRequest($router);
        $router->run();
        $this->assertEquals('', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testAfterRouterMiddleware()
    {
        // Test the / route
        ob_start();
        $_SERVER['REQUEST_URI'] = '/';
        // Create Router
        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/', static function () {
            echo 'home';
        });
        $router->run(static function () {
            echo 'finished';
        });
        $this->assertEquals('homefinished', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }

    function testBasicController()
    {
        $_SERVER['REQUEST_URI'] = '/show/foo';

        $router = new Router(Request::createFromGlobals());
        $router->route('GET', '/show/(.*)', RouterTestController::class . '@show');

        ob_start();
        $router->run();

        $this->assertEquals('foo', ob_get_contents());

        // cleanup
        ob_end_clean();
    }

    function testHttpMethodOverride()
    {
        // Fake the request method to being POST and override it
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $method = new \ReflectionMethod(
            Router::class,
            'getRequestMethod'
        );

        $method->setAccessible(true);

        $this->assertEquals(
            'PUT',
            $method->invoke(new Router(Request::createFromGlobals()))
        );
    }

    function testControllerMethodReturningFalse()
    {
        // Create Router
        $router = new Router(Request::createFromGlobals());

        // Test returnFalse
        ob_start();
        $_SERVER['REQUEST_URI'] = '/false';
        $this->overrideRequest($router);
        $router->route('GET', '/false', RouterTestController::class . '@returnFalse');
        $router->run();
        $this->assertEquals('returnFalse', ob_get_contents());

        // Test staticReturnFalse
        ob_clean();
        $_SERVER['REQUEST_URI'] = '/static-false';
        $this->overrideRequest($router);
        $router->route('GET', '/static-false', RouterTestController::class . '@staticReturnFalse');
        $router->run();
        $this->assertEquals('staticReturnFalse', ob_get_contents());

        // Cleanup
        ob_end_clean();
    }
}
