# Guía: activar el driver de PostgreSQL

Esta guía documenta cómo pasar de `JsonDatabase` (activo en desarrollo) al
driver `App\Core\Database\PostgresDatabase`, respetando exactamente el
contrato de `App\Core\Database\DatabaseInterface`
(`all`, `find`, `where`, `insert`, `update`, `delete`) para que ningún
repositorio (`src/Repositories/*.php`) ni módulo superior tenga que cambiar.

> Este documento es una guía de implementación. `PostgresDatabase.php` sigue
> siendo un stub a propósito (ver instrucciones del proyecto); aquí se explica
> **cómo** completarlo cuando se decida activar PostgreSQL.

---

## 1. Requisitos

- PostgreSQL 13 o superior (usa `gen_random_uuid()` nativo vía `pgcrypto`,
  ver `sql/schema.sql`).
- Extensión de PHP **`pdo_pgsql`** habilitada (`php -m | grep pdo_pgsql`).
  - En `php.ini`: descomentar `extension=pdo_pgsql`.
  - No se necesita ningún paquete Composer adicional: PDO es parte del núcleo
    de PHP.

## 2. Crear la base de datos

```bash
# Crea el rol/base si aún no existen (ajusta usuario/host a tu entorno)
createdb -h 127.0.0.1 -U postgres proyecto_henry
```

## 3. Aplicar el esquema

El esquema completo (tablas, ENUMs, índices, FKs) está en `sql/schema.sql`.

```bash
psql -h 127.0.0.1 -U postgres -d proyecto_henry -f sql/schema.sql
```

Esto crea `users`, `products`, `orders`, `order_items`, los tipos
`user_role` / `order_status`, los índices (incluyendo el único de
`users.email`) y los triggers de `updated_at`.

## 4. Configurar la aplicación

`config/config.php` ya expone la sección `database.postgres` leyendo
variables de entorno (`PG_HOST`, `PG_PORT`, `PG_DBNAME`, `PG_USER`,
`PG_PASSWORD`). Para activar el driver solo hace falta:

```bash
# .env o variables de entorno del servidor
DB_DRIVER=postgres
PG_HOST=127.0.0.1
PG_PORT=5432
PG_DBNAME=proyecto_henry
PG_USER=postgres
PG_PASSWORD=********
```

No se toca ningún repositorio: el contenedor de servicios de la app resuelve
`DatabaseInterface` al driver indicado por `database.driver`.

---

## 5. Mapeo colección → tabla

| Colección (`DatabaseInterface`) | Tabla SQL      | Notas                                   |
|----------------------------------|---------------|------------------------------------------|
| `users`                          | `users`       | 1:1                                       |
| `products`                       | `products`    | 1:1                                       |
| `orders`                         | `orders` + `order_items` | El array `items` del documento se reconstruye/persiste con `order_items` (ver §7). |

Como `$collection` es un `string` que llega desde los repositorios y se usa
para construir SQL, **nunca se interpola directamente**: se valida contra una
lista blanca de tablas permitidas. Esto evita inyección SQL vía nombre de
tabla (PDO no permite bindear identificadores, solo valores).

```php
private const TABLE_MAP = [
    'users'    => 'users',
    'products' => 'products',
    'orders'   => 'orders',
];

private function table(string $collection): string
{
    if (!isset(self::TABLE_MAP[$collection])) {
        throw new \InvalidArgumentException("Colección desconocida: {$collection}");
    }
    return self::TABLE_MAP[$collection];
}
```

De la misma forma, las claves de `$criteria` en `where()` y de `$changes` en
`update()` deben validarse contra las columnas reales de la tabla (whitelist
de columnas) antes de usarlas para construir el `WHERE`/`SET` dinámico.

---

## 6. Conexión (constructor)

```php
public function __construct(array $config)
{
    $this->config = $config;

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $config['host'], $config['port'], $config['dbname']
    );

    $this->pdo = new \PDO($dsn, $config['user'], $config['password'], [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false, // usa prepared statements reales del servidor
    ]);
}
```

## 7. Implementación método a método

### `all(string $collection): array`

