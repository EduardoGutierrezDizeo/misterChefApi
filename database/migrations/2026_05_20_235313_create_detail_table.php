<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail', function (Blueprint $table) {
            $table->string('line_number');
            $table->decimal('amount', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('id_product');
            $table->string('id_invoice');

            $table->primary(['line_number', 'id_invoice']);

            $table->foreign('id_invoice')
                  ->references('id_invoice')
                  ->on('invoice')
                  ->onDelete('cascade');

            $table->foreign('id_product')
                  ->references('id_product')
                  ->on('product')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail');
    }
};