<?php
/**
 * src/Controllers/AuthController.php
 * -----------------------------------------------------------------------------
 * Módulo AUTENTICACIÓN. Registro, inicio y cierre de sesión.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
    /** Formulario de registro. */
    public function showRegister(array $params): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
        }
        $this->render('auth/register', [
            'title'      => 'Crear cuenta',
            'publicRoles' => User::publicRoles(),
            'errors'     => [],
            'old'        => [],
        ]);
    }

    /** Procesa el registro. */
    public function register(array $params): void
    {
        $result = $this->auth->register([
            'name'     => $this->input('name'),
            'email'    => $this->input('email'),
            'password' => $this->input('password'),
            'role'     => $this->input('role', 'consumer'),
        ]);

        if (!$result['ok']) {
            $this->render('auth/register', [
                'title'       => 'Crear cuenta',
                'publicRoles' => User::publicRoles(),
                'errors'      => $result['errors'],
                'old'         => ['name' => $this->input('name'), 'email' => $this->input('email')],
            ]);
            return;
        }

        $this->flash('success', '¡Cuenta creada! Bienvenido/a.');
        $this->redirectByRole($result['user']['role']);
    }

    /** Formulario de login. */
    public function showLogin(array $params): void
    {
        if ($this->auth->check()) {
            $this->redirect('/');
        }
        $this->render('auth/login', [
            'title' => 'Iniciar sesión',
            'error' => null,
            'old'   => [],
        ]);
    }

    /** Procesa el login. */
    public function login(array $params): void
    {
        $result = $this->auth->attempt(
            (string) $this->input('email', ''),
            (string) $this->input('password', '')
        );

        if (!$result['ok']) {
            $this->render('auth/login', [
                'title' => 'Iniciar sesión',
                'error' => $result['error'],
                'old'   => ['email' => $this->input('email')],
            ]);
            return;
        }

        $this->flash('success', 'Sesión iniciada.');
        $this->redirectByRole($result['user']['role']);
    }

    /** Cierra la sesión. */
    public function logout(array $params): void
    {
        $this->auth->logout();
        $this->flash('success', 'Sesión cerrada.');
        $this->redirect('/');
    }

    /** Redirige a cada usuario a su panel según el rol. */
    private function redirectByRole(string $role): never
    {
        $this->redirect(match ($role) {
            User::ROLE_PRODUCER => '/productor',
            User::ROLE_ADMIN    => '/admin',
            default             => '/consumidor',
        });
    }
}
