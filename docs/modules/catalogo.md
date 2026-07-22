# Módulo Catálogo

## Propósito

Listado público de productos con búsqueda por texto y filtro por categoría. Es el punto de entrada hacia el detalle de cada producto y hacia el carrito: cada tarjeta lleva su propio botón "Agregar al carrito".

## Rutas

| Método | URL | Handler |
|---|---|---|
| GET | `/catalogo` | `CatalogController@index` |

Definida en `routes/web.php`. Acepta query params `q` (término de búsqueda) y `categoria`.

## Archivos involucrados

- `src/Controllers/CatalogController.php`
- `src/Views/catalog/index.php`
- `src/Repositories/ProductRepository.php`
- `src/Models/Product.php` (`categories()`, `formatPrice()`)

## Flujo

1. Se leen los parámetros `q` y `categoria` con `$this->input('q', '')` / `$this->input('categoria', '')` (GET tiene prioridad si no hay POST, ver `Controller::input()`).
2. `ProductRepository::search($term)`:
   - Si `$term === ''`, devuelve todos los productos (`all()`).
   - Si no, filtra en memoria por coincidencia de subcadena (`str_contains`) sobre `name . ' ' . description`, en minúsculas y compatible con UTF-8 (`mb_strtolower`).
3. Si `categoria` no está vacía, se aplica un segundo filtro en memoria (`array_filter`) que compara `product['category'] === $category` exactamente.
4. Se renderiza `catalog/index` con `products`, `categories` (todas las categorías posibles vía `Product::categories()`, no solo las usadas), `q` y `category` (para repoblar el formulario).

## Interfaz: chips de categoría y añadir al carrito

- El filtro por categoría se pinta como **chips** (`.chips`/`.chip`, una fila de píldoras con scroll horizontal en móvil) en vez de un `<select>`. Cada chip es un **enlace GET** que **conserva el término de búsqueda activo**; el chip "Todos" limpia el filtro. El chip activo se marca con `.chip--active`.
- Cada tarjeta incluye un formulario `POST /carrito/agregar` con un campo oculto `return_to` que apunta a la URL del catálogo con la búsqueda y el filtro actuales, de modo que al añadir un producto el consumidor **vuelve exactamente donde estaba** (ver `safeReturnTo()` en [`carrito.md`](./carrito.md)).

## Datos / colecciones

- Colección `products`. Campos usados: `id`, `name`, `category`, `price`, `stock`, `description`, `unit`, `origin`, `image`.

## Reglas de negocio / validaciones

- Búsqueda por texto: coincidencia parcial (substring), no distingue mayúsculas/minúsculas, busca en nombre **y** descripción combinados.
- Filtro de categoría: coincidencia exacta contra la lista fija `App\Models\Product::categories()` — 8 categorías cerradas y específicas del proyecto: **Lácteos, Huevos, Carne, Miel, Tubérculos y raíces, Hortalizas, Café, Arepas tradicionales**. Los chips de la vista solo ofrecen esas opciones.
- Búsqueda y filtro se combinan con AND (primero se busca por texto, luego se filtra por categoría sobre el resultado).
- No hay paginación: se muestran todos los resultados coincidentes.

## Control de acceso

Ninguno. Ruta pública.

## Notas / mejoras futuras

- Sin paginación ni límite de resultados; con muchos productos podría requerir `LIMIT`/`OFFSET` o paginación en memoria.
- La búsqueda es O(n) en memoria sobre todos los productos (adecuado para el driver JSON actual, revisar si se migra a un motor con soporte de índices/búsqueda de texto).
- No hay ordenamiento explícito de resultados (se devuelven en el orden del archivo `storage/data/products.json`).
