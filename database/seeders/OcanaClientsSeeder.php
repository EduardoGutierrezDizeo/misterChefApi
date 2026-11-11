<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Client;
use App\Models\Department;
use Illuminate\Database\Seeder;

class OcanaClientsSeeder extends Seeder
{
    /**
     * Clientes reales de Ocaña sin vendedor asignado ni ruta, para probar
     * la distribución automática de rutas por proximidad geográfica.
     */
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

        $path = database_path('seeders/data/clientes_ocana.json');

        if (! is_file($path)) {
            $this->command?->error('No existe el archivo de datos "database/seeders/data/clientes_ocana.json".');

            return;
        }

        $records = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $nextNumber = $this->nextClientNumber();

        foreach ($records as $record) {
            // La columna decimal(9,6) redondea a 6 decimales: normalizamos para
            // que la búsqueda idempotente coincida con lo almacenado.
            $latitude = round((float) $record['latitude'], 6);
            $longitude = round((float) $record['longitude'], 6);

            $client = Client::where('business_name', $record['business_name'])
                ->where('latitude', $latitude)
                ->where('longitude', $longitude)
                ->first();

            $attributes = [
                'client_name1' => $record['client_name1'],
                'client_name2' => null,
                'client_last_name1' => $record['client_last_name1'],
                'client_last_name2' => $record['client_last_name2'] ?? null,
                'business_name' => $record['business_name'],
                'address' => $record['address'],
                'longitude' => $longitude,
                'latitude' => $latitude,
                'phone_number' => $record['phone'] ?? null,
                'status' => true,
                'document_employee' => null,
                'id_departament' => $department->id_departament,
                'id_city' => $city->id_city,
            ];

            if ($client) {
                $client->update($attributes);

                continue;
            }

            Client::create($attributes + [
                'id_client' => 'CLI'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
            ]);

            $nextNumber++;
        }
    }

    /**
     * Continúa la secuencia CLI### usada por ClientSeeder y ClientController.
     */
    private function nextClientNumber(): int
    {
        $ultimo = Client::orderByRaw('CAST(SUBSTRING(id_client, 4) AS UNSIGNED) DESC')->first();

        return $ultimo ? (int) substr($ultimo->id_client, 3) + 1 : 1;
    }
}
