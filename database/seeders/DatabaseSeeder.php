<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            CitySeeder::class,
            ProductTypeSeeder::class,
            EmployeeSeeder::class,
            ProductSeeder::class,
            ClientSeeder::class,
            InvoiceSeeder::class,
            RouteSeeder::class,
            RouteSuggestionSeeder::class,
            EmployeeLocationSeeder::class,
        ]);
    }
}
