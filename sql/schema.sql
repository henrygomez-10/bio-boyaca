-- =============================================================================
-- sql/schema.sql
-- -----------------------------------------------------------------------------
-- Esquema PostgreSQL para el marketplace de productores y consumidores
-- (proyecto_henry). Corresponde al modelo de "colecciones" definido en
-- App\Core\Database\DatabaseInterface, normalizado a tablas relacionales.
--
-- Colecciones -> tablas:
--   users          -> users
--   products       -> products
--   orders         -> orders            (cabecera del pedido)
--   orders.items[] -> order_items       (líneas del pedido, normalizadas con FK)
--
-- Cómo aplicar este esquema:
--   1. Crear la base de datos:      createdb proyecto_henry
--   2. Aplicar este archivo:        psql -d proyecto_henry -f sql/schema.sql
--   (Ver docs/db/postgres.md para el detalle completo de activación del driver.)
--
-- Requiere PostgreSQL 13+ (gen_random_uuid() está disponible de forma nativa
-- desde la versión 13 mediante la extensión "pgcrypto"). Si tu versión no la
-- trae por defecto, la extensión se habilita explícitamente más abajo.
-- =============================================================================

BEGIN;

-- -----------------------------------------------------------------------------
-- Extensiones
-- -----------------------------------------------------------------------------
-- pgcrypto aporta gen_random_uuid(), usada como DEFAULT de las claves primarias.
CREATE EXTENSION IF NOT EXISTS pgcrypto;


-- -----------------------------------------------------------------------------
-- Tipos enumerados
-- -----------------------------------------------------------------------------
-- Rol de usuario. Debe coincidir con App\Models\User::ROLE_* y con config['roles'].
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
        CREATE TYPE user_role AS ENUM ('consumer', 'producer', 'admin');
    END IF;
END$$;

-- Estado del pedido. Debe coincidir con App\Models\Order::STATUS_*.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'order_status') THEN
        CREATE TYPE order_status AS ENUM ('pending', 'confirmed', 'shipped', 'delivered', 'cancelled');
    END IF;
END$$;


-- -----------------------------------------------------------------------------
-- Tabla: users
-- -----------------------------------------------------------------------------
-- Usuarios de la plataforma: consumidores, productores y administradores.
-- El id se genera como UUID (texto) para que sea compatible con los ids tipo
-- string que ya produce JsonDatabase (uniqid()) y con los que produciría Mongo.
CREATE TABLE IF NOT EXISTS users (
    id             UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
    name           TEXT          NOT NULL,
    email          TEXT          NOT NULL,
    password_hash  TEXT          NOT NULL,
    role           user_role     NOT NULL DEFAULT 'consumer',

    -- Última dirección de entrega usada por el consumidor. Se guarda al confirmar
    -- un pedido para prellenar el checkout siguiente (ver CartController).
    locality       TEXT          NOT NULL DEFAULT '',
    address        TEXT          NOT NULL DEFAULT '',

    created_at     TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ   NOT NULL DEFAULT now(),

    -- Reglas de validación básicas a nivel de fila.
    CONSTRAINT chk_users_name_not_blank  CHECK (btrim(name) <> ''),
    CONSTRAINT chk_users_email_not_blank CHECK (btrim(email) <> '')
);

-- Único por correo, sin distinguir mayúsculas/minúsculas (UserRepository::findByEmail
-- busca con strtolower()). Un índice único funcional evita duplicados como
-- "ana@mail.com" y "Ana@Mail.com".
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_email ON users (lower(email));

-- Índice de apoyo para filtros por rol (UserRepository::ofRole()).
CREATE INDEX IF NOT EXISTS ix_users_role ON users (role);

COMMENT ON TABLE users IS 'Usuarios: consumidores, productores y administradores.';
COMMENT ON COLUMN users.role IS 'consumer | producer | admin (ver App\Models\User).';


-- -----------------------------------------------------------------------------
-- Tabla: products
-- -----------------------------------------------------------------------------
-- Productos publicados por un productor (users.role = 'producer').
-- La categoría se deja como texto libre (no ENUM) porque el catálogo de
-- categorías vive en código (App\Models\Product::categories()) y puede crecer
-- sin requerir una migración de esquema; aun así se valida que no esté vacía.
CREATE TABLE IF NOT EXISTS products (
    id            UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
    producer_id   UUID          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name          TEXT          NOT NULL,
    description   TEXT          NOT NULL DEFAULT '',
    category      TEXT          NOT NULL,
    price         NUMERIC(12,2) NOT NULL,
    stock         INTEGER       NOT NULL DEFAULT 0,

    -- Unidad de venta (Libra, Docena, Botella...) y municipio de Boyacá de
    -- procedencia. Igual que la categoría, se dejan como texto libre porque las
    -- listas cerradas viven en código (App\Models\Product::units() / ::origins())
    -- y pueden crecer sin migrar el esquema.
    unit          TEXT          NOT NULL DEFAULT '',
    origin        TEXT          NOT NULL DEFAULT '',

    created_at    TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ   NOT NULL DEFAULT now(),

    CONSTRAINT chk_products_name_not_blank     CHECK (btrim(name) <> ''),
    CONSTRAINT chk_products_category_not_blank CHECK (btrim(category) <> ''),
    CONSTRAINT chk_products_price_non_negative CHECK (price >= 0),
    CONSTRAINT chk_products_stock_non_negative CHECK (stock >= 0)
);

