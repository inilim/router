<?php

namespace Inilim\Router;

use Inilim\Tool\Str;
use Inilim\Request\Request;
use Inilim\Router\RouteAbstract;

/**
 * @author Bram(us) Van Damme <bramus@bram.us>
 * @author inilim
 */
final class Router
{
    /**
     * @var Request
     */
    protected $request;

    protected const METHODS = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';

    /**
     * @var list<array{p:string,h:string|\Closure}>
     */
    protected array $routes                = [];
    /**
     * @var list<array{p:string,h:string|\Closure}>
     */
    protected array $middleware            = [];
    protected ?\Closure $notFoundCallback = null;
    protected int $countExecMiddleware   = 0;
    protected ?string $classHandle        = null;

    function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param RouteAbstract $route
     * @return self
     */
    function addRoute(RouteAbstract $route)
    {
        $method  = $route->getMethod();
        $pattern = $route->getPattern();
        $this->route(
            $method,
            $pattern,
            $route->getHandle(),
        );
        $m = $route->getMiddleware();
        if ($m === null) return $this;
        $this->middleware($method, $pattern, $m);
        return $this;
    }

    /**
     * @return void
     */
    function run(?\Closure $callback = null)
    {
        if ($this->middleware) $this->handle($this->middleware);
        $this->middleware = [];

        $numHandled = 0;
        if ($this->routes) {
            $numHandled = $this->handle($this->routes, true);
        }
        $this->routes = [];

        if ($numHandled === 0) {
            $this->trigger404();
        } else {
            if ($callback) $callback();
        }
    }

    /**
     * @param string|\Closure $handle
     * @return self
     */
    function middleware(string $methods, string $pattern, $handle)
    {
        $r = $this->save($methods, $pattern, $handle);
        if ($r === null) return $this;

        $this->middleware[] = $r;
        return $this;
    }

    /**
     * @param string|\Closure $handle
     * @param string|\Closure ...$middlewares
     * @return self
     */
    function route(string $methods, string $pattern, $handle, ...$middlewares)
    {
        $r = $this->save($methods, $pattern, $handle);
        if ($r === null) return $this;

        $this->routes[] = $r;

        if ($middlewares) {
            foreach ($middlewares as $m) {
                $this->middleware($methods, $pattern, $m);
            }
        }
        return $this;
    }

    /**
     * @param string|\Closure $handle
     * @param string|\Closure ...$middlewares
     * @return self
     */
    function any(string $pattern, $handle, ...$middlewares)
    {
        return $this->route(self::METHODS, $pattern, $handle, ...$middlewares);
    }

    /**
     * @return int
     */
    function getCountExecMiddleware()
    {
        return $this->countExecMiddleware;
    }

    /**
     * @return string|null
     */
    function getClassHandle()
    {
        return $this->classHandle;
    }

    /**
     * @return void
     */
    function set404(\Closure $handle)
    {
        $this->notFoundCallback = $handle;
    }

    /**
     * @return void
     */
    function trigger404()
    {
        if ($this->notFoundCallback) ($this->notFoundCallback)();
    }

    // ------------------------------------------------------------------
    // protected
    // ------------------------------------------------------------------

    /**
     * @param string|\Closure $handle
     * @return array{p:string,h:string|\Closure}|null
     */
    protected function save(string $methods, string $pattern, $handle)
    {
        if (!Str::_contains($this->prepareMethod($methods), $this->request->getMethod())) {
            return null;
        }

        return [
            'p' => $this->preparePattern($pattern),
            'h' => $handle,
        ];
    }

    /**
     * @return string
     */
    protected function preparePattern(string $pattern)
    {
        return '/' . \trim($pattern, '/');
    }

    /**
     * @param array<mixed> $matches
     * @return bool -> is match yes/no
     */
    protected function patternMatches(string $pattern, string $uri, ?array &$matches, int $flags)
    {
        $pattern = \str_replace(
            ['{int_unsigned}',     '{int}',                 '{letters}'],
            ['(0|[1-9][0-9]{0,})', '(0|\-?[1-9][0-9]{0,})', '([a-zA-Z]+)'],
            $pattern
        );
        $pattern = \preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

        return (bool) \preg_match_all('#^' . $pattern . '$#', $uri, $matches, $flags);
    }

    /**
     * @param list<array{p:string,h:string|\Closure}> $routes
     * @return int
     */
    protected function handle(array &$routes, bool $afterMiddleware = false)
    {
        $numHandled = 0;

        $path = $this->request->getPath();

        foreach ($routes as $idx => &$route) {

            $is_match = $this->patternMatches($route['p'], $path, $matches, \PREG_OFFSET_CAPTURE);

            if ($is_match) {
                $matches = \array_slice($matches, 1);

                // ------------------------------------------------------------------
                // EPIC Bramus
                // ------------------------------------------------------------------
                $params = \array_map(static function ($match, $index) use ($matches) {
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && \is_array($matches[$index + 1][0])) {
                        if ($matches[$index + 1][0][1] > -1) {
                            return \trim(\substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        }
                    }

                    return isset($match[0][0]) && $match[0][1] != -1 ? \trim($match[0][0], '/') : null;
                }, $matches, \array_keys($matches));
                // ------------------------------------------------------------------
                // EPIC
                // ------------------------------------------------------------------

                $this->exec($route['h'], $params);

                ++$numHandled;

                // вылетаем сразу после одного контроллера
                if ($afterMiddleware) break;
            }

            unset($routes[$idx]);
        }

        // записываем сколько было middleware
        if (!$afterMiddleware) $this->countExecMiddleware = $numHandled;

        return $numHandled;
    }

    /**
     * @param array<string|null> $params
     * @return void
     */
    protected function execMethodClass(string $class, string $method, array $params)
    {
        if (!\class_exists($class)) return;
        if ($method === '') {
            $method = '__construct';
            if (\method_exists($class, $method)) {
                new $class(...$params);
            }
        } else {
            if (\method_exists($class, $method)) {
                (new $class)->{$method}(...$params);
            }
        }
    }

    /**
     * @param array<string|null> $params
     * @param string|\Closure $handle
     * @return void
     */
    protected function exec($handle, array $params = [])
    {
        if (!\is_string($handle)) {
            $handle(...$params);
        } elseif (Str::_contains($handle, '@')) {
            // вызвать метод класса
            [$handle, $method] = \explode('@', $handle);
            $this->classHandle = $handle;
            $this->execMethodClass($handle, $method, $params);
        } else {
            $this->classHandle = $handle;
            $this->execMethodClass($handle, '', $params);
        }
    }

    /**
     * @return string
     */
    protected function prepareMethod(string $method)
    {
        $m = \strtoupper($method);
        if (Str::_contains($m, 'ALL')) return self::METHODS;
        return $m;
    }
}
