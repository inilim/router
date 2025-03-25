<?php

namespace Inilim\Router;

use Inilim\Tool\Str;

abstract class RouteAbstract
{
    /**
     * @var string|\Closure|null
     */
    protected $middleware = null;

    // ------------------------------------------------------------------
    // abstract
    // ------------------------------------------------------------------

    abstract function getPattern(): string;
    abstract function getMethod(): string;
    /**
     * @return string|\Closure
     */
    abstract function getHandle();

    // ------------------------------------------------------------------
    // public
    // ------------------------------------------------------------------

    /**
     * @return static
     */
    static function make()
    {
        return new static;
    }

    /**
     * @return string|\Closure|null
     */
    function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * @param string|int|float ...$params
     * @return string
     */
    function route(...$params): string
    {
        $p = $this->getPattern();
        if (!$params) return $p;
        $match = [];
        $count = (int)\preg_match_all('#\{[^\{\}]+\}#', $p, $match);
        if (!$count) return $p;
        $match = $match[0];
        /** @var string[] $match */

        foreach (\array_slice($params, 0, $count) as $idx => $param) {
            $p = Str::replaceFirst($match[$idx], \strval($param), $p);
            unset($match[$idx]);
        }
        if ($match) throw new \Exception('"args"');
        return $p;
    }
}