```php
public function all(string $collection): array
{
    if ($collection === 'orders') {
        return $this->allOrders();
    }

    $table = $this->table($collection);
    $stmt  = $this->pdo->query("SELECT * FROM {$table} ORDER BY created_at ASC");
    return $stmt->fetchAll();
}
```

### `find(string $collection, string $id): ?array`

```php
public function find(string $collection, string $id): ?array
{
    if ($collection === 'orders') {
        return $this->findOrder($id);
    }

    $table = $this->table($collection);
    $stmt  = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}
```

### `where(string $collection, array $criteria): array`

Construye un `WHERE` de igualdad con `AND`, con nombres de columna validados
contra una whitelist (usa `information_schema` en el arranque o una lista
estática por tabla) y valores siempre bindeados como parámetros:

```php
public function where(string $collection, array $criteria): array
{
    $table = $this->table($collection);

    if ($criteria === []) {
        return $this->all($collection);
    }

    $conditions = [];
    $params     = [];
    foreach ($criteria as $column => $value) {
        $this->assertValidColumn($table, $column); // whitelist, evita SQLi en identificadores
        $param              = 'p_' . $column;
        $conditions[]       = "{$column} = :{$param}";
        $params[$param]     = $value;
    }

    $sql  = "SELECT * FROM {$table} WHERE " . implode(' AND ', $conditions);
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if ($collection === 'orders') {
        return array_map(fn (array $o) => $this->attachItems($o), $rows);
    }
    return $rows;
}
```

### `insert(string $collection, array $document): array`

Para `users`/`products` es un `INSERT ... RETURNING *` directo. Para
`orders` es una transacción: inserta la cabecera y luego cada línea en
`order_items`.

```php
public function insert(string $collection, array $document): array
{
    if ($collection === 'orders') {
        return $this->insertOrder($document);
    }

    $table   = $this->table($collection);
    $columns = array_keys($document);
    $placeholders = array_map(fn ($c) => ':' . $c, $columns);

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s) RETURNING *',
        $table, implode(',', $columns), implode(',', $placeholders)
    );

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($document);
    return $stmt->fetch();
}

private function insertOrder(array $document): array
{
    $items = $document['items'] ?? [];
    unset($document['items']);

    $this->pdo->beginTransaction();
    try {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (consumer_id, total, status)
             VALUES (:consumer_id, :total, :status) RETURNING *'
        );
        $stmt->execute([
            'consumer_id' => $document['consumer_id'],
            'total'       => $document['total']  ?? 0,
            'status'      => $document['status'] ?? 'pending',
        ]);
        $order = $stmt->fetch();

        $itemStmt = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, producer_id, name, price, qty)
             VALUES (:order_id, :product_id, :producer_id, :name, :price, :qty)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                'order_id'    => $order['id'],
                'product_id'  => $item['product_id'],
                'producer_id' => $item['producer_id'],
                'name'        => $item['name'],
                'price'       => $item['price'],
                'qty'         => $item['qty'],
            ]);
        }

        $this->pdo->commit();
    } catch (\Throwable $e) {
        $this->pdo->rollBack();
        throw $e;
    }

    $order['items'] = $items;
    return $order;
}
```

### `update(string $collection, string $id, array $changes): ?array`

```php
public function update(string $collection, string $id, array $changes): ?array
{
    unset($changes['id']); // el id no se modifica, igual que en JsonDatabase

    if ($collection === 'orders') {
        return $this->updateOrder($id, $changes);
    }

    $table = $this->table($collection);
    if ($changes === []) {
        return $this->find($collection, $id);
    }

    $sets   = [];
    $params = ['id' => $id];
    foreach ($changes as $column => $value) {
        $this->assertValidColumn($table, $column);
        $sets[]          = "{$column} = :{$column}";
        $params[$column] = $value;
    }

    $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE id = :id RETURNING *";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}
```

Para `orders`, si `$changes` trae `items`, la actualización debe borrar y
reinsertar las líneas dentro de la misma transacción (patrón "delete + insert"
más simple que un diff fila a fila):

