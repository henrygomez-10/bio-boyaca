# Guía: implementar MongoDB con cluster (replica set / Atlas)

Esta guía documenta cómo completar `App\Core\Database\MongoDatabase` para
conectarse a un **cluster** de MongoDB (replica set local o MongoDB Atlas),
respetando exactamente el contrato de `App\Core\Database\DatabaseInterface`
(`all`, `find`, `where`, `insert`, `update`, `delete`), sin tocar
repositorios ni módulos superiores.

> Igual que en `docs/db/postgres.md`: `MongoDatabase.php` sigue siendo un
> stub a propósito; aquí se explica **cómo** completarlo cuando se active
> este driver.

---

## 1. Requisitos

- Extensión de PHP **`ext-mongodb`** (driver nativo en C, vía PECL):
  ```bash
  pecl install mongodb
  # y en php.ini: extension=mongodb
  ```
- Paquete Composer **`mongodb/mongodb`** (librería de alto nivel que usa
  `\MongoDB\Client`, `\MongoDB\Collection`, etc.):
  ```bash
  composer require mongodb/mongodb
  ```
- Un **cluster** MongoDB accesible: replica set local (mínimo 3 nodos para
  alta disponibilidad real, o 1 nodo como replica set de un solo miembro para
  desarrollo) o un cluster gestionado en **MongoDB Atlas**.

## 2. Configuración de la aplicación

`config/config.php` ya define `database.mongo` a partir de variables de
entorno:

```php
'mongo' => [
    'uri'      => getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017',
    'database' => getenv('MONGO_DB') ?: 'proyecto_henry',
],
```

Para activar el driver:

```bash
DB_DRIVER=mongo
MONGO_URI=mongodb://127.0.0.1:27017,127.0.0.1:27018,127.0.0.1:27019/?replicaSet=rsProyectoHenry
MONGO_DB=proyecto_henry
```

o, apuntando a Atlas:

```bash
DB_DRIVER=mongo
MONGO_URI=mongodb+srv://usuario:password@cluster0.xxxxx.mongodb.net/?retryWrites=true&w=majority
MONGO_DB=proyecto_henry
```

---

## 3. Standalone vs. replica set

| | **Standalone** | **Replica set (cluster)** |
|---|---|---|
| Nodos | 1 `mongod` | N ≥ 3 nodos: 1 primario + secundarios (o un árbitro) |
| Escrituras | Van directo al único nodo | Van al **primario**; se replican por *oplog* a los secundarios |
| Lecturas | Del único nodo | Del primario por defecto; configurable a secundarios (`readPreference`) |
| Failover | No hay: si cae el nodo, no hay servicio | Automático: si el primario cae, los secundarios eligen uno nuevo (elección por *raft-like consensus*) |
| Uso recomendado | Solo desarrollo local rápido | Producción (y Atlas siempre es al menos un replica set por debajo) |

Este proyecto debe apuntar a un **replica set** en cualquier entorno que no
sea un laptop de desarrollo, tanto por disponibilidad como porque
`\MongoDB\Client` con un URI de replica set ya maneja reconexión/failover
de forma transparente para el código de `MongoDatabase`.

---

## 4. Levantar un replica set local

```bash
# 1. Crea directorios de datos para 3 nodos
mkdir -p /data/rs0-0 /data/rs0-1 /data/rs0-2

# 2. Arranca 3 procesos mongod en distintos puertos, mismo nombre de replica set
mongod --replSet rsProyectoHenry --port 27017 --dbpath /data/rs0-0 --bind_ip localhost &
mongod --replSet rsProyectoHenry --port 27018 --dbpath /data/rs0-1 --bind_ip localhost &
mongod --replSet rsProyectoHenry --port 27019 --dbpath /data/rs0-2 --bind_ip localhost &

# 3. Conéctate a uno de ellos e inicializa el replica set
mongosh --port 27017
```

Dentro de `mongosh`:

```js
rs.initiate({
  _id: "rsProyectoHenry",
  members: [
    { _id: 0, host: "localhost:27017" },
    { _id: 1, host: "localhost:27018" },
    { _id: 2, host: "localhost:27019" }
  ]
});

rs.status();          // confirma quién es PRIMARY / SECONDARY
```

URI resultante para la app:

```
MONGO_URI=mongodb://localhost:27017,localhost:27018,localhost:27019/?replicaSet=rsProyectoHenry
```

## 5. Conectar a MongoDB Atlas

