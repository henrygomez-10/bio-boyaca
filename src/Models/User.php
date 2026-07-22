<?php
/**
 * src/Models/User.php
 * -----------------------------------------------------------------------------
 * Modelo de dominio para usuarios. Centraliza las constantes de rol para evitar
 * strings sueltos. Las etiquetas legibles de cada rol viven en config['roles'].
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Models;

final class User
{
    public const ROLE_CONSUMER = 'consumer';
    public const ROLE_PRODUCER = 'producer';
    public const ROLE_ADMIN    = 'admin';

    /** @return array<int,string> Roles que un usuario puede elegir al registrarse. */
    public static function publicRoles(): array
    {
        return [self::ROLE_CONSUMER, self::ROLE_PRODUCER];
    }
}
