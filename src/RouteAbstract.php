<?php

namespace Inilim\Router;

use Closure;

abstract class RouteAbstract
{
    /**
     * @var class-string|Closure
     */
    protected string|Closure $controller;
    protected string $path;

    public function getController(): string|Closure
    {
        return $this->controller;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function path(string|int|float ...$params): string
    {
        $p = $this->getPath();
        if (!$params) return $p;
        $match = [];
        $count = (int)\preg_match_all('#\{[^\{\}]+\}#', $p, $match);
        if (!$count) return $p;
        // print_r($match);
        // exit();
        $match = $match[0];
        /** @var string[] $match */
        $params = \array_slice($params, 0, $count);
        $params = \array_map('strval', $params);
        return \str_replace($match, $params, $p);
    }
}
