<?php
/**
 * src/Core/Auth.php
 * -----------------------------------------------------------------------------
 * Servicio de autenticación basado en sesión.
 *
 * Gestiona registro, login, logout y consulta del usuario actual. Las
 * contraseñas se almacenan con password_hash() (bcrypt) y se verifican con
 * password_verify(). No guarda la contraseña en sesión, solo el id del usuario.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

class Auth
{
    private UserRepository $users;

    public function __construct(Container $container)
    {
        $this->users = new UserRepository($container->db());
    }

    /** ¿Hay una sesión iniciada? */
    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /** Devuelve el usuario actual (array) o null. */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }
        return $this->users->find((string) $_SESSION['user_id']);
    }

    /** ¿El usuario actual tiene el rol indicado? */
    public function hasRole(string $role): bool
    {
        return ($this->user()['role'] ?? null) === $role;
    }

    /**
     * Registra un nuevo usuario.
     *
     * @return array{ok:bool,errors:array<string,string>,user:?array}
     */
    public function register(array $data): array
    {
        $errors = [];

        $name  = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $pass  = $data['password'] ?? '';
        $role  = $data['role'] ?? 'consumer';

        if ($name === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Correo electrónico no válido.';
        } elseif ($this->users->findByEmail($email) !== null) {
            $errors['email'] = 'Ya existe una cuenta con ese correo.';
        }
        if (strlen($pass) < 6) {
            $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
        }
        if (!in_array($role, ['consumer', 'producer'], true)) {
            // El rol admin no se crea por registro público.
            $errors['role'] = 'Rol no válido.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'user' => null];
        }

        $user = $this->users->create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($pass, PASSWORD_BCRYPT),
            'role'          => $role,
        ]);

        $this->login($user);

        return ['ok' => true, 'errors' => [], 'user' => $user];
    }

    /**
     * Intenta iniciar sesión con credenciales.
     *
     * @return array{ok:bool,error:?string,user:?array}
     */
    public function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $user  = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Credenciales incorrectas.', 'user' => null];
        }

        $this->login($user);
        return ['ok' => true, 'error' => null, 'user' => $user];
    }

    /** Marca la sesión como iniciada para un usuario dado. */
    public function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
    }

    /** Cierra la sesión. */
    public function logout(): void
    {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
    }
}
