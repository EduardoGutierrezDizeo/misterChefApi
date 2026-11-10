<?php

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const TYPES = [
        'Condimentos',
        'Cereales',
        'Frutos secos',
        'Salsas',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $index => $type) {
            ProductType::updateOrCreate(
                ['id_produc_type' => sprintf('PT%03d', $index + 1)],
                ['type' => $type],
            );
        }
    }
}
