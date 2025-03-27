<?php

declare(strict_types=1);

namespace Inilim\Router;

use Inilim\Tool\Str;
use Inilim\Request\Request;

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
     * @var array<array{p:string,h:string|\Closure}>
     */
    protected $routes = [];
    /**
     * @var array<array{p:string,h:string|\Closure}>
     */
    protected $middleware = [];
    /**
     * @var array<string,array<string|null>>
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
     * @return string
     */
    function getRequestMethod()
    {
        return $this->request->getMethod();
    }

    /**
     * @return string
     */
    function getCurrentUri()
    {
        return $this->request->getPath();
    }

    /**
     * @return void
     */
    function run(?\Closure $callback = null)
    {
        // If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
        // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
        $isOverrideHead = $this->isOverrideHead();
        if ($isOverrideHead) {
            \ob_start();
        }

        if ($this->middleware) {
            $this->numHundledMiddleware = $this->handle($this->middleware);
            $this->middleware           = [];
        } else {
            $this->numHundledMiddleware = 0;
        }

        $numHandled = 0;
        if ($this->routes) {
            $numHandled = $this->handle($this->routes, true);
            $this->routes = [];
        }

        if ($numHandled === 0) {
            $this->trigger404();
        } else {
            if ($callback) $callback();
        }

        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($isOverrideHead) {
            \ob_end_clean();
        }
    }

    /**
     * @param string|\Closure $handle
     * @return self
     */
    function middleware(string $methods, string $pattern, $handle)
    {
        $r = $this->prepareRoute($methods, $pattern, $handle);
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
        $r = $this->prepareRoute($methods, $pattern, $handle);
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
     * @return class-string|null
     */
    function getClassHandle()
    {
        return $this->classHandle;
    }

    /**
     * @param \Closure():void $handle
     * @return self
     */
    function set404(\Closure $handle)
    {
        $this->notFoundCallback = $handle;
        return $this;
    }

    /**
     * @template T of array<string|null>
     * @param \Closure(T $params, Request $request): T $handle
     * @return self
     */
    function setHandleParamsMiddleware(\Closure $handle)
    {
        $this->handleParamsMiddleware = $handle;
        return $this;
    }

    /**
     * @template T of array<string|null>
     * @param \Closure(T $params, Request $request): T $handle
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
        if ($this->notFoundCallback) {
            $this->notFoundCallback->__invoke();
        }
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
    protected function prepareRoute($methods, $pattern, $handle)
    {
        $m = $this->getRequestMethodWithOverride();
        if (
            $m === '' ||
            // @phpstan-ignore-next-line
            !Str::_contains($this->prepareMethod($methods), $m)
        ) {
            return null;
        }

        return [
            'p' => '/' . \trim($pattern, '/'),
            'h' => $handle,
        ];
    }

    /**
     * @param string $pattern
     * @param string $uri
     * @param mixed[] $matches
     * @param int $flags
     * @return bool -> is match yes/no
     */
    protected function patternMatches($pattern, $uri, &$matches, $flags)
    {
        $pattern = \str_replace(
            ['{_NUMBERS_UNSIGNED_}', '{_NUMBERS_}', '{_LETTERS_}', '{_INT_}',               '{_INT_UNSIGNED_}'],
            ['([0-9]+)',             '(\-?[0-9]+)', '(\p{L}+)',    '(0|\-?[1-9][0-9]{0,})', '(0|[1-9][0-9]{0,})'],
            $pattern
        );
        $pattern = \preg_replace('#\/{(.*?)}#', '/(.*?)', $pattern);

        return (bool) \preg_match_all('#^' . $pattern . '$#u', $uri, $matches, $flags);
    }

    /**
     * @param array<array{p:string,h:string|\Closure}> $controllerOrMiddlewares
     * @return int
     */
    protected function handle(array &$controllerOrMiddlewares, bool $isController = false)
    {
        $numHandled = 0;

        $path = $this->request->getPath();

        foreach ($controllerOrMiddlewares as $idx => &$rOrM) {

            $hash = \md5($rOrM['p']);

            if (isset($this->cache[$hash])) {
                $isMatch = true;
                $params  = $this->cache[$hash];
            } else {
                $isMatch = $this->patternMatches($rOrM['p'], $path, $matches, \PREG_OFFSET_CAPTURE);
            }

            if (!$isMatch) {
                unset($controllerOrMiddlewares[$idx]);
                continue;
            }

            if (!isset($params) && isset($matches)) {
                $matches = \array_slice($matches, 1);

                $params = [];
                foreach ($matches as $idx2 => &$match) {
                    $idx2++;
                    // @phpstan-ignore-next-line
                    if (isset($matches[$idx2]) && isset($matches[$idx2][0]) && \is_array($matches[$idx2][0])) {
                        if ($matches[$idx2][0][1] > -1) {
                            // @phpstan-ignore-next-line
                            $params[] = \trim(\substr($match[0][0], 0, $matches[$idx2][0][1] - $match[0][1]), '/');
                            continue;
                        }
                    }
                    // @phpstan-ignore-next-line
                    $params[] = isset($match[0][0]) && $match[0][1] != -1 ? \trim($match[0][0], '/') : null;
                }

                $this->cache[$hash] = $params;
                $matches            = [];
            } // endif

            if ($isController) {
                $this->cache = [];
            }
            // @phpstan-ignore-next-line
            $this->exec($rOrM['h'], $params);
            unset($controllerOrMiddlewares[$idx]);

            $numHandled++;

            // вылетаем сразу после одного контроллера
            if ($isController) {
                break;
            }
        } // endforeach

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
            $params = $handleParams($params, $this->request);
        }

        // ---------------------------------------------
        // 
        // ---------------------------------------------

        if (!\is_string($handle)) {
            $this->classHandle = \Closure::class;
            $handle(...$params);
            return;
            // @phpstan-ignore-next-line
        } elseif (Str::_contains($handle, '@')) {
            /**
             * @var class-string $handle
             */
            [$handle, $method] = \explode('@', $handle);
            $this->classHandle = $handle;
        } else {
            /**
             * @var class-string $handle
             */
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
     * @return bool
     */
    protected function isOverrideHead()
    {
        return $this->request->getMethod() === 'HEAD';
    }

    /**
     * @return string
     */
    protected function prepareMethod(string $method)
    {
        $m = \strtoupper($method);
        // @phpstan-ignore-next-line
        if (Str::_contains($m, 'ALL')) {
            return self::METHODS;
        }
        return $m;
    }

    /**
     * @return string
     */
    protected function getRequestMethodWithOverride()
    {
        $m = $this->request->getMethod();
        if ($m === 'HEAD') {
            return 'GET';
        }
        return $m;
    }
}
