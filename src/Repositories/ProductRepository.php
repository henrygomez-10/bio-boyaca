<?php
/**
 * src/Repositories/ProductRepository.php
 * -----------------------------------------------------------------------------
 * Acceso a la colección "products".
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

class ProductRepository extends BaseRepository
{
    protected string $collection = 'products';

    /** Productos publicados por un productor concreto. */
    public function ofProducer(string $producerId): array
    {
        return $this->where(['producer_id' => $producerId]);
    }

    /** Productos filtrados por categoría. */
    public function ofCategory(string $category): array
    {
        return $this->where(['category' => $category]);
    }

    /**
     * Búsqueda simple por texto en nombre y descripción.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term): array
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return $this->all();
        }

        return array_values(array_filter(
            $this->all(),
            static function (array $p) use ($term): bool {
                $haystack = mb_strtolower(($p['name'] ?? '') . ' ' . ($p['description'] ?? ''));
                return str_contains($haystack, $term);
            }
        ));
    }
}
