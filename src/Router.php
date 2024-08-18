<?php

namespace Inilim\Router;

use Inilim\Router\RouteAbstract;
use Inilim\Request\Request;
use \Closure;

/**
 * @author      Bram(us) Van Damme <bramus@bram.us>
 * @author      inilim
 */
class Router
{
   public readonly Request $request;

   protected const METHODS                = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
   /**
    * @var list<array{p:string,h:string|Closure}>
    */
   protected array $routes                = [];
   /**
    * @var list<array{p:string,h:string|Closure}>
    */
   protected array $middleware            = [];
   protected ?Closure $not_found_callback = null;
   protected int $count_exec_middleware   = 0;
   protected ?string $class_handle        = null;

   function __construct(Request $request)
   {
      $this->request = $request;
   }

   function addRoute(RouteAbstract $route): self
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

   function run(?Closure $callback = null): void
   {
      if ($this->middleware) $this->handle($this->middleware);
      $this->middleware = [];

      $num_handled = 0;
      if ($this->routes) {
         $num_handled = $this->handle($this->routes, true);
      }
      $this->routes = [];

      if ($num_handled === 0) {
         $this->trigger404();
      } else {
         if ($callback) $callback();
      }
   }

   function middleware(string $methods, string $pattern, string|Closure $handle): self
   {
      $r = $this->add($methods, $pattern, $handle);
      if ($r === null) return $this;

      $this->middleware[] = $r;
      return $this;
   }

   function route(string $methods, string $pattern, string|Closure $handle, string|Closure ...$middlewares): self
   {
      $r = $this->add($methods, $pattern, $handle);
      if ($r === null) return $this;

      $this->routes[] = $r;

      if ($middlewares) {
         foreach ($middlewares as $m) {
            $this->middleware($methods, $pattern, $m);
         }
      }
      return $this;
   }

   function all(string $pattern, string|Closure $handle): self
   {
      return $this->route(self::METHODS, $pattern, $handle);
   }

   function any(string $pattern, string|Closure $handle): self
   {
      return $this->route(self::METHODS, $pattern, $handle);
   }

   function get(string $pattern, string|Closure $handle): self
   {
      return $this->route('GET', $pattern, $handle);
   }

   function head(string $pattern, string|Closure $handle): self
   {
      return $this->route('HEAD', $pattern, $handle);
   }

   function post(string $pattern, string|Closure $handle): self
   {
      return $this->route('POST', $pattern, $handle);
   }

   function patch(string $pattern, string|Closure $handle): self
   {
      return $this->route('PATCH', $pattern, $handle);
   }

   function delete(string $pattern, string|Closure $handle): self
   {
      return $this->route('DELETE', $pattern, $handle);
   }

   function destroy(string $pattern, string|Closure $handle): self
   {
      return $this->delete($pattern, $handle);
   }

   function put(string $pattern, string|Closure $handle): self
   {
      return $this->route('PUT', $pattern, $handle);
   }

   function options(string $pattern, string|Closure $handle): self
   {
      return $this->route('OPTIONS', $pattern, $handle);
   }

   function getCountExecMiddleware(): int
   {
      return $this->count_exec_middleware;
   }

   function getClassHandle(): ?string
   {
      return $this->class_handle;
   }

   function set404(Closure $handle): void
   {
      $this->not_found_callback = $handle;
   }

   function trigger404(): void
   {
      if ($this->not_found_callback) ($this->not_found_callback)();
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

   /**
    * @return array{p:string,h:string|\Closure}|null
    */
   protected function add(string $methods, string $pattern, string|Closure $handle): ?array
   {
      if (!\str_contains($this->prepareMethod($methods), $this->request->getMethod())) {
         return null;
      }

      return [
         'p' => $this->preparePattern($pattern),
         'h' => $handle,
      ];
   }

   protected function preparePattern(string $pattern): string
   {
      return '/' . \trim($pattern, '/');
   }

   /**
    * @param array<mixed> $matches
    * @return bool -> is match yes/no
    */
   protected function patternMatches(string $pattern, string $uri, ?array &$matches, int $flags): bool
   {
      $pattern = \str_replace(
         ['{int_unsigned}',     '{int}',                 '{letters}'],
         ['(0|[1-9][0-9]{0,})', '(0|\-?[1-9][0-9]{0,})', '([a-zA-Z]+)'],
         $pattern
      );
      $pattern = \preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

      return \boolval(\preg_match_all('#^' . $pattern . '$#', $uri, $matches, $flags));
   }

   /**
    * @param list<array{p:string,h:string|Closure}> $routes
    */
   protected function handle(array &$routes, bool $after_middleware = false): int
   {
      $num_handled = 0;

      $path = $this->request->getPath();

      foreach ($routes as $idx => $route) {

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

            ++$num_handled;

            // вылетаем сразу после одного контроллера
            if ($after_middleware) break;
         }

         unset($routes[$idx]);
      }

      // записываем сколько было middleware
      if (!$after_middleware) $this->count_exec_middleware = $num_handled;

      return $num_handled;
   }

   /**
    * @param array<string|null> $params
    */
   protected function execMethodClass(string $class, string $method, array $params): void
   {
      // try {
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
      // } catch (\Throwable $e) {
      // }
   }

   // protected function isPublicMethod(string $class, string $method): bool
   // {
   //    return (new \ReflectionMethod($class, $method))->isPublic();
   // }

   /**
    * @param array<string|null> $params
    */
   protected function exec(string|Closure $handle, array $params = []): void
   {
      if (!\is_string($handle)) {
         $handle(...$params);
      } elseif (\str_contains($handle, '@')) {
         // вызвать метод класса
         list($handle, $method) = \explode('@', $handle);
         $this->class_handle = $handle;
         $this->execMethodClass($handle, $method, $params);
      } else {
         $this->class_handle = $handle;
         $this->execMethodClass($handle, '', $params);
      }
   }

   protected function prepareMethod(string $method): string
   {
      $m = \strtoupper($method);
      if (\str_contains($m, 'ALL')) return self::METHODS;
      return $m;
   }
}
