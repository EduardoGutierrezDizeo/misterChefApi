<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Client;
use App\Models\Department;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    private const VENDOR_1 = '1000000002';

    private const VENDOR_2 = '1000000003';

    public function run(): void
    {
        $department = Department::where('name_departament', 'Norte de Santander')->first();

        if (! $department) {
            $this->command?->error('No existe el departamento "Norte de Santander" — ejecuta DepartmentSeeder primero.');

            return;
        }

        $city = City::where('id_departament', $department->id_departament)
            ->where('name_city', 'Ocaña')
            ->first();

        if (! $city) {
            $this->command?->error('No existe la ciudad "Ocaña" — ejecuta CitySeeder primero.');

            return;
        }

        $clients = [
            [
                'id_client' => 'CLI001',
                'client_name1' => 'María',
                'client_name2' => 'Patricia',
                'client_last_name1' => 'Gómez',
                'client_last_name2' => 'Rivas',
                'business_name' => 'Market El Barrio',
                'address' => 'Cra 9 #12-45, Ocaña',
                'longitude' => -73.2610,
                'latitude' => 8.2412,
                'phone_number' => '3001112233',
                'status' => true,
                'document_employee' => self::VENDOR_1,
            ],
            [
                'id_client' => 'CLI002',
                'client_name1' => 'Carlos',
                'client_name2' => 'Andrés',
                'client_last_name1' => 'Pérez',
                'client_last_name2' => 'Manrique',
                'business_name' => 'Restaurante Sabor',
                'address' => 'Cra 10 # 15-22, Ocaña',
                'longitude' => -73.2575,
                'latitude' => 8.2298,
                'phone_number' => '3002223344',
                'status' => true,
                'document_employee' => self::VENDOR_1,
            ],
            [
                'id_client' => 'CLI003',
                'client_name1' => 'Lucía',
                'client_name2' => null,
                'client_last_name1' => 'Hernández',
                'client_last_name2' => 'Díaz',
                'business_name' => 'Café Central',
                'address' => 'Cll 14 # 8-19, Ocaña',
                'longitude' => -73.2701,
                'latitude' => 8.2365,
                'phone_number' => '3003334455',
                'status' => true,
                'document_employee' => self::VENDOR_1,
            ],
            [
                'id_client' => 'CLI004',
                'client_name1' => 'José',
                'client_name2' => 'David',
                'client_last_name1' => 'Ramírez',
                'client_last_name2' => 'Ortega',
                'business_name' => 'Distribuidora El Progreso',
                'address' => 'Av 15 # 21-08, Ocaña',
                'longitude' => -73.2480,
                'latitude' => 8.2180,
                'phone_number' => '3004445566',
                'status' => true,
                'document_employee' => self::VENDOR_1,
            ],
            [
                'id_client' => 'CLI005',
                'client_name1' => 'Ana',
                'client_name2' => 'Victoria',
                'client_last_name1' => 'Torres',
                'client_last_name2' => 'Pineda',
                'business_name' => 'Panadería Dulce Hogar',
                'address' => 'Cll 20 # 5-16, Ocaña',
                'longitude' => -73.2530,
                'latitude' => 8.2450,
                'phone_number' => '3005556677',
                'status' => true,
                'document_employee' => self::VENDOR_2,
            ],
            [
                'id_client' => 'CLI006',
                'client_name1' => 'Pedro',
                'client_name2' => null,
                'client_last_name1' => 'Díaz',
                'client_last_name2' => 'Fuentes',
                'business_name' => 'Miscelánea Los Pinos',
                'address' => 'Cra 3 # 18-74, Ocaña',
                'longitude' => -73.2740,
                'latitude' => 8.2245,
                'phone_number' => '3006667788',
                'status' => true,
                'document_employee' => self::VENDOR_2,
            ],
            [
                'id_client' => 'CLI007',
                'client_name1' => 'Sandra',
                'client_name2' => 'Mileena',
                'client_last_name1' => 'Vega',
                'client_last_name2' => 'Cifuentes',
                'business_name' => 'Comercial La 14',
                'address' => 'Cll 14 # 11-33, Ocaña',
                'longitude' => -73.2450,
                'latitude' => 8.2390,
                'phone_number' => '3007778899',
                'status' => true,
                'document_employee' => self::VENDOR_2,
            ],
            [
                'id_client' => 'CLI008',
                'client_name1' => 'Luis',
                'client_name2' => 'Alberto',
                'client_last_name1' => 'Mendoza',
                'client_last_name2' => 'Palencia',
                'business_name' => 'Tienda Don Luis',
                'address' => 'Cra 7 # 25-41, Ocaña',
                'longitude' => -73.2620,
                'latitude' => 8.2150,
                'phone_number' => '3008889900',
                'status' => true,
                'document_employee' => self::VENDOR_2,
            ],
        ];

        foreach ($clients as $client) {
            Client::updateOrCreate(
                ['id_client' => $client['id_client']],
                $client + [
                    'id_departament' => $department->id_departament,
                    'id_city' => $city->id_city,
                ],
            );
        }
    }
}
