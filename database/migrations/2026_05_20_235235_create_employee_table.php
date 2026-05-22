<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee', function (Blueprint $table) {
            $table->string('document_employee')->primary();
            $table->string('name_1');
            $table->string('name_2')->nullable();
            $table->string('last_name_1');
            $table->string('last_name_2')->nullable();
            $table->string('phone_number')->nullable();
            $table->char('status', 1)->default('A');
            $table->string('email')->unique();
            $table->string('password');
            $table->char('type', 1);
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->date('hire_date')->nullable();
            $table->char('can_modify_invoice', 1)->default('N');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee');
    }
};