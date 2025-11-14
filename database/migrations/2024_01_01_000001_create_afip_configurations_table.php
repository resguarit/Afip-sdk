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
        Schema::create('afip_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre descriptivo de la configuración');
            $table->string('cuit', 11)->unique()->comment('CUIT del contribuyente');
            $table->enum('environment', ['testing', 'production'])->default('testing')->comment('Entorno de trabajo');
            $table->string('certificate_path')->nullable()->comment('Ruta al archivo de certificado');
            $table->string('key_path')->nullable()->comment('Ruta al archivo de clave privada');
            $table->string('certificate_password')->nullable()->comment('Contraseña del certificado (encriptada)');
            $table->boolean('is_active')->default(true)->comment('Indica si esta configuración está activa');
            $table->text('description')->nullable()->comment('Descripción adicional');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'environment']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afip_configurations');
    }
};

