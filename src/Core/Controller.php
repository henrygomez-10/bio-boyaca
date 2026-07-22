<?php
/**
 * src/Core/Controller.php
 * -----------------------------------------------------------------------------
 * Controlador base. Todos los controladores de la aplicación heredan de aquí.
 *
 * Ofrece utilidades compartidas:
 *   - render()   : renderiza una vista.
 *   - redirect() : redirige a otra URL.
 *   - input()    : lee datos de la petición (GET/POST) de forma segura.
 *   - requireAuth() / requireRole() : control de acceso.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected View $view;
    protected Auth $auth;

    public function __construct(protected Container $container)
    {
        $this->view = new View($container);
        $this->auth = $container->auth();
    }

    /** Renderiza una vista y la envía al navegador. */
    protected function render(string $view, array $data = [], bool $layout = true): void
    {
        echo $this->view->render($view, $data, $layout);
    }

    /** Redirige a una ruta interna y termina la ejecución. */
    protected function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    /** Lee un valor de la petición (POST tiene prioridad sobre GET). */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /** Verifica que la petición sea POST; si no, redirige al inicio. */
    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Exige sesión iniciada; si no, redirige al login. */
    protected function requireAuth(): void
    {
        if (!$this->auth->check()) {
            $this->redirect('/login');
        }
    }

    /** Exige que el usuario tenga uno de los roles indicados. */
    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        if (!in_array($this->auth->user()['role'] ?? '', $roles, true)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Acceso denegado']);
            exit;
        }
    }

    /** Guarda un mensaje flash para mostrarlo tras una redirección. */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }
}
