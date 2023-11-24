<?php

namespace Inilim;

use \Closure;

/**
 * @author      Bram(us) Van Damme <bramus@bram.us>
 * @author      inilim
 */
class Router
{
   private string $methods = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
   /**
    * @var array<array{pattern:string, handle:string|Closure|string[]}> The route patterns and their handling functions
    */
   private array $routes = [];

   /**
    * @var array<array{pattern:string, handle:string|Closure|string[]}> The before middleware route patterns and their handling functions
    */
   private array $middleware = [];

   /**
    * @var ?Closure The function to be executed when no route has been matched
    */
   protected ?Closure $not_found_callback = null;

   /**
    * @var string Current base route, used for (sub)route mounting
    */
   private string $baseRoute = '';

   /**
    * @var ?string The Request Method that needs to be handled
    */
   private ?string $requestedMethod = null;

   /**
    * @var ?string The Server Base Path for Router Execution
    */
   private ?string $serverBasePath = null;

   private ?string $currentUri = null;

   /**
    * Execute the router: Loop all defined before middleware's and routes, and execute the handling function if a match was found.
    *
    * @param ?Closure $callback Function to be executed after a matching route was handled (= after router middleware)
    */
   public function run(?Closure $callback = null): void
   {
      // Define which method we need to handle
      $this->requestedMethod = $this->getRequestMethod();
      // Handle all before middlewares
      if (isset($this->middleware[$this->requestedMethod])) {
         // очищаем
         $this->handle($this->middleware[$this->requestedMethod]);
         $this->middleware = [];
      } else {
         // очищаем
         $this->middleware = [];
      }

      // Handle all routes
      $numHandled = 0;
      if (isset($this->routes[$this->requestedMethod])) {
         // очищаем
         $numHandled = $this->handle($this->routes[$this->requestedMethod], true);
         $this->routes = [];
      } else {
         // очищаем
         $this->routes = [];
      }

      // If no route was handled, trigger the 404 (if any)
      if ($numHandled === 0) {
         $this->trigger404();
      } // If a route was handled, perform the finish callback (if any)
      else {
         // для финального завершения
         if ($callback && $callback instanceof Closure) $callback();
      }

      // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
      if (($_SERVER['REQUEST_METHOD'] ?? '') == 'HEAD') ob_end_clean();
   }

   /**
    * Store a before middleware route and a handling function to be executed when accessed using one of the specified methods.
    *
    * @param string|string[] $methods Allowed methods, | delimited
    * @param string $pattern A route pattern such as /about/system
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function middleware(string|array $methods, string $pattern, $handle): self
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
    * Store a route and a handling function to be executed when accessed using one of the specified methods.
    *
    * @param string|string[] $methods Allowed methods, | delimited
    * @param string $pattern A route pattern such as /about/system
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function match(string|array $methods, string $pattern, $handle): self
   {
      $methods = $this->method($methods);
      if (!str_contains($methods, $this->getRequestMethod())) return $this;
      $pattern = $this->handlePattern($pattern);

      // $this->names[$methods.$pattern] = null;
      foreach (explode('|', $methods) as $method) {
         $this->routes[$method][] = [
            'pattern' => $pattern,
            'handle' => $handle,
         ];
      }
      return $this;
   }

   private function handlePattern(string $pattern): string
   {
      $pattern = $this->baseRoute . '/' . trim($pattern, '/');
      return $this->baseRoute ? rtrim($pattern, '/') : $pattern;
   }

   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function all(string $pattern, $handle): self
   {
      return $this->match($this->methods, $pattern, $handle);
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
      return $this->match('GET', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function post(string $pattern, $handle): self
   {
      return $this->match('POST', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function patch(string $pattern, $handle): self
   {
      return $this->match('PATCH', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function delete(string $pattern, $handle): self
   {
      return $this->match('DELETE', $pattern, $handle);
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
      return $this->match('PUT', $pattern, $handle);
   }
   /**
    * @param string|Closure|string[] $handle The handling function to be executed
    */
   public function options(string $pattern, $handle): self
   {
      return $this->match('OPTIONS', $pattern, $handle);
   }

   /**
    * Get all request headers.
    *
    * @return string[] The request headers
    */
   private function getRequestHeaders(): array
   {
      $headers = [];

      // If getallheaders() is available, use that
      if (function_exists('getallheaders')) {
         $headers = getallheaders();

         // getallheaders() can return false if something went wrong
         if ($headers !== false) return $headers;
      }

      // Method getallheaders() not available or went wrong: manually extract 'm
      foreach ($_SERVER as $name => $value) {
         if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
            $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
         }
      }

