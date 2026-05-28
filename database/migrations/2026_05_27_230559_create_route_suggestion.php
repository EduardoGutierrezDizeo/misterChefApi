<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_suggestion', function (Blueprint $table) {
            $table->string('id_suggestion', 10)->primary();
            $table->string('id_client');
            $table->string('document_employee');
            $table->char('status', 1)->default('P'); // P=Pendiente, A=Aprobada, R=Rechazada
            $table->decimal('distance_km', 8, 3);    // distancia del cliente al domiciliario más cercano
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable(); // document_employee del admin que resolvió

            $table->foreign('id_client')
                  ->references('id_client')
                  ->on('client')
                  ->onDelete('cascade');

            $table->foreign('document_employee')
                  ->references('document_employee')
                  ->on('employee')
                  ->onDelete('cascade');

            $table->foreign('resolved_by')
                  ->references('document_employee')
                  ->on('employee')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_suggestion');
    }
};