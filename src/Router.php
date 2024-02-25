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
   protected readonly Request $request;

   protected const METHODS                = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
   /**
    * @var array<string,list<array{pattern:string,handle:string|Closure}>>
    */
   protected array $routes                = [];
   /**
    * @var array<string,list<array{pattern:string,handle:string|Closure}>>
    */
   protected array $middleware            = [];
   protected ?Closure $not_found_callback = null;
   protected int $count_exec_middleware   = 0;
   protected ?string $class_handle        = null;

   public function __construct()
   {
      $this->request = new Request;
   }

   public function getHandleRequest(): Request
   {
      return $this->request;
   }

   public function addRoute(RouteAbstract $route): self
   {
      $method = $route::METHOD;
      if ($method === null) return $this;
      $pattern = $route::PATTERN;
      if ($pattern === null) return $this;
      $h = $route->getHandle();
      if ($h === null) return $this;
      $m = $route->getMiddleware();
      $this->route($method, $pattern, $h);
      if ($m !== null) {
         $this->middleware($method, $pattern, $m);
      }
      return $this;
   }

   /**
    * @param ?Closure $callback
    */
   public function run(?Closure $callback = null): void
   {
      $method = $this->request->getMethod();
      if (isset($this->middleware[$method])) $this->handle($this->middleware[$method]);
      $this->middleware = [];

      $num_handled = 0;
      if (isset($this->routes[$method])) {
         $num_handled = $this->handle($this->routes[$method], true);
      }
      $this->routes = [];

      if ($num_handled === 0) {
         $this->trigger404();
      } else {
         if ($callback) $callback();
      }
   }

   public function middleware(string $methods, string $pattern, string|Closure $handle): self
   {
      $methods = $this->prepareMethod($methods);
      if (!\str_contains($methods, $this->request->getMethod())) return $this;

      foreach (\explode('|', $methods) as $method) {
         $this->middleware[$method][] = [
            'pattern' => $this->preparePattern($pattern),
            'handle'  => $handle,
         ];
      }
      return $this;
   }

   public function route(string $methods, string $pattern, string|Closure $handle): self
   {
      $methods = $this->prepareMethod($methods);
      if (!\str_contains($methods, $this->request->getMethod())) return $this;

      foreach (\explode('|', $methods) as $method) {
         $this->routes[$method][] = [
            'pattern' => $this->preparePattern($pattern),
            'handle'  => $handle,
         ];
      }
      return $this;
   }

   public function all(string $pattern, string|Closure $handle): self
   {
      return $this->route(self::METHODS, $pattern, $handle);
   }

   public function any(string $pattern, string|Closure $handle): self
   {
      return $this->route(self::METHODS, $pattern, $handle);
   }

   public function get(string $pattern, string|Closure $handle): self
   {
      return $this->route('GET', $pattern, $handle);
   }

   public function head(string $pattern, string|Closure $handle): self
   {
      return $this->route('HEAD', $pattern, $handle);
   }

   public function post(string $pattern, string|Closure $handle): self
   {
      return $this->route('POST', $pattern, $handle);
   }

   public function patch(string $pattern, string|Closure $handle): self
   {
      return $this->route('PATCH', $pattern, $handle);
   }

   public function delete(string $pattern, string|Closure $handle): self
   {
      return $this->route('DELETE', $pattern, $handle);
   }

   public function destroy(string $pattern, string|Closure $handle): self
   {
      return $this->route('DELETE', $pattern, $handle);
   }

   public function put(string $pattern, string|Closure $handle): self
   {
      return $this->route('PUT', $pattern, $handle);
   }

   public function options(string $pattern, string|Closure $handle): self
   {
      return $this->route('OPTIONS', $pattern, $handle);
   }

   public function getCurrentURI(): string
   {
      return $this->request->getURI();
   }

   public function getCountExecMiddleware(): int
   {
      return $this->count_exec_middleware;
   }

   public function getClassHandle(): ?string
   {
      return $this->class_handle;
   }

   public function set404(Closure $handle): void
   {
      $this->not_found_callback = $handle;
   }

   public function trigger404(): void
   {
      if ($this->not_found_callback) ($this->not_found_callback)();
   }

   /**
    * @return array<string,string>
    */
   public function getRequestHeaders(): array
   {
      return $this->request->getHeaders();
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

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
         ['{int_unsigned}',     '{int}'],
         ['(0|[1-9][0-9]{0,})', '(0|\-?[1-9][0-9]{0,})'],
         $pattern
      );
      $pattern = \preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

      return \boolval(\preg_match_all('#^' . $pattern . '$#', $uri, $matches, $flags));
   }

   /**
    * @param list<array{pattern:string,handle:string|Closure}> $routes
    */
   protected function handle(array &$routes, bool $after_middleware = false): int
   {
      $num_handled = 0;

      $uri = $this->getCurrentURI();

      foreach ($routes as $idx => $route) {

         $is_match = $this->patternMatches($route['pattern'], $uri, $matches, PREG_OFFSET_CAPTURE);

         if ($is_match) {
            $matches = \array_slice($matches, 1);

            // ------------------------------------------------------------------
            // EPIC Bramus
            // ------------------------------------------------------------------
            $params = \array_map(function ($match, $index) use ($matches) {
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

            $this->exec($route['handle'], $params);

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
      $method = \strtoupper($method);
      if (\str_contains($method, 'ALL')) return self::METHODS;
      return $method;
   }
}
