<?php
/**
 * bootstrap.php
 * -----------------------------------------------------------------------------
 * Arranque de la aplicación. Se incluye una sola vez desde public/index.php.
 *
 * Responsabilidades:
 *   1. Definir rutas base (BASE_PATH).
 *   2. Registrar el autoloader PSR-4 (funciona con o sin Composer).
 *   3. Cargar la configuración global.
 *   4. Configurar zona horaria, errores y sesión.
 *   5. Exponer un contenedor mínimo con la configuración.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

// 1. Ruta raíz del proyecto.
define('BASE_PATH', __DIR__);

// 2. Autoloader.
//    Si existe el autoloader de Composer se usa; si no, se usa uno propio
//    (PSR-4) para que el proyecto corra en localhost sin instalar nada.
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        // Mapea el namespace "App\" a la carpeta "src/".
        $prefix  = 'App\\';
        $baseDir = BASE_PATH . '/src/';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

// 3. Funciones de ayuda globales (e(), csrf_field(), flash(), ...).
//    Se cargan aquí para que estén disponibles en TODAS las vistas, incluso
//    cuando se renderizan sin layout.
require_once BASE_PATH . '/src/Core/helpers.php';

// 4. Configuración global.
$config = require BASE_PATH . '/config/config.php';

// 5. Entorno de ejecución.
date_default_timezone_set($config['app']['timezone']);

if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// 6. Sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session']['name']);
    session_start();
}

// Devuelve la configuración a quien incluya este archivo.
return $config;
