<?php
/**
 * config/config.php
 * -----------------------------------------------------------------------------
 * Configuración global de la aplicación.
 *
 * IMPORTANTE: El nombre del proyecto ("BioBoyacá") se define aquí como una única
 * variable global. Si alguna vez cambia, cámbialo SOLO en la clave app.name (o
 * define la variable de entorno APP_NAME) y se reflejará en toda la aplicación
 * (título, encabezado, correos, etc.). No lo hardcodees en vistas.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

return [

    // -------------------------------------------------------------------------
    // Identidad de la aplicación
    // -------------------------------------------------------------------------
    'app' => [
        // Nombre definitivo del proyecto. Sigue centralizado aquí: cámbialo solo
        // en esta clave (o exporta la variable de entorno APP_NAME) y se refleja
        // en toda la aplicación (título, encabezado, correos, etc.).
        'name'        => getenv('APP_NAME') ?: 'BioBoyacá',
        'tagline'     => 'Del campo boyacense a tu mesa en Bogotá',
        'env'         => getenv('APP_ENV') ?: 'local',
        'debug'       => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOL),
        'base_url'    => getenv('APP_BASE_URL') ?: 'http://localhost:8000',
        'timezone'    => 'America/Bogota',
    ],

    // -------------------------------------------------------------------------
    // Base de datos
    //
    // 'driver' selecciona el motor de persistencia activo:
    //   - 'json'     -> Persistencia local en archivos JSON (activo ahora).
    //   - 'postgres' -> PostgreSQL (stub listo para implementar).
    //   - 'mongo'    -> MongoDB / cluster (stub listo para implementar).
    //
    // Para cambiar de motor solo se cambia 'driver'. La lógica de los módulos
    // NO cambia porque todos trabajan contra la interfaz de repositorios.
    // -------------------------------------------------------------------------
    'database' => [
        'driver' => getenv('DB_DRIVER') ?: 'json',

        // Driver JSON (activo). Guarda cada colección en un archivo .json.
        'json' => [
            'path' => BASE_PATH . '/storage/data',
        ],

        // Driver PostgreSQL (pendiente de implementar). Ver docs/db/postgres.md
        'postgres' => [
            'host'     => getenv('PG_HOST') ?: '127.0.0.1',
            'port'     => getenv('PG_PORT') ?: '5432',
            'dbname'   => getenv('PG_DBNAME') ?: 'proyecto_henry',
            'user'     => getenv('PG_USER') ?: 'postgres',
            'password' => getenv('PG_PASSWORD') ?: '',
        ],

        // Driver MongoDB / cluster (pendiente). Ver docs/db/mongo-cluster.md
        'mongo' => [
            // Cadena de conexión al cluster (Atlas o réplica local).
            'uri'      => getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017',
            'database' => getenv('MONGO_DB') ?: 'proyecto_henry',
        ],
    ],

    // -------------------------------------------------------------------------
    // Sesión y seguridad
    // -------------------------------------------------------------------------
    'session' => [
        'name'     => 'proyecto_henry_session',
        'lifetime' => 60 * 60 * 2, // 2 horas
    ],

    // Roles disponibles en el sistema. Se usan para autorización.
    'roles' => [
        'consumer' => 'Consumidor',
        'producer' => 'Productor',
        'admin'    => 'Administrador',
    ],
];
