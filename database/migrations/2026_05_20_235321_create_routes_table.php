<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->string('id_ruta')->primary();
            $table->string('id_client');
            $table->string('document_employee');

            $table->foreign('id_client')
                  ->references('id_client')
                  ->on('client')
                  ->onDelete('cascade');

            $table->foreign('document_employee')
                  ->references('document_employee')
                  ->on('employee')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};