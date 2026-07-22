<?php
/**
 * scripts/seed.php
 * -----------------------------------------------------------------------------
 * Carga datos de demostración en la base de datos ACTIVA (según config).
 *
 * Uso:   php scripts/seed.php
 *
 * Crea usuarios de ejemplo (admin, productor, consumidor) y varios productos.
 * Es idempotente: vacía las colecciones antes de insertar para dejar un estado
 * conocido. NO usar en producción.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$config = require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database\DatabaseFactory;
use App\Models\Order;
use App\Models\User;

$db = DatabaseFactory::make($config['database']);

// --- Limpieza: elimina todo lo existente en cada colección. ------------------
foreach (['users', 'products', 'orders', 'withdrawals'] as $col) {
    foreach ($db->all($col) as $doc) {
        $db->delete($col, (string) $doc['id']);
    }
}

echo "Sembrando datos de demostración...\n";

// --- Usuarios ----------------------------------------------------------------
$admin = $db->insert('users', [
    'name'          => 'Administrador',
    'email'         => 'admin@demo.test',
    'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
    'role'          => User::ROLE_ADMIN,
]);

$producer = $db->insert('users', [
    'name'          => 'Finca La Esperanza',
    'email'         => 'productor@demo.test',
    'password_hash' => password_hash('producer123', PASSWORD_BCRYPT),
    'role'          => User::ROLE_PRODUCER,
]);

$consumer = $db->insert('users', [
    'name'          => 'Cliente Demo',
    'email'         => 'consumidor@demo.test',
    'password_hash' => password_hash('consumer123', PASSWORD_BCRYPT),
    'role'          => User::ROLE_CONSUMER,
]);

// --- Productos ---------------------------------------------------------------
// Un producto por cada categoría del catálogo (ver App\Models\Product), con su
// municipio de origen en Boyacá y su unidad de venta.
$productsSeed = [
    [
        'name' => 'Cuajada fresca campesina', 'category' => 'Lácteos',
        'price' => 9000, 'stock' => 18, 'unit' => 'Libra', 'origin' => 'Chiquinquirá',
        'description' => 'Cuajada fresca hecha el mismo día con leche de la finca.',
    ],
    [
        'name' => 'Huevos criollos x12', 'category' => 'Huevos',
        'price' => 9000, 'stock' => 40, 'unit' => 'Docena', 'origin' => 'Garagoa',
        'description' => 'Huevos de gallina campesina criada en libertad.',
    ],
    [
        'name' => 'Gallina campesina entera', 'category' => 'Carne',
        'price' => 32000, 'stock' => 12, 'unit' => 'Unidad', 'origin' => 'Tibaná',
        'description' => 'Gallina criolla de campo, criada sin concentrados.',
    ],
    [
        'name' => 'Miel de caña artesanal', 'category' => 'Miel',
        'price' => 14000, 'stock' => 25, 'unit' => 'Botella', 'origin' => 'Guateque',
        'description' => 'Miel de caña producida en trapiche tradicional.',
    ],
    [
        'name' => 'Cubios frescos', 'category' => 'Tubérculos y raíces',
        'price' => 4500, 'stock' => 35, 'unit' => 'Libra', 'origin' => 'Ventaquemada',
        'description' => 'Cubios recién cosechados, tubérculo tradicional andino.',
    ],
    [
        'name' => 'Canasta de hortalizas', 'category' => 'Hortalizas',
        'price' => 15000, 'stock' => 20, 'unit' => 'Bolsa', 'origin' => 'Samacá',
        'description' => 'Surtido de hortalizas de temporada cultivadas en la región.',
    ],
    [
        'name' => 'Café molido de altura', 'category' => 'Café',
        'price' => 22000, 'stock' => 30, 'unit' => 'Libra', 'origin' => 'Moniquirá',
        'description' => 'Café cultivado y tostado artesanalmente en Boyacá.',
    ],
    [
        'name' => 'Arepas de Boyacá x5', 'category' => 'Arepas tradicionales',
        'price' => 8500, 'stock' => 50, 'unit' => 'Paquete', 'origin' => 'Chinavita',
        'description' => 'Arepas de maíz pelado amasadas a mano, receta tradicional.',
    ],
];

$created = [];
foreach ($productsSeed as $p) {
    $created[] = $db->insert('products', $p + ['producer_id' => $producer['id']]);
}

/** Índice de producto por nombre, para armar los pedidos de ejemplo. */
$byName = [];
foreach ($created as $p) {
    $byName[$p['name']] = $p;
}

/** Construye una línea de pedido a partir de un producto sembrado. */
$line = static function (array $product, int $qty) use ($producer): array {
    return [
        'product_id'  => $product['id'],
        'producer_id' => $producer['id'],
        'name'        => $product['name'],
        'price'       => (float) $product['price'],
        'qty'         => $qty,
        'unit'        => $product['unit'],
        'origin'      => $product['origin'],
    ];
};

// --- Pedidos de ejemplo ------------------------------------------------------
// Repartidos por semanas del mes en curso para que la billetera del productor
// muestre datos reales en el gráfico de "comportamiento de ventas".
$ordersSeed = [
    ['day' => 3,  'status' => Order::STATUS_DELIVERED, 'items' => [['Arepas de Boyacá x5', 2], ['Huevos criollos x12', 1]]],
    ['day' => 10, 'status' => Order::STATUS_DELIVERED, 'items' => [['Café molido de altura', 2]]],
    ['day' => 12, 'status' => Order::STATUS_DELIVERED, 'items' => [['Cuajada fresca campesina', 3], ['Miel de caña artesanal', 1]]],
    ['day' => 17, 'status' => Order::STATUS_DELIVERED, 'items' => [['Gallina campesina entera', 2]]],
    ['day' => 19, 'status' => Order::STATUS_SHIPPED,   'items' => [['Canasta de hortalizas', 2], ['Cubios frescos', 2]]],
    ['day' => 24, 'status' => Order::STATUS_CONFIRMED, 'items' => [['Miel de caña artesanal', 1], ['Arepas de Boyacá x5', 1]]],
    ['day' => 26, 'status' => Order::STATUS_PENDING,   'items' => [['Huevos criollos x12', 2]]],
];

$month = date('Y-m');
$today = (int) date('j');

foreach ($ordersSeed as $seed) {
    $items = [];
    foreach ($seed['items'] as [$name, $qty]) {
        $items[] = $line($byName[$name], $qty);
    }

    // Nunca sembrar pedidos con fecha futura: si el mes va poco avanzado, los
    // días previstos se recortan al día de hoy.
    $day = min($seed['day'], $today);

    $db->insert('orders', [
        'consumer_id' => $consumer['id'],
        'items'       => $items,
        'subtotal'    => Order::computeSubtotal($items),
        'shipping'    => Order::computeShipping($items),
        'total'       => Order::computeTotal($items),
        'locality'    => 'Suba',
        'address'     => 'Carrera 104 # 145-20, Apto 301',
        'status'      => $seed['status'],
        'created_at'  => sprintf('%s-%02dT10:00:00+00:00', $month, $day),
    ]);
}

echo "Listo.\n\n";
echo "Cuentas de prueba:\n";
echo "  Admin      -> admin@demo.test / admin123\n";
echo "  Productor  -> productor@demo.test / producer123\n";
echo "  Consumidor -> consumidor@demo.test / consumer123\n";
