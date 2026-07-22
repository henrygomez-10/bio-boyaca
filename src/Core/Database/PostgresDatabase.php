<?php
/**
 * src/Core/Database/PostgresDatabase.php
 * -----------------------------------------------------------------------------
 * Driver de PostgreSQL (STUB / pendiente de implementar).
 *
 * La estructura queda lista: cuando quieras activar PostgreSQL, implementa los
 * métodos usando PDO y cambia config['database']['driver'] = 'postgres'.
 *
 * Estrategia de mapeo sugerida (colección -> tabla):
 *   - Cada colección ("users", "products", "orders") es una tabla.
 *   - Puedes almacenar el documento completo en una columna JSONB "data" y una
 *     columna "id" indexada, para mantener flexibilidad de esquema; o normalizar
 *     en columnas reales. Ver sql/schema.sql y docs/db/postgres.md.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core\Database;

class PostgresDatabase implements DatabaseInterface
{
    /** @var array<string,mixed> */
    private array $config;

    // private \PDO $pdo; // <- descomenta al implementar

    public function __construct(array $config)
    {
        $this->config = $config;

        // TODO: al implementar, abrir la conexión PDO:
        //
        // $dsn = sprintf(
        //     'pgsql:host=%s;port=%s;dbname=%s',
        //     $config['host'], $config['port'], $config['dbname']
        // );
        // $this->pdo = new \PDO($dsn, $config['user'], $config['password'], [
        //     \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        //     \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        // ]);
    }

    public function all(string $collection): array
    {
        throw new \RuntimeException($this->pending(__FUNCTION__));
    }

    public function find(string $collection, string $id): ?array
    {
        throw new \RuntimeException($this->pending(__FUNCTION__));
    }

    public function where(string $collection, array $criteria): array
    {
        throw new \RuntimeException($this->pending(__FUNCTION__));
    }

    public function insert(string $collection, array $document): array
    {
        throw new \RuntimeException($this->pending(__FUNCTION__));
    }

    public function update(string $collection, string $id, array $changes): ?array
    {
        throw new \RuntimeException($this->pending(__FUNCTION__));
    }

    public function delete(string $collection, string $id): bool
    {
        throw new \RuntimeException($this->pending(__FUNCTION__));
    }

    private function pending(string $method): string
    {
        return "PostgresDatabase::{$method}() aún no implementado. "
             . 'Ver docs/db/postgres.md para activar este driver.';
    }
}
