<?php
/**
 * src/Models/Product.php
 * -----------------------------------------------------------------------------
 * Modelo de dominio para productos. Aporta el catálogo de categorías, las
 * unidades de medida, los municipios de origen y algunas utilidades de
 * presentación/validación reutilizables.
 *
 * El catálogo está enfocado en productos campesinos de Boyacá que se venden en
 * Bogotá, por eso las categorías son cerradas y específicas del proyecto.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Models;

final class Product
{
    /** @return array<int,string> Categorías disponibles del catálogo. */
    public static function categories(): array
    {
        return [
            'Lácteos',
            'Huevos',
            'Carne',
            'Miel',
            'Tubérculos y raíces',
            'Hortalizas',
            'Café',
            'Arepas tradicionales',
        ];
    }

    /**
     * Texto de ayuda por categoría. Se muestra bajo el selector en el formulario
     * del productor para que sepa qué entra en cada una.
     *
     * @return array<string,string>
     */
    public static function categoryHints(): array
    {
        return [
            'Lácteos'              => 'Cuajada, quesos, mantequilla y otros derivados',
            'Huevos'               => 'De gallina, pato o pavo',
            'Carne'                => 'Pollo, gallina campesina, pato o pavo',
            'Miel'                 => 'De abejas o de caña',
            'Tubérculos y raíces'  => 'Malanga, cubios, sagú',
            'Hortalizas'           => 'Hortalizas frescas de la región',
            'Café'                 => 'Café en grano o molido',
            'Arepas tradicionales' => 'Arepas de maíz elaboradas a mano',
        ];
    }

    public static function categoryHint(string $category): string
    {
        return self::categoryHints()[$category] ?? '';
    }

    /**
     * Unidades de medida en las que se puede vender un producto. El productor
     * elige una al publicar; se muestra junto al stock y en el carrito.
     *
     * @return array<int,string>
     */
    public static function units(): array
    {
        return [
            'Unidad',
            'Libra',
            'Kilogramo',
            'Arroba',
            'Docena',
            'Botella',
            'Litro',
            'Bolsa',
            'Paquete',
        ];
    }

    /**
     * Municipios de Boyacá disponibles como origen del producto. Es una lista
     * cerrada para mantener consistentes los datos de procedencia.
     *
     * @return array<int,string>
     */
    public static function origins(): array
    {
        return [
            'Aquitania',
            'Belén',
            'Boyacá',
            'Chinavita',
            'Chiquinquirá',
            'Duitama',
            'Firavitoba',
            'Garagoa',
            'Guateque',
            'Jenesano',
            'La Capilla',
            'Macanal',
            'Miraflores',
            'Moniquirá',
            'Nobsa',
            'Nuevo Colón',
            'Pachavita',
            'Paipa',
            'Ramiriquí',
            'Samacá',
            'Santa Rosa de Viterbo',
            'Soatá',
            'Sogamoso',
            'Somondoco',
            'Sutatenza',
            'Tenza',
            'Tibaná',
            'Tibasosa',
            'Tota',
            'Tunja',
            'Turmequé',
            'Úmbita',
            'Ventaquemada',
            'Villa de Leyva',
        ];
    }

    /** Formatea un precio como moneda simple. */
    public static function formatPrice(float|int|string $price): string
    {
        return '$' . number_format((float) $price, 0, ',', '.');
    }

    /**
     * Devuelve un "slug" estable de la categoría, sin acentos ni espacios, para
     * usarlo como gancho de estilos (ej. atributo data-cat en las vistas). El CSS
     * asocia cada slug a un matiz dentro de la identidad verde de la marca.
     */
    public static function categorySlug(string $category): string
    {
        $map = [
            'Lácteos'              => 'lacteos',
            'Huevos'               => 'huevos',
            'Carne'                => 'carne',
            'Miel'                 => 'miel',
            'Tubérculos y raíces'  => 'tuberculos',
            'Hortalizas'           => 'hortalizas',
            'Café'                 => 'cafe',
            'Arepas tradicionales' => 'arepas',
        ];
        return $map[$category] ?? 'otros';
    }
}
