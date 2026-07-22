# Diagramas de flujo por perfil — BioBoyacá

Recorridos de cada tipo de usuario a través del sistema. Los diagramas reflejan
las rutas reales de `routes/web.php` y las reglas verificadas en los
controladores; para el detalle de permisos y reglas de negocio, ver
[`COMPORTAMIENTO.md`](./COMPORTAMIENTO.md).

Hay cuatro perfiles: **visitante** (sin sesión), **consumidor**, **productor** y
**administrador**.

---

## 1. Visión general: el ciclo comercial

Cómo encajan los perfiles entre sí. El productor publica, el consumidor compra,
el productor cumple y cobra; el administrador solo observa.

```mermaid
flowchart TD
    subgraph PROD["🌱 Productor · antes de la venta"]
        P1["Publica un producto<br/>categoría · unidad · origen · foto"]
    end

    subgraph PUB["🌐 Zona pública"]
        C1["Catálogo<br/>búsqueda y filtro por chips"]
        C2["Carrito en sesión<br/>no exige login"]
    end

    subgraph CONS["🛒 Consumidor"]
        K1["Checkout:<br/>dirección + logística"]
        K2["Historial de pedidos"]
    end

    subgraph PROD2["🌱 Productor · después de la venta"]
        P2["Gestiona los pedidos recibidos<br/>y cambia su estado"]
        P3["Billetera:<br/>ingresos y retiro"]
    end

    subgraph ADM["🛠️ Administrador"]
        A1["Paneles de solo lectura:<br/>usuarios · productos · pedidos"]
    end

    P1 --> C1
    C1 --> C2
    C2 --> K1
    K1 -->|"crea el pedido en pending"| P2
    P2 -->|"al marcar delivered"| P3
    P2 -->|"el consumidor ve el estado"| K2
    K1 --> K2
    A1 -.->|"solo observa"| PUB
    A1 -.->|"solo observa"| CONS
    A1 -.->|"solo observa"| PROD2
```

