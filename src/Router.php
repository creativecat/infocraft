<?php
declare(strict_types=1);

namespace Infocraft;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => 'GET', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#{([a-z_]+)}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                ($route['handler'])($params);
                return;
            }
        }

        http_response_code(404);
        render('error', [
            'title' => 'Seite nicht gefunden',
            'message' => 'Die angeforderte Seite gibt es nicht. Vielleicht hilft der Link zum Start.',
        ]);
    }
}
