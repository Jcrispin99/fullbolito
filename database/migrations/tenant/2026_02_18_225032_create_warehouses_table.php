<?php

declare(strict_types=1);

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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            // Datos legales SUNAT (GRE): ubigeo INEI 6 dígitos, dirección formal,
            // y código de establecimiento (4 dígitos del anexo en ficha RUC).
            $table->string('ubigeo', 6)->nullable();
            $table->string('address_line')->nullable();
            $table->string('establishment_code', 4)->default('0000');
            $table->boolean('is_active')->default(true);
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