> El dinero se reparte así: el **subtotal de cada línea** va al productor dueño de
> ese producto; la **logística** ($6.000 por pedido) no le corresponde a ninguno.
> Por eso la billetera nunca suma el `total` del pedido. Ver
> [COMPORTAMIENTO §4](./COMPORTAMIENTO.md#4-dinero-cómo-se-calcula-todo).

---

## 2. Visitante (sin sesión)

Puede navegar y **llenar el carrito sin registrarse**. La sesión solo se exige al
pagar, para no cortar la navegación. Como el carrito vive en `$_SESSION`,
**sobrevive al login**: lo que agregó como invitado sigue ahí al volver.

```mermaid
flowchart TD
    V(["Entra sin sesión"]) --> H["Inicio /"]
    H --> CAT["Catálogo /catalogo"]
    CAT --> BUS{"¿Busca o filtra?"}
    BUS -->|"texto o chip de categoría"| CAT
    BUS -->|"elige un producto"| DET["Detalle /producto/id"]
    DET --> ADD["Agregar al carrito<br/>POST /carrito/agregar"]
    CAT --> ADD
    ADD --> CAR["Carrito /carrito"]
    CAR --> PAY{"Pulsa Confirmar y pagar"}
    PAY -->|"no hay sesión"| LOG["Login /login"]
    LOG -->|"no tiene cuenta"| REG["Registro /registro"]
    REG --> ROL{"Elige rol"}
    ROL -->|"consumidor"| KD["Panel del consumidor"]
    ROL -->|"productor"| PD["Panel del productor"]
    LOG -->|"credenciales OK"| KD
    KD --> VUELVE["El carrito sigue intacto:<br/>puede completar la compra"]
```

> El rol **administrador no se puede crear por registro público**: solo lo genera
> `scripts/seed.php`.

---

## 3. Consumidor

Compra y consulta. Una vez creado el pedido, **no puede modificarlo ni
cancelarlo**: el estado lo gobierna el productor.

```mermaid
flowchart TD
    L(["Inicia sesión"]) --> D["Panel /consumidor"]
    D --> CAT["Catálogo /catalogo"]
    CAT --> DET["Detalle /producto/id"]
    DET --> ADD["Agregar al carrito"]
    CAT --> ADD
    ADD --> CAR["Carrito /carrito"]
    CAR --> EDIT{"¿Ajusta el carrito?"}
    EDIT -->|"cambia cantidad"| CAR
    EDIT -->|"quita un producto"| CAR
    EDIT -->|"continúa"| DIR["Dirección de entrega:<br/>localidad de Bogotá + dirección"]
    DIR --> RES["Resumen del pedido:<br/>subtotal + logística = total"]
    RES --> CONF["Confirmar y pagar<br/>POST /pedido"]
    CONF --> VAL{"¿Dirección válida<br/>y stock suficiente?"}
    VAL -->|"No"| ERR["Mensaje de error<br/>vuelve al carrito"]
    ERR --> CAR
    VAL -->|"Sí"| OK["Pedido creado en pending<br/>stock descontado<br/>carrito vaciado<br/>dirección recordada"]
    OK --> HIST["Historial /consumidor/pedidos"]
    HIST --> SEG["Consulta el estado<br/>solo lectura"]
```

Detalles que importan:

- El **stock se revalida justo antes de cobrar**: entre llenar el carrito y pagar,
  otro consumidor puede haberse llevado las últimas unidades.
- Nombre y precio de cada línea se **congelan** en el pedido; si el productor los
  cambia después, el histórico no se altera.
- La dirección se guarda en el usuario para **prellenar** el siguiente checkout.

---

## 4. Productor

Publica su producción, gestiona los pedidos que la incluyen y cobra. Todas sus
acciones sobre un producto o pedido concreto comprueban además la **propiedad**:
no basta con tener el rol.

```mermaid
flowchart TD
    L(["Inicia sesión"]) --> D["Panel /productor"]

    D --> PRODS["Mis productos<br/>/productor/productos"]
    PRODS --> NEW["Nuevo producto<br/>/productor/productos/nuevo"]
    NEW --> FORM["Nombre · categoría · origen<br/>precio · unidad · stock · foto"]
    FORM --> VAL{"¿Validación correcta?"}
    VAL -->|"No"| FORM
    VAL -->|"Sí"| PUBL["Publicado: ya aparece<br/>en el catálogo público"]
    PRODS --> ED["Editar o eliminar<br/>solo productos propios"]

    D --> ORD["Pedidos recibidos<br/>/productor/pedidos"]
    ORD --> OWN{"¿El pedido incluye<br/>algún producto suyo?"}
    OWN -->|"No"| E403["HTTP 403"]
    OWN -->|"Sí"| ST["Cambia el estado"]
    ST --> FLOW["pending → confirmed<br/>→ shipped → delivered"]

    D --> W["Billetera<br/>/productor/billetera"]
    FLOW -->|"al marcar delivered"| W
    W --> MET["Ingresos del mes · entregados<br/>en tránsito · gráfico semanal"]
    W --> RET{"¿Saldo disponible?"}
    RET -->|"Sí"| RETIRO["Retiro simulado<br/>siempre del saldo completo"]
    RET -->|"No"| BLOQ["Botón deshabilitado"]
```

> ⚠️ El flujo `pending → confirmed → shipped → delivered` es la **intención, no una
> restricción**: `updateOrderStatus()` acepta cualquiera de los cinco estados sin
> validar la transición. Y **cancelar no repone el stock**. Ver
> [COMPORTAMIENTO §3](./COMPORTAMIENTO.md#3-ciclo-de-vida-del-pedido).

---

## 5. Administrador

Perfil de **supervisión, no de gestión**. Los cuatro paneles son de solo lectura:
no crea, edita ni borra nada, y tampoco cambia estados de pedidos.

```mermaid
flowchart TD
    L(["Inicia sesión<br/>cuenta creada solo por seed"]) --> D["Panel /admin<br/>métricas globales"]
    D --> U["Usuarios<br/>/admin/usuarios"]
    D --> P["Productos<br/>/admin/productos"]
    D --> O["Pedidos<br/>/admin/pedidos"]
    U --> RO["Solo consulta"]
    P --> RO
    O --> RO
    RO --> NOTA["Para modificar datos hay que<br/>entrar con el rol correspondiente"]
```

---

## 6. Qué protege cada flujo

Todo lo anterior se apoya en las mismas defensas, aplicadas en el **controlador**,
nunca en la vista (ocultar un botón es presentación, no seguridad):

| Control | Dónde | Efecto |
|---|---|---|
| CSRF en todo POST | `public/index.php`, antes de enrutar | `419` si el token no coincide |
| `requireAuth()` | Controlador base | Redirige a `/login` |
| `requireRole()` | Cada acción protegida | `403` con `errors/403` |
| `ownedProductOrFail()` | Productos del productor | `404` si no existe, `403` si es ajeno |
| `orderHasMyProduct()` | Pedidos del productor | `403` si el pedido no lleva nada suyo |
| `safeReturnTo()` | Carrito | Impide redirección abierta fuera del sitio |

El diagrama del **flujo interno de una petición** (front controller → router →
controlador → vista) está en
[`modules/README.md`](./modules/README.md#diagrama-del-flujo-de-una-petición).
