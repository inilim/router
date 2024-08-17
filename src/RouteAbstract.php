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

    abstract function getPattern(): string;
    abstract function getMethod(): string;
    abstract function getHandle(): string|Closure;

    // ------------------------------------------------------------------
    // public
    // ------------------------------------------------------------------

    static function make(): static
    {
        return new static;
    }

    function getMiddleware(): string|Closure|null
    {
        return $this->middleware;
    }

    function route(string|int|float ...$params): string
    {
        $p = $this->getPattern();
        if (!$params) return $p;
        $match = [];
        $count = (int)\preg_match_all('#\{[^\{\}]+\}#', $p, $match);
        if (!$count) return $p;
        $match = $match[0];
        /** @var string[] $match */

        foreach (\array_slice($params, 0, $count) as $idx => $param) {
            $p = \_str()->replaceFirst($match[$idx], \strval($param), $p);
            unset($match[$idx]);
        }
        if ($match) throw new \Exception('"args"');
        return $p;
    }
}
