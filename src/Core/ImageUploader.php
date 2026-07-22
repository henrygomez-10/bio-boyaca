<?php
/**
 * src/Core/ImageUploader.php
 * -----------------------------------------------------------------------------
 * Servicio de subida de imágenes. Valida y almacena de forma segura una imagen
 * subida por formulario (multipart/form-data) y devuelve su ruta pública.
 *
 * Medidas de seguridad:
 *   - Solo acepta archivos realmente subidos por HTTP (is_uploaded_file).
 *   - Verifica que el contenido sea una imagen real (getimagesize), no confía
 *     en la extensión ni en el mime del navegador.
 *   - Lista blanca de formatos (JPG, PNG, WEBP, GIF).
 *   - Límite de tamaño.
 *   - Genera un nombre de archivo aleatorio (evita colisiones y traversal).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

class ImageUploader
{
    /** Mime válido => extensión con la que se guarda. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private int $maxBytes = 2 * 1024 * 1024; // 2 MB

    /**
     * @param string $destDir       Carpeta física destino (ej. .../public/uploads/products).
     * @param string $publicPrefix  Prefijo de URL pública (ej. /uploads/products).
     */
    public function __construct(private string $destDir, private string $publicPrefix)
    {
    }

    /**
     * Procesa un archivo de $_FILES. La imagen es OPCIONAL: si no se envió
     * ningún archivo, se considera correcto con path = null.
     *
     * @param array<string,mixed>|null $file  Entrada de $_FILES (ej. $_FILES['image']).
     * @return array{ok:bool,path:?string,error:?string}
     */
    public function handle(?array $file): array
    {
        // Sin archivo: válido (el producto puede no tener imagen).
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null, 'error' => null];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $this->fail('No se pudo subir la imagen. Inténtalo de nuevo.');
        }

        if (($file['size'] ?? 0) > $this->maxBytes) {
            return $this->fail('La imagen supera el máximo de 2 MB.');
        }

        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return $this->fail('Subida no válida.');
        }

        // Verifica que sea realmente una imagen y obtén su mime real.
        $info = @getimagesize($file['tmp_name']);
        $mime = is_array($info) ? ($info['mime'] ?? '') : '';
        if (!isset(self::ALLOWED[$mime])) {
            return $this->fail('Formato no permitido. Usa JPG, PNG, WEBP o GIF.');
        }

        if (!is_dir($this->destDir) && !mkdir($this->destDir, 0777, true) && !is_dir($this->destDir)) {
            return $this->fail('No se pudo preparar el almacenamiento de imágenes.');
        }

        $filename = bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$mime];
        $target   = $this->destDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return $this->fail('No se pudo guardar la imagen.');
        }

        return ['ok' => true, 'path' => $this->publicPrefix . '/' . $filename, 'error' => null];
    }

    /**
     * Elimina un archivo previamente subido, con seguridad: solo borra si la
     * ruta pertenece a la carpeta gestionada por este uploader.
     */
    public function delete(?string $webPath): void
    {
        if ($webPath === null || $webPath === '') {
            return;
        }
        if (!str_starts_with($webPath, $this->publicPrefix . '/')) {
            return; // no es una imagen gestionada por nosotros
        }
        $file = $this->destDir . '/' . basename($webPath);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** @return array{ok:false,path:null,error:string} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'path' => null, 'error' => $message];
    }
}
