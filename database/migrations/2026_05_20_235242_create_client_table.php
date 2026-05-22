<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client', function (Blueprint $table) {
            $table->string('id_client')->primary();
            $table->string('client_name1');
            $table->string('client_name2')->nullable();
            $table->string('client_last_name1');
            $table->string('client_last_name2')->nullable();
            $table->string('business_name')->nullable();
            $table->string('address')->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->string('phone_number')->nullable();
            $table->boolean('status')->default(true);
            $table->string('document_employee')->nullable();
            $table->string('id_departament');
            $table->string('id_city');

            $table->foreign('document_employee')
                  ->references('document_employee')
                  ->on('employee')
                  ->onDelete('set null');

            $table->foreign(['id_departament', 'id_city'])
                  ->references(['id_departament', 'id_city'])
                  ->on('city')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client');
    }
};