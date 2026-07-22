<?php
/**
 * src/Core/Database/MongoDatabase.php
 * -----------------------------------------------------------------------------
 * Driver de MongoDB / cluster (STUB / pendiente de implementar).
 *
 * La estructura queda lista para conectar a un cluster (MongoDB Atlas o un
 * replica set local). Cuando lo configures:
 *   1. Instala la extensión de PHP (ext-mongodb) y el paquete mongodb/mongodb.
 *   2. Implementa los métodos usando \MongoDB\Client.
 *   3. Cambia config['database']['driver'] = 'mongo'.
 *
 * Como el DatabaseInterface ya está pensado en colecciones/documentos, el mapeo
 * es prácticamente 1 a 1 con Mongo. Ver docs/db/mongo-cluster.md.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core\Database;

class MongoDatabase implements DatabaseInterface
{
    /** @var array<string,mixed> */
    private array $config;

    // private \MongoDB\Database $db; // <- descomenta al implementar

    public function __construct(array $config)
    {
        $this->config = $config;

        // TODO: al implementar, abrir la conexión al cluster:
        //
        // $client   = new \MongoDB\Client($config['uri']);
        // $this->db = $client->selectDatabase($config['database']);
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
        return "MongoDatabase::{$method}() aún no implementado. "
             . 'Ver docs/db/mongo-cluster.md para activar el cluster.';
    }
}
