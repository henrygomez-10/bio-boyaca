<?php
/**
 * src/Repositories/BaseRepository.php
 * -----------------------------------------------------------------------------
 * Repositorio base. Encapsula el acceso a una colección concreta a través del
 * DatabaseInterface, de modo que los controladores nunca hablan directamente
 * con el driver de base de datos.
 *
 * Los repositorios concretos (UserRepository, ProductRepository, ...) heredan de
 * aquí y añaden consultas específicas de su dominio.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\DatabaseInterface;

abstract class BaseRepository
{
    /** Nombre de la colección que gestiona el repositorio (definir en la hija). */
    protected string $collection;

    public function __construct(protected DatabaseInterface $db)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->all($this->collection);
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        return $this->db->find($this->collection, $id);
    }

    /** @return array<int,array<string,mixed>> */
    public function where(array $criteria): array
    {
        return $this->db->where($this->collection, $criteria);
    }

    /** @return array<string,mixed> */
    public function create(array $data): array
    {
        return $this->db->insert($this->collection, $data);
    }

    /** @return array<string,mixed>|null */
    public function update(string $id, array $changes): ?array
    {
        return $this->db->update($this->collection, $id, $changes);
    }

    public function delete(string $id): bool
    {
        return $this->db->delete($this->collection, $id);
    }

    public function count(): int
    {
        return count($this->all());
    }
}
