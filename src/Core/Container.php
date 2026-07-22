<?php
/**
 * src/Core/Container.php
 * -----------------------------------------------------------------------------
 * Contenedor de dependencias mínimo.
 *
 * No es un contenedor completo con inyección automática; simplemente centraliza
 * la creación de servicios compartidos (config, base de datos, auth) y los
 * entrega bajo demanda (lazy). Suficiente para el tamaño del proyecto y fácil
 * de sustituir por un contenedor PSR-11 más adelante.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

use App\Core\Database\DatabaseFactory;
use App\Core\Database\DatabaseInterface;

class Container
{
    /** @var array<string,mixed> Configuración global. */
    private array $config;

    /** @var array<string,object> Instancias ya creadas (singletons). */
    private array $instances = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** Devuelve toda la configuración o una sección concreta. */
    public function config(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? null;
    }

    /** Base de datos activa (según config['database']['driver']). */
    public function db(): DatabaseInterface
    {
        return $this->instances['db'] ??= DatabaseFactory::make($this->config['database']);
    }

    /** Servicio de autenticación. */
    public function auth(): Auth
    {
        return $this->instances['auth'] ??= new Auth($this);
    }
}
