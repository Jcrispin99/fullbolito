<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('type');
            $table->boolean('is_fiscal')->default(false);

            $table->string('document_type_code', 2)->nullable();
            // Para notas de crédito/débito: documento al que afectan.
            //   NC factura: document_type_code='07', affects_document_type_code='01'
            //   NC boleta:  document_type_code='07', affects_document_type_code='03'
            // Null en journals que no son notas.
            $table->string('affects_document_type_code', 2)->nullable();

            $table->foreignId('sequence_id')->constrained('sequences');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');

            $table->timestamps();

            $table->unique('code');
            $table->unique(['company_id', 'code']);
            $table->index(['document_type_code']);
            $table->index(['affects_document_type_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
