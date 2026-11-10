<?php

namespace Database\Seeders;

use App\Models\RouteSuggestion;
use Illuminate\Database\Seeder;

class RouteSuggestionSeeder extends Seeder
{
    public function run(): void
    {
        RouteSuggestion::updateOrCreate(
            ['id_suggestion' => 'SG00000001'],
            [
                'id_client' => 'CLI004',
                'document_employee' => '1000000002',
                'status' => 'P',
                'distance_km' => 2.350,
                'created_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        );
    }
}
