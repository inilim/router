<?php

namespace Inilim;

use \Closure;

/**
 * @author      Bram(us) Van Damme <bramus@bram.us>
 * @author      inilim
 */
class Router
{
   private const METHODS                = 'GET|POST|PUT|DELETE|OPTIONS|PATCH';
   /**
    * @var array<array{pattern:string, handle:string|Closure}>
    */
   private array $routes                = [];
   /**
    * @var array<array{pattern:string, handle:string|Closure}>
    */
   private array $middleware            = [];
   private ?Closure $not_found_callback = null;
   private string $base_route           = '';
   private ?string $current_method      = null;
   private ?string $server_base_path    = null;
   private ?string $current_uri         = null;



   /**
    * @param ?Closure $callback
    */
   public function run(?Closure $callback = null): void
   {
      $this->current_method = $this->getRequestMethod();
      if (isset($this->middleware[$this->current_method])) {
         // очищаем
         $this->handle($this->middleware[$this->current_method]);
         $this->middleware = [];
      } else {
         // очищаем
         $this->middleware = [];
      }

      $numHandled = 0;
      if (isset($this->routes[$this->current_method])) {
         // очищаем
         $numHandled = $this->handle($this->routes[$this->current_method], true);
         $this->routes = [];
      } else {
         // очищаем
         $this->routes = [];
      }

      if ($numHandled === 0) {
         $this->trigger404();
      } else {
         // для финального завершения
         if ($callback) $callback();
      }
   }

   /**
    * @param string|string[] $methods
    * @param string $pattern
    * @param string|Closure $handle
    */
   public function middleware(string|array $methods, string $pattern, string|Closure $handle): self
   {
      $methods = $this->method($methods);
      if (!str_contains($methods, $this->getRequestMethod())) return $this;
      $pattern = $this->handlePattern($pattern);

      foreach (explode('|', $methods) as $method) {
         $this->middleware[$method][] = [
            'pattern' => $pattern,
            'handle' => $handle,
         ];
      }
      return $this;
   }

   /**
    * @param string|string[] $methods Allowed methods, | delimited
    * @param string $pattern A route pattern such as /about/system
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function route(string|array $methods, string $pattern, $handle): self
   {
      $methods = $this->method($methods);
      if (!str_contains($methods, $this->getRequestMethod())) return $this;
      $pattern = $this->handlePattern($pattern);

      foreach (explode('|', $methods) as $method) {
         $this->routes[$method][] = [
            'pattern' => $pattern,
            'handle' => $handle,
         ];
      }
      return $this;
   }

   protected function handlePattern(string $pattern): string
   {
      $pattern = $this->base_route . '/' . trim($pattern, '/');
      return $this->base_route ? rtrim($pattern, '/') : $pattern;
   }

   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function all(string $pattern, $handle): self
   {
      return $this->route(self::METHODS, $pattern, $handle);
   }

   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function any(string $pattern, $handle): self
   {
      return $this->all($pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function get(string $pattern, $handle): self
   {
      return $this->route('GET', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function post(string $pattern, $handle): self
   {
      return $this->route('POST', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function patch(string $pattern, $handle): self
   {
      return $this->route('PATCH', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function delete(string $pattern, $handle): self
   {
      return $this->route('DELETE', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function destroy(string $pattern, $handle): self
   {
      return $this->delete($pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function put(string $pattern, $handle): self
   {
      return $this->route('PUT', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function options(string $pattern, $handle): self
   {
      return $this->route('OPTIONS', $pattern, $handle);
   }

   /**
    * @return array<string,string>
    */
   protected function getRequestHeaders(): array
   {
      $headers = [];

      if (function_exists('getallheaders')) {
         $headers = getallheaders();
         if ($headers !== false) return $headers;
      }

      foreach ($_SERVER as $name => $value) {
         if (str_starts_with($name, 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
            $key = str_replace(
               [' ', 'Http'],
               ['-', 'HTTP'],
               ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))
            );
            $headers[$key] = $value;
         }
      }

