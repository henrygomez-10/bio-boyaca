<?php
/**
 * src/Core/Router.php
 * -----------------------------------------------------------------------------
 * Enrutador HTTP simple.
 *
 * - Registra rutas por método (GET/POST) y patrón de URL.
 * - Soporta parámetros dinámicos con la sintaxis {nombre}, por ejemplo:
 *       /producto/{id}
 * - Despacha a un controlador con el formato "Clase@metodo".
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:string}> */
    private array $routes = [];

    public function __construct(private Container $container)
    {
    }

    public function get(string $pattern, string $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, string $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, string $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Resuelve la petición actual y ejecuta el controlador correspondiente.
     */
    public function dispatch(string $method, string $uri): void
    {
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = $this->patternToRegex($route['pattern']);
            if (preg_match($regex, $uri, $matches)) {
                // Extrae solo los parámetros con nombre.
                $params = array_filter(
                    $matches,
                    'is_string',
                    ARRAY_FILTER_USE_KEY
                );
                $this->run($route['handler'], $params);
                return;
            }
        }

        // Ninguna ruta coincide.
        $this->notFound();
    }

    /** Convierte "/producto/{id}" en una expresión regular con grupos. */
    private function patternToRegex(string $pattern): string
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    /** Instancia el controlador y llama al método indicado. */
    private function run(string $handler, array $params): void
    {
        [$class, $action] = explode('@', $handler);
        $fqcn = 'App\\Controllers\\' . $class;

        $controller = new $fqcn($this->container);
        $controller->{$action}($params);
    }

    private function notFound(): void
    {
        http_response_code(404);
        $view = new View($this->container);
        echo $view->render('errors/404', ['title' => 'Página no encontrada'], false);
    }
}
