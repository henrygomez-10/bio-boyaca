<?php
/**
 * src/Core/helpers.php
 * -----------------------------------------------------------------------------
 * Funciones de ayuda globales disponibles en las vistas.
 *
 * Se cargan explícitamente desde el layout principal. Se mantienen mínimas y
 * sin estado. Todas las funciones comprueban function_exists para evitar
 * redeclaraciones.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (!function_exists('e')) {
    /** Escapa una cadena para imprimir de forma segura en HTML. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('flash')) {
    /** Obtiene (y consume) un mensaje flash de la sesión. */
    function flash(string $type): ?string
    {
        $msg = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
}

if (!function_exists('csrf_token')) {
    /** Devuelve (creando si hace falta) el token CSRF de la sesión. */
    function csrf_token(): string
    {
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
    }
}

if (!function_exists('csrf_field')) {
    /** Campo oculto con el token CSRF para incluir en los formularios. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}
