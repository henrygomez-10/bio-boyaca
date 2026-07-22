<?php
/**
 * src/Core/Database/JsonDatabase.php
 * -----------------------------------------------------------------------------
 * Driver de persistencia local basado en archivos JSON.
 *
 * Cada "colección" es un archivo <colección>.json dentro de storage/data que
 * contiene un array de documentos. Es el motor ACTIVO durante el desarrollo en
 * localhost; no requiere instalar ninguna base de datos.
 *
 * Notas:
 *   - Usa un bloqueo de archivo (LOCK_EX) al escribir para evitar corrupción en
 *     escrituras concurrentes básicas. No está pensado para alta concurrencia;
 *     para producción se cambia al driver de PostgreSQL o MongoDB.
 *   - Los ids se generan con uniqid() (suficiente para desarrollo).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core\Database;

class JsonDatabase implements DatabaseInterface
{
    public function __construct(private string $path)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    public function all(string $collection): array
    {
        return $this->read($collection);
    }

    public function find(string $collection, string $id): ?array
    {
        foreach ($this->read($collection) as $doc) {
            if ((string) ($doc['id'] ?? '') === $id) {
                return $doc;
            }
        }
        return null;
    }

    public function where(string $collection, array $criteria): array
    {
        return array_values(array_filter(
            $this->read($collection),
            static function (array $doc) use ($criteria): bool {
                foreach ($criteria as $key => $value) {
                    if (($doc[$key] ?? null) !== $value) {
                        return false;
                    }
                }
                return true;
            }
        ));
    }

    public function insert(string $collection, array $document): array
    {
        $docs = $this->read($collection);

        // Id sin caracteres especiales (hex). Se evita el punto de uniqid(...,true)
        // para no confundir a servidores web que lo tratarían como archivo estático,
        // y para obtener URLs limpias como /producto/ab12cd34ef56.
        $document['id']         = $document['id']         ?? bin2hex(random_bytes(8));
        $document['created_at'] = $document['created_at'] ?? date('c');

        $docs[] = $document;
        $this->write($collection, $docs);

        return $document;
    }

    public function update(string $collection, string $id, array $changes): ?array
    {
        $docs    = $this->read($collection);
        $updated = null;

        foreach ($docs as $i => $doc) {
            if ((string) ($doc['id'] ?? '') === $id) {
                unset($changes['id']); // el id no se modifica
                $docs[$i]  = array_merge($doc, $changes, ['updated_at' => date('c')]);
                $updated   = $docs[$i];
                break;
            }
        }

        if ($updated !== null) {
            $this->write($collection, $docs);
        }
        return $updated;
    }

    public function delete(string $collection, string $id): bool
    {
        $docs   = $this->read($collection);
        $before = count($docs);

        $docs = array_values(array_filter(
            $docs,
            static fn (array $doc): bool => (string) ($doc['id'] ?? '') !== $id
        ));

        if (count($docs) === $before) {
            return false;
        }

        $this->write($collection, $docs);
        return true;
    }

    // -------------------------------------------------------------------------
    // Utilidades internas de lectura/escritura de archivos.
    // -------------------------------------------------------------------------

    private function file(string $collection): string
    {
        // Evita rutas maliciosas: solo caracteres seguros en el nombre.
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $collection);
        return $this->path . '/' . $safe . '.json';
    }

    /** @return array<int,array<string,mixed>> */
    private function read(string $collection): array
    {
        $file = $this->file($collection);
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /** @param array<int,array<string,mixed>> $docs */
    private function write(string $collection, array $docs): void
    {
        $file = $this->file($collection);
        $json = json_encode(
            array_values($docs),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        file_put_contents($file, $json, LOCK_EX);
    }
}
