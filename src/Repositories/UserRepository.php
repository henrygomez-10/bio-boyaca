<?php
/**
 * src/Repositories/UserRepository.php
 * -----------------------------------------------------------------------------
 * Acceso a la colección "users".
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

class UserRepository extends BaseRepository
{
    protected string $collection = 'users';

    /** Busca un usuario por su correo (único). */
    public function findByEmail(string $email): ?array
    {
        $matches = $this->where(['email' => strtolower($email)]);
        return $matches[0] ?? null;
    }

    /** Devuelve todos los usuarios de un rol dado. */
    public function ofRole(string $role): array
    {
        return $this->where(['role' => $role]);
    }
}
