<?php

namespace Database\Seeders;

use App\Models\DeliveryRoute;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    private const VENDOR_1 = '1000000002';

    private const VENDOR_2 = '1000000003';

    /**
     * Asigna 6 de los 8 clientes a los domiciliarios.
     * CLI004 y CLI008 quedan sin ruta para probar POST /routes/distribute
     * y la aprobación de sugerencias.
     *
     * @var array<int, array{id: string, client: string, employee: string}>
     */
    private const ROUTES = [
        ['id' => 'RT000001', 'client' => 'CLI001', 'employee' => self::VENDOR_1],
        ['id' => 'RT000002', 'client' => 'CLI002', 'employee' => self::VENDOR_1],
        ['id' => 'RT000003', 'client' => 'CLI003', 'employee' => self::VENDOR_1],
        ['id' => 'RT000004', 'client' => 'CLI005', 'employee' => self::VENDOR_2],
        ['id' => 'RT000005', 'client' => 'CLI006', 'employee' => self::VENDOR_2],
        ['id' => 'RT000006', 'client' => 'CLI007', 'employee' => self::VENDOR_2],
    ];

    public function run(): void
    {
        foreach (self::ROUTES as $route) {
            DeliveryRoute::updateOrCreate(
                ['id_ruta' => $route['id']],
                [
                    'id_client' => $route['client'],
                    'document_employee' => $route['employee'],
                ],
            );
        }
    }
}
