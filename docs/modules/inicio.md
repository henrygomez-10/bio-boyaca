# Módulo Inicio

## Propósito

Página de bienvenida (landing) del marketplace. Presenta el nombre y eslogan de la aplicación y una muestra de productos destacados para invitar a explorar el catálogo.

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/` | `HomeController@index` |

Definido en `routes/web.php`.

## Archivos involucrados

- `src/Controllers/HomeController.php`
- `src/Views/home/index.php`
- `src/Views/layouts/main.php` (layout general)
- `src/Repositories/ProductRepository.php`
- `src/Models/Product.php` (formateo de precio: `Product::formatPrice()`)

## Flujo

1. `HomeController::index()` crea un `ProductRepository` a partir de `$this->container->db()`.
2. Toma hasta 6 productos con `array_slice($products->all(), 0, 6)` como "destacados" (no hay lógica de relevancia ni orden explícito: son los primeros 6 del listado tal como los devuelve el driver activo).
3. Renderiza `home/index` con las variables `title` y `featured`.

## Datos / colecciones

- Lee la colección `products` (vía `ProductRepository`), sin filtrar por productor ni categoría.
- Cada producto mostrado usa los campos: `id`, `name`, `category`, `price`.

## Reglas de negocio / validaciones

- No hay validaciones ni control de acceso: la página es pública.
- Si no hay productos (`empty($featured)`), la vista muestra un mensaje de catálogo vacío en vez de las tarjetas.

## Control de acceso

Ninguno. Ruta pública, accesible con o sin sesión iniciada.

## Notas / mejoras futuras

- "Destacados" es simplemente `array_slice(..., 0, 6)`: no hay criterio de negocio (ej. más vendidos, mejor valorados). Podría mejorarse con un campo `featured: bool` o un ranking por ventas.
- El título de la página se arma manualmente concatenando `config('app')['name']` — cuando se defina el nombre final del proyecto (`config/config.php` → `app.name`), este título se actualiza automáticamente sin tocar el controlador.
