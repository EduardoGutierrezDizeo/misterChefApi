<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Ciudades principales por departamento (capital y municipios destacados).
     *
     * @var array<string, array<int, string>>
     */
    private const CITIES = [
        'Amazonas' => ['Leticia', 'Puerto Nariño', 'El Encanto', 'La Pedrera', 'Mirití-Paraná', 'Puerto Alegría'],
        'Antioquia' => ['Medellín', 'Bello', 'Itagüí', 'Envigado', 'Rionegro', 'Turbo', 'Barbosa', 'Girardota', 'Copacabana', 'Caucasia'],
        'Arauca' => ['Arauca', 'Tame', 'Fortul', 'Pavia', 'Saravena', 'Cravo Norte'],
        'Atlántico' => ['Barranquilla', 'Soledad', 'Malambo', 'Sabanalarga', 'Puerto Colombia', 'Galapa', 'Tubará'],
        'Bogotá D.C.' => ['Bogotá'],
        'Bolívar' => ['Cartagena', 'Magangué', 'Turbaco', 'Arjona', 'María La Baja', 'San Juan Nepomuceno', 'Carmen de Bolívar'],
        'Boyacá' => ['Tunja', 'Duitama', 'Sogamoso', 'Chiquinquirá', 'Paipa', 'Villa de Leyva', 'Nobsa'],
        'Caldas' => ['Manizales', 'La Dorada', 'Chinchiná', 'Villamaría', 'Riosucio', 'Supia'],
        'Caquetá' => ['Florencia', 'San Vicente del Caguán', 'La Montañita', 'Curillo', 'El Paujil', 'Cartagena del Chairá'],
        'Casanare' => ['Yopal', 'Aguazul', 'Villanueva', 'Tauramena', 'Maní', 'Orocué'],
        'Cauca' => ['Popayán', 'Santander de Quilichao', 'Puerto Tejada', 'Guachené', 'Inzá', 'Silvia'],
        'Cesar' => ['Valledupar', 'Aguachica', 'Codazzi', 'Bosconia', 'Curumaní', 'La Paz'],
        'Chocó' => ['Quibdó', 'Istmina', 'Turbo', 'Riosucio', 'Atrato', 'Nuquí'],
        'Córdoba' => ['Montería', 'Cereté', 'Sahagún', 'Lorica', 'Tierralta', 'Puerto Libertador'],
        'Cundinamarca' => ['Soacha', 'Fusagasugá', 'Facatativá', 'Zipaquirá', 'Chía', 'Barbosa', 'Girardot', 'Madrid', 'Mosquera', 'Ubaté'],
        'Guainía' => ['Inírida', 'Barrancominas', 'Mapiripana', 'San Felipe'],
        'Guaviare' => ['San José del Guaviare', 'Calamar', 'El Retorno', 'Miraflores'],
        'Huila' => ['Neiva', 'Pitalito', 'Garzón', 'La Plata', 'Campoalegre', 'Rivera'],
        'La Guajira' => ['Riohacha', 'Maicao', 'San Juan del Cesar', 'Albania', 'Barrancas', 'Fonseca', 'Dibulla'],
        'Magdalena' => ['Santa Marta', 'Fundación', 'Ciénaga', 'Aracataca', 'El Banco', 'Plato', 'Pivijay'],
        'Meta' => ['Villavicencio', 'Acacías', 'Puerto López', 'Granada', 'San Martín', 'Puerto Gaitán', 'Cabuyaro'],
        'Nariño' => ['Pasto', 'Tumaco', 'Ipiales', 'La Cruz', 'Túquerres', 'Buesaco', 'Sandoná'],
        'Norte de Santander' => [
            'Cúcuta',
            'Ocaña',
            'Tibú',
            'Pamplona',
            'Villacaró',
            'Chinácota',
            'San Calixto',
            'Hacarí',
            'La Esperanza',
            'Sardinata',
            'Teorama',
            'Convención',
            'El Tarra',
            'San Martín',
            'Mutiscua',
            'Labateca',
            'Toledo',
            'Durania',
        ],
        'Quindío' => ['Armenia', 'Montenegro', 'Quimbaya', 'Filandia', 'Salento', 'Circasia'],
        'Risaralda' => ['Pereira', 'Dos Quebradas', 'La Virginia', 'Santa Rosa de Cabal', 'Mistrató', 'Belén de Umbría'],
        'San Andrés y Providencia' => ['San Andrés', 'Providencia'],
        'Santander' => ['Bucaramanga', 'Floridablanca', 'Girón', 'Piedecuesta', 'Barrancabermeja', 'San Gil', 'Socorro', 'Barichara', 'Landázuri'],
        'Sucre' => ['Sincelejo', 'Corozal', 'Tolú', 'San Marcos', 'Ovejas', 'La Unión'],
        'Tolima' => ['Ibagué', 'Espinal', 'Melgar', 'Mariquita', 'Honda', 'Líbano', 'Cajamarca'],
        'Valle del Cauca' => ['Cali', 'Palmira', 'Buenaventura', 'Tuluá', 'Jamundí', 'Yumbo', 'Buga', 'Pradera', 'Florida'],
        'Vaupés' => ['Mitú', 'Carurú', 'Pacoa', 'Taraira', 'Papunaua'],
        'Vichada' => ['Puerto Carreño', 'La Primavera', 'Santa Rosalía', 'Cumaribo'],
    ];

    public function run(): void
    {
        $sequence = 1;

        foreach (self::CITIES as $departmentName => $cities) {
            $department = Department::where('name_departament', $departmentName)->first();

            if (! $department) {
                $this->command?->warn("Departamento no encontrado: {$departmentName} — ejecuta DepartmentSeeder primero.");

                continue;
            }

            foreach ($cities as $cityName) {
                City::updateOrCreate(
                    ['id_departament' => $department->id_departament, 'name_city' => $cityName],
                    ['id_city' => sprintf('C%03d', $sequence)],
                );

                $sequence++;
            }
        }
    }
}