-- Productos de un productor concreto (ProductRepository::ofProducer()).
CREATE INDEX IF NOT EXISTS ix_products_producer_id ON products (producer_id);

-- Filtro por categoría (ProductRepository::ofCategory()).
CREATE INDEX IF NOT EXISTS ix_products_category ON products (category);

-- Apoyo a ProductRepository::search(), que hace ILIKE / búsqueda de texto sobre
-- nombre y descripción. pg_trgm permite acelerar LIKE '%termino%' con GIN.
-- (Opcional: descomentar si se usa PostgreSQL en vez del filtrado en memoria.)
-- CREATE EXTENSION IF NOT EXISTS pg_trgm;
-- CREATE INDEX IF NOT EXISTS ix_products_search_trgm
--     ON products USING GIN ((name || ' ' || description) gin_trgm_ops);

COMMENT ON TABLE products IS 'Productos publicados por productores.';
COMMENT ON COLUMN products.producer_id IS 'FK a users.id (debe tener role = producer).';


-- -----------------------------------------------------------------------------
-- Tabla: orders (cabecera del pedido)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id           UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
    consumer_id  UUID          NOT NULL REFERENCES users(id) ON DELETE RESTRICT,

    -- Desglose económico congelado en el momento de la compra: subtotal de los
    -- productos, tarifa de logística Boyacá-Bogotá (App\Models\Order::SHIPPING_FEE)
    -- y total = subtotal + shipping. Se guardan los tres para que el histórico no
    -- cambie si mañana varía la tarifa.
    subtotal     NUMERIC(12,2) NOT NULL DEFAULT 0,
    shipping     NUMERIC(12,2) NOT NULL DEFAULT 0,
    total        NUMERIC(12,2) NOT NULL DEFAULT 0,

    -- Dirección de entrega en Bogotá (App\Models\Order::localities()).
    locality     TEXT          NOT NULL DEFAULT '',
    address      TEXT          NOT NULL DEFAULT '',

    status       order_status  NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ   NOT NULL DEFAULT now(),

    CONSTRAINT chk_orders_total_non_negative    CHECK (total >= 0),
    CONSTRAINT chk_orders_subtotal_non_negative CHECK (subtotal >= 0),
    CONSTRAINT chk_orders_shipping_non_negative CHECK (shipping >= 0)
);

-- Pedidos de un consumidor (OrderRepository::ofConsumer()).
CREATE INDEX IF NOT EXISTS ix_orders_consumer_id ON orders (consumer_id);

-- Filtros habituales por estado (paneles de admin/producer).
CREATE INDEX IF NOT EXISTS ix_orders_status ON orders (status);

COMMENT ON TABLE orders IS 'Cabecera de pedido. Las líneas viven en order_items.';
COMMENT ON COLUMN orders.status IS 'pending | confirmed | shipped | delivered | cancelled (ver App\Models\Order).';


-- -----------------------------------------------------------------------------
-- Tabla: order_items (líneas del pedido)
-- -----------------------------------------------------------------------------
-- Normaliza el array "items" que en el driver JSON/Mongo vive embebido dentro
-- del documento de la orden:
--   ['product_id'=>.., 'producer_id'=>.., 'name'=>.., 'price'=>.., 'qty'=>..]
--
-- name y price se guardan como "snapshot" (copia del valor del producto en el
-- momento de la compra), NO se recalculan desde products, porque el precio de
-- un producto puede cambiar después de que el pedido ya fue creado. product_id
-- y producer_id sí son FK reales para trazabilidad e integridad referencial.
CREATE TABLE IF NOT EXISTS order_items (
    id           UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id     UUID          NOT NULL REFERENCES orders(id)   ON DELETE CASCADE,
    product_id   UUID          NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    producer_id  UUID          NOT NULL REFERENCES users(id)    ON DELETE RESTRICT,
    name         TEXT          NOT NULL,   -- snapshot del nombre del producto
    price        NUMERIC(12,2) NOT NULL,   -- snapshot del precio unitario
    unit         TEXT          NOT NULL DEFAULT '', -- snapshot de la unidad de venta
    origin       TEXT          NOT NULL DEFAULT '', -- snapshot del municipio de origen
    qty          INTEGER       NOT NULL,
    created_at   TIMESTAMPTZ   NOT NULL DEFAULT now(),

    CONSTRAINT chk_order_items_price_non_negative CHECK (price >= 0),
    CONSTRAINT chk_order_items_qty_positive       CHECK (qty > 0)
);

