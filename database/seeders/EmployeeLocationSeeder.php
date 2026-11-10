<?php

namespace Database\Seeders;

use App\Models\EmployeeLocation;
use Illuminate\Database\Seeder;

class EmployeeLocationSeeder extends Seeder
{
    public function run(): void
    {
        // Ubicación de ejemplo en Ocaña para que el mapa del administrador
        // muestre un marcador activo (se reemplaza sola cada 30s con GPS real).
        EmployeeLocation::updateOrCreate(
            ['document_employee' => '1000000002'],
            [
                'latitude' => 8.2340,
                'longitude' => -73.2648,
                'is_active' => true,
                'updated_at' => now(),
            ],
        );
    }
}
