<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->string('id_product')->primary();
            $table->string('product_name');
            $table->integer('stock')->default(0);
            $table->integer('minimun_stock')->default(0);
            $table->decimal('selling_price', 10, 2);
            $table->boolean('status')->default(true);
            $table->string('id_produc_type');

            $table->foreign('id_produc_type')
                  ->references('id_produc_type')
                  ->on('product_type')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};