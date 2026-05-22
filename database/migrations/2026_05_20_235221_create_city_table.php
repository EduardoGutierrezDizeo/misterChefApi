<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city', function (Blueprint $table) {
            $table->string('id_city');
            $table->string('name_city');
            $table->string('id_departament');

            $table->primary(['id_departament', 'id_city']);

            $table->foreign('id_departament')
                  ->references('id_departament')
                  ->on('department')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city');
    }
};