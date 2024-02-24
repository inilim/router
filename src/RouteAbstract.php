<?php

namespace Inilim\Router;

use Closure;

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
    protected ?string $method = null;
    protected ?string $path = null;

    public static function make(): static
    {
        return new static;
    }

    public function getMiddleware(): string|Closure|null
    {
        return $this->middleware;
    }

    public function getHandle(): string|Closure|null
    {
        return $this->handle;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function path(string|int|float ...$params): string
    {
        $p = $this->getPath() ?? '';
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
