<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: URL-Routing an die Controller
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
declare(strict_types=1);

namespace Infocraft;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: array|callable}> */
    private array $routes = [];

    /**
     * Handler als Array [Klasse, Methode] oder callable.
     * Wichtig: array|callable statt callable, weil PHP bei der Typprüfung
     * einer [Klasse, Methode]-Angabe den Autoloader NICHT auslöst und die
     * Controller-Klassen erst beim Dispatch bekannt sind.
     */
    public function get(string $pattern, array|callable $handler): void
    {
        $this->routes[] = ['method' => 'GET', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function post(string $pattern, array|callable $handler): void
    {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                call_user_func($route['handler'], $params);
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
