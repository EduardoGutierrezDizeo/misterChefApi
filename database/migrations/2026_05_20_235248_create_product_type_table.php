<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_type', function (Blueprint $table) {
            $table->string('id_produc_type')->primary();
            $table->string('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_type');
    }
};