<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Productos sembrados con el mismo formato de id que autogenera
     * ProductController (P + 4 dígitos), para que los ids siguientes
     * continúen en P0013, P0014, ...
     *
     * @var array<int, array{id: string, name: string, type: string, stock: int, min: int, price: float}>
     */
    private const PRODUCTS = [
        ['id' => 'P0001', 'name' => 'Sal refinada 1kg',       'type' => 'Condimentos',  'stock' => 120, 'min' => 30, 'price' => 3500],
        ['id' => 'P0002', 'name' => 'Pimienta molida 200g',   'type' => 'Condimentos',  'stock' => 60,  'min' => 15, 'price' => 8900],
        ['id' => 'P0003', 'name' => 'Comino molido 100g',     'type' => 'Condimentos',  'stock' => 45,  'min' => 12, 'price' => 6500],
        ['id' => 'P0004', 'name' => 'Arroz 500g',             'type' => 'Cereales',     'stock' => 200, 'min' => 50, 'price' => 2800],
        ['id' => 'P0005', 'name' => 'Avena 500g',             'type' => 'Cereales',     'stock' => 80,  'min' => 20, 'price' => 5200],
        ['id' => 'P0006', 'name' => 'Maíz pira 500g',         'type' => 'Cereales',     'stock' => 70,  'min' => 15, 'price' => 4100],
        ['id' => 'P0007', 'name' => 'Cacahuetes 250g',        'type' => 'Frutos secos', 'stock' => 55,  'min' => 15, 'price' => 7500],
        ['id' => 'P0008', 'name' => 'Almendras 200g',         'type' => 'Frutos secos', 'stock' => 30,  'min' => 10, 'price' => 18500],
        ['id' => 'P0009', 'name' => 'Nueces 200g',            'type' => 'Frutos secos', 'stock' => 4,   'min' => 10, 'price' => 16900],
        ['id' => 'P0010', 'name' => 'Salsa de tomate 500ml',  'type' => 'Salsas',       'stock' => 90,  'min' => 25, 'price' => 6800],
        ['id' => 'P0011', 'name' => 'Salsa de soya 300ml',    'type' => 'Salsas',       'stock' => 75,  'min' => 20, 'price' => 5900],
        ['id' => 'P0012', 'name' => 'Ají picante 250ml',      'type' => 'Salsas',       'stock' => 65,  'min' => 15, 'price' => 5400],
    ];

    public function run(): void
    {
        $typeIds = ProductType::pluck('id_produc_type', 'type');

        foreach (self::PRODUCTS as $product) {
            $idType = $typeIds[$product['type']] ?? null;

            if (! $idType) {
                $this->command?->warn("Tipo de producto no encontrado: {$product['type']} — ejecuta ProductTypeSeeder primero.");

                continue;
            }

            Product::updateOrCreate(
                ['id_product' => $product['id']],
                [
                    'product_name' => $product['name'],
                    'stock' => $product['stock'],
                    'minimun_stock' => $product['min'],
                    'selling_price' => $product['price'],
                    'status' => true,
                    'id_produc_type' => $idType,
                ],
            );
        }
    }
}