      return $headers;
   }

   /**
    */
   public function getRequestMethod(): string
   {
      if (is_string($this->current_method)) return $this->current_method;
      return $this->current_method = $this->defineRequestMethod();
   }



   /**
    * @param Closure $handle The function to be executed
    */
   public function set404(Closure $handle): void
   {
      $this->not_found_callback = $handle;
   }

   /**
    */
   public function trigger404(): void
   {
      $numHandled = 0;

      if ($this->not_found_callback instanceof Closure) {
         ++$numHandled;
         ($this->not_found_callback)();
      } else {
      }
   }



   /**
    */
   public function getCurrentUri(): string
   {
      if (!is_null($this->current_uri)) return $this->current_uri;
      $uri = substr(rawurldecode($_SERVER['REQUEST_URI'] ?? ''), strlen($this->getBasePath()));
      $pos = strpos($uri, '?');
      if (is_int($pos)) $uri = substr($uri, 0, $pos);
      return $this->current_uri = '/' . trim($uri, '/');
   }

   /**
    */
   public function getBasePath(): string
   {
      if ($this->server_base_path === null) {
         $this->server_base_path = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
      }

      return $this->server_base_path;
   }

   // ------------------------------------------------------------------
   // protected
   // ------------------------------------------------------------------

   protected function defineRequestMethod(): string
   {
      $method = $_SERVER['REQUEST_METHOD'] ?? '';

      if ($method == 'POST') {
         $headers = $this->getRequestHeaders();
         if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
            $method = $headers['X-HTTP-Method-Override'];
         }
      }

      return $method;
   }

   /**
    * @param array<mixed> $matches
    * @return bool -> is match yes/no
    */
   protected function patternMatches(string $pattern, string $uri, ?array &$matches, int $flags): bool
   {
      $pattern = str_replace(
         ['{int_unsigned}',   '{int}'],
         ['(0|[1-9][0-9]{0,})', '(0|\-?[1-9][0-9]{0,})'],
         $pattern
      );
      $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

      return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE));
   }

   /**
    * @param array<array{pattern:string, handle:string|Closure|string[]}> $routes       Collection of route patterns and their handling functions
    * @param bool  $quitAfterRun Does the handle function need to quit after one route was matched?
    */
   protected function handle(array &$routes, bool $quitAfterRun = false): int
   {
      $numHandled = 0;

      $uri = $this->getCurrentUri();

      foreach ($routes as $key => &$route) {

         $is_match = $this->patternMatches($route['pattern'], $uri, $matches, PREG_OFFSET_CAPTURE);

         if ($is_match) {
            $matches = array_slice($matches, 1);

            $params = array_map(function ($match, $index) use ($matches) {
               if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                  if ($matches[$index + 1][0][1] > -1) {
                     return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                  }
               }

               return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
            }, $matches, array_keys($matches));

            $this->invoke($route['handle'], $params);

            ++$numHandled;

            if ($quitAfterRun) break;
         }
         unset($routes[$key]);
      }

      return $numHandled;
   }

   /**
    * @param array<string|null> $params
    */
   protected function tryExec(string $class, string $method, array $params): void
   {
      try {
         $this->exec($class, $method, $params);
      } catch (\Throwable $e) {
      }
   }

   /**
    * @param array<string|null> $params
    */
   protected function exec(string $class, string $method, array $params): void
   {
      if ($method === '') {
         // конструктор
         $reflectedMethod = $this->getReflectionMethod($class, '__construct');
         if (is_null($reflectedMethod)) return;
         new $class(...$params);
      } else {
         // метод указан
         $this->methodSet($class, $method, $params);
      }
   }

   /**
    * @param array<string|null> $params
    */
   protected function methodSet(string $class, string $method, array $params): void
   {
      $reflectedMethod = $this->getReflectionMethod($class, $method);

      if (is_null($reflectedMethod)) {
         // метода нет, проверяем наличие __call()
         $reflectedMethod = $this->getReflectionMethod($class, '__call');
         if (is_null($reflectedMethod)) return;
         // вызываем __call()
         $callable = [new $class, $method];
         if (is_callable($callable)) {
            call_user_func_array($callable, $params);
         } else throw new \Exception('');
      } elseif (!$reflectedMethod->isAbstract()) {
         if ($reflectedMethod->isPublic()) {
            if ($reflectedMethod->isStatic()) {
               $callable = [new $class, $method];
               if (is_callable($callable)) {
                  forward_static_call_array($callable, $params);
               } else throw new \Exception('');
            } else {
               $callable = [new $class, $method];
               if (is_callable($callable)) {
                  call_user_func_array($callable, $params);
               } else throw new \Exception('');
            }
         } else {
            // метода private или protected, проверяем наличие __call()
            $reflectedMethod = $this->getReflectionMethod($class, '__call');
            if (is_null($reflectedMethod)) return;
            // вызываем __call()
            $callable = [new $class, $method];
            if (is_callable($callable)) {
               call_user_func_array($callable, $params);
            } else throw new \Exception('');
         }
      }
   }

   protected function getReflectionMethod(string $class, string $method): ?\ReflectionMethod
   {
      try {
         return new \ReflectionMethod($class, $method);
      } catch (\ReflectionException $e) {
         return null;
      }
   }

   /**
    * @param string[]|string|Closure $handle
    * @param array<string|null> $params
    */
   protected function invoke($handle, array $params = []): void
   {
      if ($handle instanceof \Closure) {
         call_user_func_array($handle, $params);
      } elseif (is_string($handle) && str_contains($handle, '@')) {
         // вызвать метод класса
         list($class, $method) = explode('@', $handle);

         $this->tryExec($class, $method, $params);
      } elseif (is_string($handle)) {
         // Выозов конструктор класса
         $this->tryExec($handle, '', $params);
      } elseif (is_array($handle)) {
         if (sizeof($handle) === 2) {
            // вызвать метод класса
            list($class, $method) = $handle;
            $this->tryExec($class, $method, $params);
         } elseif (sizeof($handle) === 1) {
            // Выозов конструктор класса
            $class = strval(reset($handle));
            $this->tryExec($class, '', $params);
         }
      }
   }

   /**
    * @param string|string[] $method
    */
   protected function method(array|string $method): string
   {
      if (is_string($method)) $method = strtoupper($method);
      if (is_array($method)) $method = strtoupper(implode('|', $method));

      if ($method === 'ALL') return self::METHODS;
      if (str_contains($method, 'ALL')) return self::METHODS;
      return $method;
   }
}