1. Crear un cluster (M0 gratuito sirve para desarrollo) en
   https://cloud.mongodb.com — Atlas ya lo despliega como replica set
   (típicamente 3 nodos) de forma administrada.
2. **Network Access**: agregar la IP del servidor/desarrollador (o
   `0.0.0.0/0` solo para pruebas, nunca en producción).
3. **Database Access**: crear un usuario con contraseña y rol
   `readWrite` sobre la base `proyecto_henry`.
4. Copiar el **connection string** (`mongodb+srv://...`) desde "Connect ->
   Drivers" y usarlo como `MONGO_URI`. El prefijo `mongodb+srv://` resuelve
   automáticamente, vía DNS SRV, la lista real de nodos del cluster — el
   driver no necesita conocerlos de antemano.

---

## 6. Consideraciones de cluster (write/read concern, failover)

- **Write concern**: `w=majority` (recomendado, y ya incluido en el URI de
  ejemplo de Atlas) espera confirmación de escritura de la mayoría de los
  nodos antes de dar la operación por exitosa — evita perder datos si el
  primario cae justo después de escribir.
- **Read concern / read preference**: por defecto las lecturas van al
  primario (`primary`). Para repartir carga de lectura se puede usar
  `secondaryPreferred`, aceptando que los secundarios pueden tener un
  pequeño retraso de replicación (*eventual consistency*). Para este
  marketplace (pedidos, stock) se recomienda mantener `primary` o
  `primaryPreferred` para evitar leer stock/pedidos desactualizados.
- **retryWrites=true**: reintenta automáticamente escrituras que fallan por
  una elección de nuevo primario en curso (recomendado, ya viene activado
  por defecto en el driver moderno y en el URI de Atlas).
- **Failover**: cuando el primario cae, el replica set elige un nuevo
  primario en segundos; `\MongoDB\Client`, al recibir el URI con todos los
  *seeds* (o el `+srv` de Atlas), detecta el cambio y redirige las
  operaciones automáticamente. La app no necesita lógica de reconexión
  manual.
- **Índices únicos también en cluster**: `email` en `users` debe tener un
  índice único (`createIndex({email: 1}, {unique: true})`); en un replica
  set la unicidad se sigue garantizando porque solo el primario acepta
  escrituras.

### Diagrama del cluster

```mermaid
flowchart LR
    APP["MongoDatabase (PHP)\n\\MongoDB\\Client"]

    subgraph RS["Replica Set: rsProyectoHenry"]
        P["PRIMARY\n(acepta lecturas y escrituras)"]
        S1["SECONDARY 1"]
        S2["SECONDARY 2"]
        P -- oplog replication --> S1
        P -- oplog replication --> S2
    end

    APP -- "escrituras (w=majority)" --> P
    APP -. "lecturas (opcional, secondaryPreferred)" .-> S1
    APP -. "lecturas (opcional, secondaryPreferred)" .-> S2

    S1 -. "elección de nuevo primario\nsi P cae" .-> S2
```

Texto equivalente si no se renderiza el diagrama:

```
        [ MongoDatabase (PHP) / \MongoDB\Client ]
                       |
        URI con todos los seeds (o mongodb+srv)
                       |
   +-------------------+-------------------+
   |                   |                   |
[PRIMARY] <--oplog--> [SECONDARY 1]  [SECONDARY 2]
   ^                                        |
   |________ elección de nuevo primario ____|
              si PRIMARY cae
```

---

## 7. Mapeo colección/documento

A diferencia de PostgreSQL, el mapeo es **casi 1 a 1**: cada colección del
`DatabaseInterface` (`users`, `products`, `orders`) es directamente una
colección de MongoDB, y cada documento PHP (array asociativo) es
directamente un documento BSON. `orders.items` se guarda tal cual, como un
array embebido dentro del documento — no hace falta una colección aparte
equivalente a `order_items`.

### `id` propio vs. `_id` de Mongo

Mongo asigna automáticamente un `_id` de tipo `ObjectId` a cada documento.
Sin embargo, `DatabaseInterface` trabaja con **ids como `string` simples**
(`find(string $collection, string $id)`), igual que `JsonDatabase` (que usa
`uniqid()`). Mezclar ambos mundos (a veces castear a `ObjectId`, a veces no)
es una fuente típica de bugs y excepciones (`ObjectId` lanza error si el
string no tiene el formato hexadecimal de 24 caracteres).

**Decisión de diseño**: mantener un campo propio `id` (string) como
identificador público, generado por la aplicación, y dejar que Mongo maneje
su `_id` internamente sin exponerlo:

