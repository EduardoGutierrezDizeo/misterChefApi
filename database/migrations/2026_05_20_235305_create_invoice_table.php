<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice', function (Blueprint $table) {
            $table->string('id_invoice')->primary();
            $table->date('date');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status');
            $table->string('id_client');

            $table->foreign('id_client')
                  ->references('id_client')
                  ->on('client')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice');
    }
};