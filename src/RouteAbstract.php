<?php

namespace Inilim\Router;

use Closure;

/**
 * публичные константы дешевле чем защищенные свойства
 */
abstract class RouteAbstract
{
    /**
     * @var class-string|Closure
     */
    protected string|Closure|null $handle = null;
    /**
     * @var class-string|Closure
     */
    protected string|Closure|null $middleware = null;
    public const METHOD  = null;
    public const PATTERN = null;

    public static function make(): static
    {
        return new static;
    }

    public function setHandle(string|Closure $handle): void
    {
        $this->handle = $handle;
    }

    public function setMiddleware(string|Closure $middleware): void
    {
        $this->middleware = $middleware;
    }

    public function getMiddleware(): string|Closure|null
    {
        return $this->middleware;
    }

    public function getHandle(): string|Closure|null
    {
        return $this->handle;
    }

    public function route(string|int|float ...$params): string
    {
        $p = self::PATTERN ?? '';
        if (!$params) return $p;
        $match = [];
        $count = (int)\preg_match_all('#\{[^\{\}]+\}#', $p, $match);
        if (!$count) return $p;
        $match = $match[0];
        /** @var string[] $match */
        $params = \array_slice($params, 0, $count);
        $params = \array_map('strval', $params);
        return \str_replace($match, $params, $p);
    }
}
