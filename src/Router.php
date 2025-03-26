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
    protected const METHODS = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var list<array{p:string,h:string|\Closure}>
     */
    protected $routes = [];
    /**
     * @var list<array{p:string,h:string|\Closure}>
     */
    protected $middleware = [];
    /**
     * @var array<string,string[]>
     */
    protected $cache = [];
    /**
     * @var \Closure|null
     */
    protected $notFoundCallback = null;
    /**
     * @var int|null
     */
    protected $numHundledMiddleware = null;
    /**
     * @var \Closure|null
     */
    protected $handleParamsMiddleware = null;
    /**
     * @var \Closure|null
     */
    protected $handleParamsController = null;
    /**
     * @var class-string|null
     */
    protected $classHandle = null;

    // ---------------------------------------------
    // 
    // ---------------------------------------------

    function __construct(Request $request)
    {
        $this->request = $request;
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
        if ($r === null) {
            return $this;
        }

        $this->routes[] = $r;

        if ($middlewares) {
            foreach ($middlewares as &$m) {
                $r['h'] = $m;
                $this->middleware[] = $r;
            }
        }
        return $this;
    }

    /**
     * @param RouteAbstract $route
     * @return self
     */
    // function addRoute(RouteAbstract $route)
    // {
    //     $method  = $route->getMethod();
    //     $pattern = $route->getPattern();
    //     $this->route(
    //         $method,
    //         $pattern,
    //         $route->getHandle(),
    //     );
    //     $m = $route->getMiddleware();
    //     if ($m === null) return $this;
    //     $this->middleware($method, $pattern, $m);
    //     return $this;
    // }

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
    function getNumHundledMiddleware()
    {
        return $this->numHundledMiddleware ?? 0;
    }

    /**
     * @return string|null
     */
    function getClassHandle()
    {
        return $this->classHandle;
    }

    /**
     * @return self
     */
    function set404(\Closure $handle)
    {
        $this->notFoundCallback = $handle;
        return $this;
    }

    /**
     * @template T of array<string|null>
     * @param \Closure(T $params)): T $handle
     * @return self
     */
    function setHandleParamsMiddleware(\Closure $handle)
    {
        $this->handleParamsMiddleware = $handle;
        return $this;
    }

    /**
     * @template T of array<string|null>
     * @param \Closure(T $params)): T $handle
     * @return self
     */
    function setHandleParamsController(\Closure $handle)
    {
        $this->handleParamsController = $handle;
        return $this;
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
     * @param string $methods
     * @param string $pattern
     * @param string|\Closure $handle
     * @return array{p:string,h:string|\Closure}|null
     */
    protected function save($methods, $pattern, $handle)
    {
        $m = $this->request->getMethod();
        if ($m === '' || !Str::_contains($this->prepareMethod($methods), $m)) {
            return null;
        }

        return [
            'p' => $this->preparePattern($pattern),
            'h' => $handle,
        ];
    }

    /**
     * @param string $pattern
     * @return string
     */
    protected function preparePattern($pattern)
    {
        return '/' . \trim($pattern, '/');
    }

    /**
     * @param string $pattern
     * @param string $uri
     * @param array<mixed> $matches
     * @return bool -> is match yes/no
     */
    protected function patternMatches($pattern, $uri, ?array &$matches, int $flags)
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
     * @param list<array{p:string,h:string|\Closure}> $routesOrMiddlewares
     * @return int
     */
    protected function handle(array &$routesOrMiddlewares, bool $isController = false)
    {
        $numHandled = 0;

        $path = $this->request->getPath();

        foreach ($routesOrMiddlewares as $idx => &$rOrM) {

            $hash = \md5($rOrM['p']);

            if (isset($this->cache[$hash])) {
                $isMatch = true;
                $params  = $this->cache[$hash];
            } else {
                $isMatch = $this->patternMatches($rOrM['p'], $path, $matches, \PREG_OFFSET_CAPTURE);
            }

            if (!$isMatch) {
                unset($routesOrMiddlewares[$idx]);
                continue;
            }

            if (!isset($params)) {
                $matches = \array_slice($matches, 1);

                // ------------------------------------------------------------------
                // EPIC Bramus
                // ------------------------------------------------------------------

                $params = [];
                foreach ($matches as $idx => &$match) {
                    $idx++;
                    if (isset($matches[$idx]) && isset($matches[$idx][0]) && \is_array($matches[$idx][0])) {
                        if ($matches[$idx][0][1] > -1) {
                            $params[] = \trim(\substr($match[0][0], 0, $matches[$idx][0][1] - $match[0][1]), '/');
                            continue;
                        }
                    }

                    $params[] = isset($match[0][0]) && $match[0][1] != -1 ? \trim($match[0][0], '/') : null;
                }
                $this->cache[$hash] = $params;
                $matches            = [];

                // ---------------------------------------------
                // 
                // ---------------------------------------------

                // $this->cache[$hash] = $params = \array_map(static function ($match, $index) use ($matches) {
                //     if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && \is_array($matches[$index + 1][0])) {
                //         if ($matches[$index + 1][0][1] > -1) {
                //             return \trim(\substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                //         }
                //     }

                //     return isset($match[0][0]) && $match[0][1] != -1 ? \trim($match[0][0], '/') : null;
                // }, $matches, \array_keys($matches));
                // $matches = [];

                // ------------------------------------------------------------------
                // EPIC
                // ------------------------------------------------------------------
            } // endif

            /** @var array<string|int, string|null> $params */

            if ($isController) {
                $this->cache = [];
            }

            $this->exec($rOrM['h'], $params);

            ++$numHandled;

            // вылетаем сразу после одного контроллера
            if ($isController) {
                break;
            }

            unset($routesOrMiddlewares[$idx]);
        } // endforeach

        if (!$isController) {
            // записываем сколько было middleware
            $this->numHundledMiddleware = $numHandled;
        }

        return $numHandled;
    }

    /**
     * @param array<string|null> $params
     * @param string|\Closure $handle
     * @return void
     */
    protected function exec($handle, array $params = [])
    {
        if ($this->numHundledMiddleware === null) {
            $handleParams = $this->handleParamsMiddleware;
        } else {
            $handleParams = $this->handleParamsController;
        }

        if ($handleParams) {
            $params = $handleParams($params);
        }

        // ---------------------------------------------
        // 
        // ---------------------------------------------

        if (!\is_string($handle)) {
            $this->classHandle = \Closure::class;
            $handle(...$params);
            return;
        } elseif (Str::_contains($handle, '@')) {
            [$handle, $method] = \explode('@', $handle);
            $this->classHandle = $handle;
        } else {
            $this->classHandle = $handle;
            $method            = '';
        }

        // ---------------------------------------------
        // 
        // ---------------------------------------------

        if (!\class_exists($handle)) {
            return;
        }

        if ($method === '') {
            new $handle(...$params);
        } else {
            if (\method_exists($handle, $method)) {
                (new $handle)->{$method}(...$params);
            }
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