-- Reconstruir items de un pedido (JOIN orders <-> order_items). Ver docs/db/postgres.md.
CREATE INDEX IF NOT EXISTS ix_order_items_order_id    ON order_items (order_id);

-- Pedidos que contienen productos de un productor (OrderRepository::ofProducer()),
-- hoy resuelto en memoria filtrando App\Models\Order; con esta tabla se puede
-- resolver con una consulta SQL directa: SELECT DISTINCT order_id FROM order_items
-- WHERE producer_id = :producer_id.
CREATE INDEX IF NOT EXISTS ix_order_items_producer_id ON order_items (producer_id);
CREATE INDEX IF NOT EXISTS ix_order_items_product_id  ON order_items (product_id);

COMMENT ON TABLE order_items IS 'Líneas de pedido normalizadas (una fila por producto pedido).';


-- -----------------------------------------------------------------------------
-- Tabla: withdrawals (retiros de fondos del productor)
-- -----------------------------------------------------------------------------
-- Solicitudes de retiro del saldo acumulado en la billetera del productor
-- (App\Repositories\WithdrawalRepository, colección "withdrawals").
--
-- IMPORTANTE: hoy los retiros son SIMULADOS. Se registra la solicitud para que
-- el saldo disponible se descuente de forma coherente, pero no hay integración
-- con ninguna pasarela de pago ni movimiento de dinero real. Cuando se conecte
-- una pasarela, 'status' es el campo donde reflejar el estado de la transferencia.
CREATE TABLE IF NOT EXISTS withdrawals (
    id           UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
    producer_id  UUID          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount       NUMERIC(12,2) NOT NULL,
    status       TEXT          NOT NULL DEFAULT 'requested',
    created_at   TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ   NOT NULL DEFAULT now(),

    CONSTRAINT chk_withdrawals_amount_positive CHECK (amount > 0)
);

-- Retiros de un productor (WithdrawalRepository::ofProducer()).
CREATE INDEX IF NOT EXISTS ix_withdrawals_producer_id ON withdrawals (producer_id);

COMMENT ON TABLE withdrawals IS 'Solicitudes de retiro de la billetera del productor (simuladas).';


-- -----------------------------------------------------------------------------
-- Triggers: mantener updated_at automáticamente
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_products_updated_at ON products;
CREATE TRIGGER trg_products_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_orders_updated_at ON orders;
CREATE TRIGGER trg_orders_updated_at
    BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_withdrawals_updated_at ON withdrawals;
CREATE TRIGGER trg_withdrawals_updated_at
    BEFORE UPDATE ON withdrawals
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMIT;


-- =============================================================================
-- ALTERNATIVA DOCUMENTAL (JSONB) — no ejecutar junto con lo anterior
-- -----------------------------------------------------------------------------
-- Si en el futuro se prefiere no normalizar (por ejemplo para acelerar el
-- desarrollo o para parecerse más al modelo de documentos que ya usan
-- JsonDatabase/MongoDatabase), PostgresDatabase también podría implementarse
-- guardando el documento completo en una columna JSONB, tal como sugiere el
-- comentario de cabecera de src/Core/Database/PostgresDatabase.php:
--
--   CREATE TABLE IF NOT EXISTS docs_users (
--       id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
--       data       JSONB NOT NULL,           -- documento completo (name, email, role, ...)
--       created_at TIMESTAMPTZ NOT NULL DEFAULT now()
--   );
--   -- Índice único sobre un campo dentro del JSON (equivalente a users.email):
--   CREATE UNIQUE INDEX IF NOT EXISTS ux_docs_users_email
--       ON docs_users (lower(data ->> 'email'));
--   -- Índice GIN para consultas por cualquier clave del documento (equivalente
--   -- genérico al método where($collection, $criteria) del DatabaseInterface):
--   CREATE INDEX IF NOT EXISTS ix_docs_users_data ON docs_users USING GIN (data);
--
-- La misma idea se replicaría para docs_products y docs_orders (esta última ya
-- podría guardar "items" como un array JSON embebido dentro de "data", sin
-- necesidad de una tabla order_items separada). Esta alternativa es más rápida
-- de evolucionar (no requiere ALTER TABLE al añadir campos) a costa de perder
-- validación fuerte de tipos, FKs reales fila-a-fila e índices B-tree clásicos
-- por columna. El esquema normalizado de arriba es el recomendado para este
-- proyecto porque los repositorios ya trabajan con forma de documento estable
-- (users/products/orders con campos fijos), así que conviene aprovechar las
-- garantías relacionales de PostgreSQL (FKs, ENUM, CHECK) en vez de renunciar
-- a ellas.
-- =============================================================================
