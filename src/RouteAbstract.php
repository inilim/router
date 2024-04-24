<?php

namespace Inilim\Router;

use Closure;

/**
 */
abstract class RouteAbstract
{
    /**
     * @var string|Closure|null
     */
    protected string|Closure|null $middleware = null;

    // ------------------------------------------------------------------
    // abstract
    // ------------------------------------------------------------------

    abstract public function getPattern(): string;
    abstract public function getRequestMethod(): string;
    abstract public function getHandle(): string|Closure;

    // ------------------------------------------------------------------
    // public
    // ------------------------------------------------------------------

    public static function make(): static
    {
        return new static;
    }

    public function getMiddleware(): string|Closure|null
    {
        return $this->middleware;
    }

    public function route(string|int|float ...$params): string
    {
        $p = $this->getPattern();
        if (!$params) return $p;
        $match = [];
        $count = (int)\preg_match_all('#\{[^\{\}]+\}#', $p, $match);
        if (!$count) return $p;
        // print_r($match);
        // exit();
        $match = $match[0];
        /** @var string[] $match */
        $params = \array_slice($params, 0, $count);
        $params = \array_map(\strval(...), $params);
        return \str_replace($match, $params, $p);
    }
}