      return $headers;
   }

   /**
    * Get the request method used, taking overrides into account.
    *
    * @return string The Request method to handle
    */
   public function getRequestMethod(): string
   {
      if (is_string($this->requestedMethod)) return $this->requestedMethod;
      return $this->requestedMethod = $this->defineRequestMethod();
   }

   private function defineRequestMethod(): string
   {
      // Take the method as found in $_SERVER
      $method = $_SERVER['REQUEST_METHOD'] ?? '';

      // If it's a HEAD request override it to being GET and prevent any output, as per HTTP Specification
      // @url http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
      if ($method == 'HEAD') {
         ob_start();
         $method = 'GET';
      }
      // If it's a POST request, check for a method override header
      elseif ($method == 'POST') {
         $headers = $this->getRequestHeaders();
         if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], array('PUT', 'DELETE', 'PATCH'))) {
            $method = $headers['X-HTTP-Method-Override'];
         }
      }

      return $method === '' ? 'CLI' : $method;
   }

   /**
    * Set the 404 handling function.
    *
    * @param Closure $handle The function to be executed
    */
   public function set404(Closure $handle): void
   {
      $this->not_found_callback = $handle;
   }

   /**
    * Triggers 404 response
    */
   public function trigger404(): void
   {
      // Counter to keep track of the number of routes we've handled
      $numHandled = 0;

      if ($this->not_found_callback instanceof Closure) {
         ++$numHandled;
         ($this->not_found_callback)();
      } else {
         // header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
      }
   }

   /**
    * Replace all curly braces matches {} into word patterns (like Laravel)
    * Checks if there is a routing match
    * @param array<mixed> $matches
    * @return bool -> is match yes/no
    */
   private function patternMatches(string $pattern, string $uri, ?array &$matches, int $flags): bool
   {
      $pattern = str_replace(
         ['{int_unsigned}',   '{int}'],
         ['(0|[1-9][0-9]{0,})', '(0|\-?[1-9][0-9]{0,})'],
         $pattern
      );
      // Replace all curly braces matches {} into word patterns (like Laravel)
      $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

      // we may have a match!
      return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE));
   }

   /**
    * Handle a a set of routes: if a match is found, execute the relating handling function.
    *
    * @param array<array{pattern:string, handle:string|Closure|string[]}> $routes       Collection of route patterns and their handling functions
    * @param bool  $quitAfterRun Does the handle function need to quit after one route was matched?
    */
   private function handle(array &$routes, bool $quitAfterRun = false): int
   {
      // Counter to keep track of the number of routes we've handled
      $numHandled = 0;

      // The current page URL
      $uri = $this->getCurrentUri();

      // Loop all routes
      foreach ($routes as $key => &$route) {

         // get routing matches
         $is_match = $this->patternMatches($route['pattern'], $uri, $matches, PREG_OFFSET_CAPTURE);

         // is there a valid match?
         if ($is_match) {
            // Rework matches to only contain the matches, not the orig string
            $matches = array_slice($matches, 1);

            // Extract the matched URL parameters (and only the parameters)
            $params = array_map(function ($match, $index) use ($matches) {
               // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
               if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                  if ($matches[$index + 1][0][1] > -1) {
                     return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                  }
               } // We have no following parameters: return the whole lot

               return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
            }, $matches, array_keys($matches));

            // Call the handling function with the URL parameters if the desired input is callable
            $this->invoke($route['handle'], $params);

            ++$numHandled;

            // If we need to quit, then quit
            if ($quitAfterRun) break;
         }
         unset($routes[$key]);
      }

      // Return the number of routes handled
      return $numHandled;
   }

   /**
    * @param array<string|null> $params
    */
   private function tryExec(string $class, string $method, array $params): void
   {
      try {
         $this->exec($class, $method, $params);
      } catch (\Throwable $e) {
      }
   }

   /**
    * @param array<string|null> $params
    */
   private function exec(string $class, string $method, array $params): void
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
   private function methodSet(string $class, string $method, array $params): void
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
               // Make sure we have an instance, because a non-static method must not be called statically
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

   private function getReflectionMethod(string $class, string $method): ?\ReflectionMethod
   {
      try {
         return new \ReflectionMethod($class, $method);
      } catch (\ReflectionException $e) {
         // The controller class is not available or the class does not have the method $method
         return null;
      }
   }

   /**
    * @param string[]|string|Closure $handle
    * @param array<string|null> $params
    */
   private function invoke($handle, array $params = []): void
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
    * Define the current relative URI.
    */
   public function getCurrentUri(): string
   {
      if (!is_null($this->currentUri)) return $this->currentUri;
      // Get the current Request URI and remove rewrite base path from it (= allows one to run the router in a sub folder)
      $uri = substr(rawurldecode($_SERVER['REQUEST_URI'] ?? ''), strlen($this->getBasePath()));
      // if($uri === false) throw new \Exception('substr return bool false');
      // Don't take query params into account on the URL
      $pos = strpos($uri, '?');
      if (is_int($pos)) $uri = substr($uri, 0, $pos);

      // Remove trailing slash + enforce a slash at the start
      return $this->currentUri = '/' . trim($uri, '/');
   }

   /**
    * Return server base Path, and define it if isn't defined.
    */
   public function getBasePath(): string
   {
      // Check if server base path is defined, if not define it.
      if ($this->serverBasePath === null) {
         $this->serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
      }

      return $this->serverBasePath;
   }

   /**
    * Explicilty sets the server base path. To be used when your entry script path differs from your entry URLs.
    * @see https://github.com/bramus/router/issues/82#issuecomment-466956078
    */
   public function setBasePath(string $serverBasePath): void
   {
      $this->serverBasePath = $serverBasePath;
   }

   /**
    * @param string|string[] $method
    */
   private function method(array|string $method): string
   {
      if (is_string($method)) $method = strtoupper($method);
      if (is_array($method)) $method = strtoupper(implode('|', $method));

      if ($method === 'ALL') return $this->methods;
      if (str_contains($method, 'ALL')) return $this->methods;
      return $method;
   }
}
