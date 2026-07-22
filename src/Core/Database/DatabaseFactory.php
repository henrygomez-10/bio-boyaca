<?php
/**
 * src/Core/Database/DatabaseFactory.php
 * -----------------------------------------------------------------------------
 * Fábrica que instancia el driver de base de datos según la configuración.
 *
 * Cambiar de motor es cuestión de cambiar config['database']['driver'].
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core\Database;

class DatabaseFactory
{
    /**
     * @param array<string,mixed> $dbConfig  Sección config['database'].
     */
    public static function make(array $dbConfig): DatabaseInterface
    {
        $driver = $dbConfig['driver'] ?? 'json';

        return match ($driver) {
            'json'     => new JsonDatabase($dbConfig['json']['path']),
            'postgres' => new PostgresDatabase($dbConfig['postgres']),
            'mongo'    => new MongoDatabase($dbConfig['mongo']),
            default    => throw new \InvalidArgumentException("Driver de BD no soportado: {$driver}"),
        };
    }
}
