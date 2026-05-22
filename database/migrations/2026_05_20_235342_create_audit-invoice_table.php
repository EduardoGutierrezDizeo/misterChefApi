<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_invoice', function (Blueprint $table) {
            $table->string('id_audit', 10)->primary();
            $table->string('id_invoice', 5);
            $table->string('document_employee', 15);
            $table->char('action_type', 1);
            $table->timestamp('action_date')->useCurrent();
            $table->char('previous_status', 1)->nullable();
            $table->char('new_status', 1)->nullable();
            $table->decimal('previous_total', 8, 2)->nullable();
            $table->decimal('new_total', 8, 2)->nullable();

            $table->foreign('id_invoice')
                  ->references('id_invoice')
                  ->on('invoice')
                  ->onDelete('restrict');

            $table->foreign('document_employee')
                  ->references('document_employee')
                  ->on('employee')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_invoice');
    }
};