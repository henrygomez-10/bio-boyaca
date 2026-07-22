# Módulo Panel de Administración

## Propósito

Panel básico de solo-consulta para el rol `admin`: métricas generales del sistema y listados completos de usuarios, productos y pedidos. No incluye edición ni eliminación (CRUD) desde este módulo.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/admin` | `AdminController@dashboard` |
| GET | `/admin/usuarios` | `AdminController@users` |
| GET | `/admin/productos` | `AdminController@products` |
| GET | `/admin/pedidos` | `AdminController@orders` |

Definidas en `routes/web.php`.

## Archivos involucrados

- `src/Controllers/AdminController.php`
- `src/Views/admin/dashboard.php`, `admin/users.php`, `admin/products.php`, `admin/orders.php`
- `src/Repositories/UserRepository.php`, `ProductRepository.php`, `OrderRepository.php`
- `src/Models/Order.php` (`statuses()`, para etiquetar el estado en el listado de pedidos)

## Datos / colecciones

- `dashboard`: calcula métricas a partir de las tres colecciones:
  - `usuarios`: `UserRepository::count()` (total de documentos en `users`).
  - `productores`: `count(UserRepository::ofRole('producer'))`.
  - `consumidores`: `count(UserRepository::ofRole('consumer'))`.
  - `productos`: `ProductRepository::count()`.
  - `pedidos`: `OrderRepository::count()`.
- `users`: listado completo (`UserRepository::all()`), sin paginación.
- `products`: listado completo (`ProductRepository::all()`), sin paginación.
- `orders`: listado completo (`OrderRepository::all()`), con `statuses` para mostrar la etiqueta legible de cada estado.

## Reglas de negocio / validaciones

- No hay formularios ni acciones de escritura en este módulo: es puramente de consulta (dashboard + 3 listados).
- `count()` (heredado de `BaseRepository`) se implementa como `count($this->all())`, es decir, siempre carga todos los documentos de la colección para contarlos (no hay un `count` optimizado a nivel de driver).

## Control de acceso

Las cuatro acciones llaman `requireRole('admin')`: exige sesión iniciada y rol `admin`. El registro público no permite crear usuarios `admin` (ver `docs/modules/autenticacion.md`); se crean manualmente (ver `scripts/seed.php`, que crea `admin@demo.test`).

## Notas / mejoras futuras

- No hay acciones de gestión (banear usuario, editar/eliminar producto o pedido ajeno, cambiar rol) — solo visualización.
- Sin paginación ni filtros/búsqueda en los listados; con datasets grandes convendría añadirlos.
- El cálculo de métricas recorre colecciones completas en cada request (aceptable para el volumen de datos del driver JSON; revisar si se requiere cachear al escalar).