```php
private function newId(): string
{
    return bin2hex(random_bytes(12)); // string de 24 hex chars, legible y estable
}
```

Se crea un índice único sobre `id` en cada colección (una vez, al
aprovisionar el cluster):

```js
db.users.createIndex({ id: 1 }, { unique: true });
db.products.createIndex({ id: 1 }, { unique: true });
db.orders.createIndex({ id: 1 }, { unique: true });
```

Así, `find`/`where`/`update`/`delete` siempre filtran por `{ id: $id }` en
vez de `{ _id: new ObjectId($id) }`, sin importar el formato del string.

---

## 8. Implementación método a método

### Constructor

```php
public function __construct(array $config)
{
    $this->config = $config;

    $client   = new \MongoDB\Client($config['uri'], [], [
        // typeMap: hace que find()/findOne() devuelvan arrays PHP nativos
        // en vez de objetos BSONDocument/BSONArray, listo para los repos.
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
    ]);
    $this->db = $client->selectDatabase($config['database']);
}

private function collection(string $name): \MongoDB\Collection
{
    return $this->db->selectCollection($name);
}

/** Quita el _id interno de Mongo del array devuelto a la app. */
private function clean(?array $doc): ?array
{
    if ($doc === null) {
        return null;
    }
    unset($doc['_id']);
    return $doc;
}
```

### `all(string $collection): array`

```php
public function all(string $collection): array
{
    $cursor = $this->collection($collection)->find();
    $docs   = [];
    foreach ($cursor as $doc) {
        $docs[] = $this->clean($doc);
    }
    return $docs;
}
```

### `find(string $collection, string $id): ?array`

```php
public function find(string $collection, string $id): ?array
{
    $doc = $this->collection($collection)->findOne(['id' => $id]);
    return $this->clean($doc);
}
```

### `where(string $collection, array $criteria): array`

Como `$criteria` ya es un array de igualdades (`['role' => 'producer']`), es
directamente un filtro válido de Mongo — no hace falta traducir nada:

```php
public function where(string $collection, array $criteria): array
{
    $cursor = $this->collection($collection)->find($criteria);
    $docs   = [];
    foreach ($cursor as $doc) {
        $docs[] = $this->clean($doc);
    }
    return $docs;
}
```

Nota: para `OrderRepository::ofProducer()`, que hoy filtra en memoria porque
el criterio está dentro de `items[]`, Mongo sí permite resolverlo en el
servidor con un filtro sobre el array embebido:
`$this->collection('orders')->find(['items.producer_id' => $producerId])`.
Esto queda como mejora futura del repositorio (fuera del alcance de esta
guía, que solo documenta el driver).

### `insert(string $collection, array $document): array`

```php
public function insert(string $collection, array $document): array
{
    $document['id']         = $document['id']         ?? $this->newId();
    $document['created_at'] = $document['created_at'] ?? date('c');

    $this->collection($collection)->insertOne($document);
    // insertOne añade '_id' (ObjectId) al array $document por referencia;
    // clean() lo retira antes de devolverlo a la app.
    return $this->clean($document);
}
```

### `update(string $collection, string $id, array $changes): ?array`

```php
public function update(string $collection, string $id, array $changes): ?array
{
    unset($changes['id']); // el id no se modifica, igual que en JsonDatabase
    $changes['updated_at'] = date('c');

    $doc = $this->collection($collection)->findOneAndUpdate(
        ['id' => $id],
        ['$set' => $changes],
        ['returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );

    return $doc === null ? null : $this->clean((array) $doc);
}
```

### `delete(string $collection, string $id): bool`

```php
public function delete(string $collection, string $id): bool
{
    $result = $this->collection($collection)->deleteOne(['id' => $id]);
    return $result->getDeletedCount() > 0;
}
```

---

## 9. Resumen de por qué el mapeo es más simple que en PostgreSQL

- No hace falta tabla `order_items`: `orders.items` se guarda como array
  embebido dentro del mismo documento, igual que ya lo hace `JsonDatabase`.
- No hace falta whitelist de columnas para `where()`/`update()`: el filtro
  y el `$set` de Mongo aceptan directamente los arrays asociativos que ya
  llegan desde los repositorios.
- El único punto de fricción real es el `id` propio vs. `_id` de Mongo,
  resuelto manteniendo `id` como campo de aplicación indexado de forma
  única (§7), en vez de intentar reusar `_id` como si fuera el id del
  `DatabaseInterface`.
