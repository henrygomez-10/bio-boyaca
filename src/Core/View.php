<?php
/**
 * src/Core/View.php
 * -----------------------------------------------------------------------------
 * Renderizador de vistas.
 *
 * Las vistas son plantillas PHP planas ubicadas en src/Views. Este renderizador
 * inyecta variables, envuelve la vista en el layout (header/footer) y expone
 * helpers básicos como e() para escapar HTML y old() para repoblar formularios.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

class View
{
    private array $config;

    public function __construct(private Container $container)
    {
        $this->config = $container->config();
    }

    /**
     * Renderiza una vista.
     *
     * @param string               $view    Ruta relativa sin extensión, ej: "catalog/index".
     * @param array<string,mixed>  $data    Variables disponibles en la vista.
     * @param bool                 $layout  Si se envuelve en el layout general.
     */
    public function render(string $view, array $data = [], bool $layout = true): string
    {
        $viewFile = BASE_PATH . '/src/Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }

        // Variables comunes disponibles en todas las vistas.
        $data += [
            'appName'  => $this->config['app']['name'],
            'tagline'  => $this->config['app']['tagline'],
            'roles'    => $this->config['roles'],
            'auth'     => $this->container->auth(),
            'title'    => $this->config['app']['name'],
        ];

        // Renderiza el contenido de la vista en un buffer.
        $content = $this->capture($viewFile, $data);

        if (!$layout) {
            return $content;
        }

        // Envuelve el contenido en el layout general.
        $data['content'] = $content;
        return $this->capture(BASE_PATH . '/src/Views/layouts/main.php', $data);
    }

    /** Ejecuta una plantilla PHP aislando su scope y devuelve la salida. */
    private function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
