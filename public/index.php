<?php
/**
 * public/index.php
 * -----------------------------------------------------------------------------
 * Front Controller (punto de entrada único).
 *
 * Todas las peticiones HTTP entran por aquí. El servidor solo debe apuntar su
 * document root a la carpeta /public. El resto del código (src, config, storage)
 * queda fuera del alcance público por seguridad.
 *
 * Flujo:
 *   petición -> bootstrap -> Router -> Controller -> View -> respuesta
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Core\Router;
use App\Core\Container;

// -----------------------------------------------------------------------------
// Archivos estáticos bajo el servidor embebido de PHP.
//
// Cuando la app se sirve con el servidor embebido usando este archivo como
// ROUTER (php -S host:port -t public public/index.php), TODAS las peticiones
// —incluidas /assets/css/style.css, imágenes, JS— entran por aquí. Si la ruta
// corresponde a un archivo real dentro de /public, devolvemos `false` para que
// el servidor lo entregue tal cual (con su Content-Type correcto) en vez de
// enrutarlo. Con Apache/Nginx esta comprobación no aplica (no es cli-server).
// -----------------------------------------------------------------------------
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

// Arranque: autoloader, config y sesión.
$config = require dirname(__DIR__) . '/bootstrap.php';

// Verificación CSRF: toda petición POST debe traer un token válido. Todos los
// formularios incluyen csrf_field(). Evita peticiones cruzadas no autorizadas.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Token de seguridad inválido o expirado. Vuelve a la página e inténtalo de nuevo.');
    }
}

// Contenedor simple: comparte configuración y servicios a los controladores.
$container = new Container($config);

// Carga de rutas y despacho.
$router = new Router($container);
(require dirname(__DIR__) . '/routes/web.php')($router);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
