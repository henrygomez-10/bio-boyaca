<?php
/**
 * routes/web.php
 * -----------------------------------------------------------------------------
 * Definición central de rutas. Devuelve una función que recibe el Router y
 * registra todas las rutas de la aplicación.
 *
 * Convención del handler: "Controlador@metodo" (sin el sufijo Controller del
 * namespace; el Router antepone App\Controllers\).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Core\Router;

return static function (Router $r): void {

    // ---- Inicio -------------------------------------------------------------
    $r->get('/', 'HomeController@index');

    // ---- Autenticación ------------------------------------------------------
    $r->get('/registro',  'AuthController@showRegister');
    $r->post('/registro', 'AuthController@register');
    $r->get('/login',     'AuthController@showLogin');
    $r->post('/login',    'AuthController@login');
    $r->post('/logout',   'AuthController@logout');

    // ---- Catálogo y detalle de producto -------------------------------------
    $r->get('/catalogo',        'CatalogController@index');
    $r->get('/producto/{id}',   'ProductController@show');

    // ---- Carrito y checkout -------------------------------------------------
    // El carrito vive en sesión y se puede llenar sin haber iniciado sesión;
    // solo el checkout exige rol 'consumer'.
    $r->get('/carrito',            'CartController@show');
    $r->post('/carrito/agregar',   'CartController@add');
    $r->post('/carrito/cantidad',  'CartController@updateQty');
    $r->post('/carrito/eliminar',  'CartController@remove');

    // ---- Perfil del productor (gestión de productos y pedidos) --------------
    $r->get('/productor',                 'ProducerController@dashboard');
    $r->get('/productor/billetera',       'ProducerController@wallet');
    $r->post('/productor/billetera/retiro', 'ProducerController@withdraw');
    $r->get('/productor/productos',       'ProducerController@products');
    $r->get('/productor/productos/nuevo', 'ProducerController@createForm');
    $r->post('/productor/productos',      'ProducerController@store');
    $r->get('/productor/productos/{id}/editar', 'ProducerController@editForm');
    $r->post('/productor/productos/{id}',       'ProducerController@update');
    $r->post('/productor/productos/{id}/eliminar', 'ProducerController@destroy');
    $r->get('/productor/pedidos',         'ProducerController@orders');
    $r->post('/productor/pedidos/{id}/estado', 'ProducerController@updateOrderStatus');

    // ---- Perfil del consumidor ---------------------------------------------
    $r->get('/consumidor',          'ConsumerController@dashboard');
    $r->get('/consumidor/pedidos',  'ConsumerController@orders');
    // Confirmar y pagar el carrito completo (antes era la compra de 1 producto).
    $r->post('/pedido',             'CartController@checkout');

    // ---- Panel de administración (básico) -----------------------------------
    $r->get('/admin',            'AdminController@dashboard');
    $r->get('/admin/usuarios',   'AdminController@users');
    $r->get('/admin/productos',  'AdminController@products');
    $r->get('/admin/pedidos',    'AdminController@orders');
};
