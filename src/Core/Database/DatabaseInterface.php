<?php
/**
 * src/Core/Database/DatabaseInterface.php
 * -----------------------------------------------------------------------------
 * Contrato de persistencia orientado a COLECCIONES (estilo documento).
 *
 * Se diseñó a propósito con vocabulario de colecciones/documentos (no tablas/SQL)
 * para que el mismo contrato sirva tanto para el driver JSON actual como para
 * un futuro driver de MongoDB, y también para PostgreSQL (que puede modelarse
 * con tablas o con columnas JSONB). Cualquier driver que implemente esta
 * interfaz puede sustituir al actual SIN tocar los repositorios ni los módulos.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core\Database;

interface DatabaseInterface
{
    /**
     * Devuelve todos los documentos de una colección.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $collection): array;

    /**
     * Busca un documento por su id.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $collection, string $id): ?array;

    /**
     * Busca documentos que cumplan un conjunto de criterios de igualdad.
     *
     * @param array<string,mixed> $criteria  ej: ['role' => 'producer']
     * @return array<int,array<string,mixed>>
     */
    public function where(string $collection, array $criteria): array;

    /**
     * Inserta un documento nuevo. Genera el id si no viene dado.
     *
     * @param array<string,mixed> $document
     * @return array<string,mixed>  El documento insertado (con id).
     */
    public function insert(string $collection, array $document): array;

    /**
     * Actualiza (merge) un documento existente por id.
     *
     * @param array<string,mixed> $changes
     * @return array<string,mixed>|null  El documento actualizado o null si no existe.
     */
    public function update(string $collection, string $id, array $changes): ?array;

    /** Elimina un documento por id. Devuelve true si existía. */
    public function delete(string $collection, string $id): bool;
}
