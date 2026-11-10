<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Departamentos de Colombia — ids D001..D032 (orden alfabético).
     *
     * @var array<int, string>
     */
    private const DEPARTMENTS = [
        'Amazonas',
        'Antioquia',
        'Arauca',
        'Atlántico',
        'Bogotá D.C.',
        'Bolívar',
        'Boyacá',
        'Caldas',
        'Caquetá',
        'Casanare',
        'Cauca',
        'Cesar',
        'Chocó',
        'Córdoba',
        'Cundinamarca',
        'Guainía',
        'Guaviare',
        'Huila',
        'La Guajira',
        'Magdalena',
        'Meta',
        'Nariño',
        'Norte de Santander',
        'Quindío',
        'Risaralda',
        'San Andrés y Providencia',
        'Santander',
        'Sucre',
        'Tolima',
        'Valle del Cauca',
        'Vaupés',
        'Vichada',
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $index => $name) {
            Department::updateOrCreate(
                ['id_departament' => sprintf('D%03d', $index + 1)],
                ['name_departament' => $name],
            );
        }
    }
}