```php
private function updateOrder(string $id, array $changes): ?array
{
    $items = $changes['items'] ?? null;
    unset($changes['items']);

    $this->pdo->beginTransaction();
    try {
        if ($changes !== []) {
            $sets   = [];
            $params = ['id' => $id];
            foreach ($changes as $column => $value) {
                $sets[]          = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $stmt = $this->pdo->prepare(
                'UPDATE orders SET ' . implode(',', $sets) . ' WHERE id = :id'
            );
            $stmt->execute($params);
        }

        if ($items !== null) {
            $del = $this->pdo->prepare('DELETE FROM order_items WHERE order_id = :id');
            $del->execute(['id' => $id]);

            $ins = $this->pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, producer_id, name, price, qty)
                 VALUES (:order_id, :product_id, :producer_id, :name, :price, :qty)'
            );
            foreach ($items as $item) {
                $ins->execute([
                    'order_id'    => $id,
                    'product_id'  => $item['product_id'],
                    'producer_id' => $item['producer_id'],
                    'name'        => $item['name'],
                    'price'       => $item['price'],
                    'qty'         => $item['qty'],
                ]);
            }
        }

        $this->pdo->commit();
    } catch (\Throwable $e) {
        $this->pdo->rollBack();
        throw $e;
    }

    return $this->findOrder($id);
}
```

### `delete(string $collection, string $id): bool`

```php
public function delete(string $collection, string $id): bool
{
    $table = $this->table($collection);
    // ON DELETE CASCADE en order_items.order_id limpia las líneas automáticamente.
    $stmt  = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
}
```

---

## 8. Reconstruir `items` de un pedido con un JOIN

`orders.items` no existe como columna: se reconstruye agregando
`order_items` con `json_agg` + `json_build_object` en un solo `JOIN`, y se
decodifica el resultado con `json_decode()` en PHP:

```php
private function findOrder(string $id): ?array
{
    $sql = <<<SQL
        SELECT o.*,
               COALESCE(
                   json_agg(
                       json_build_object(
                           'product_id',  oi.product_id,
                           'producer_id', oi.producer_id,
                           'name',        oi.name,
                           'price',       oi.price,
                           'qty',         oi.qty
                       ) ORDER BY oi.created_at
                   ) FILTER (WHERE oi.id IS NOT NULL),
                   '[]'
               ) AS items
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.id = :id
        GROUP BY o.id
        SQL;

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    $row['items'] = json_decode($row['items'], true);
    return $row;
}

private function allOrders(): array
{
    // Misma consulta que findOrder(), sin WHERE, para traer todos los pedidos
    // con sus items ya agregados (evita el problema N+1 de una query por orden).
    $sql = <<<SQL
        SELECT o.*,
               COALESCE(json_agg(...) FILTER (WHERE oi.id IS NOT NULL), '[]') AS items
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
        ORDER BY o.created_at ASC
        SQL;

    $stmt = $this->pdo->query($sql);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['items'] = json_decode($row['items'], true);
    }
    return $rows;
}

private function attachItems(array $order): array
{
    $stmt = $this->pdo->prepare(
        'SELECT product_id, producer_id, name, price, qty
         FROM order_items WHERE order_id = :id ORDER BY created_at'
    );
    $stmt->execute(['id' => $order['id']]);
    $order['items'] = $stmt->fetchAll();
    return $order;
}
```

`attachItems()` (una query aparte por pedido, más simple de leer) se usa en
`where()` porque ahí normalmente se manejan pocos resultados; `findOrder()` /
`allOrders()` usan el `JOIN` con `json_agg` para evitar el problema N+1 al
listar todos los pedidos.

## 9. Notas finales

- `id` se define como `UUID` en el esquema; PDO lo devuelve como string, por
  lo que es compatible sin cambios con la firma `find(string $collection,
  string $id): ?array` y con el resto del contrato.
- Los `ENUM` (`user_role`, `order_status`) rechazan valores inválidos a nivel
  de base de datos, como capa extra de defensa además de la validación en
  los controladores/modelos (`App\Models\User`, `App\Models\Order`).
- Ver el bloque final de `sql/schema.sql` para la alternativa de esquema
  documental (columna `JSONB`), útil si en el futuro se prefiere no
  normalizar `order_items`.
