<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department', function (Blueprint $table) {
            $table->string('id_departament')->primary();
            $table->string('name_departament');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department');
    }
};