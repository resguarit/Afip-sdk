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
        Schema::create('point_of_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afip_configuration_id')->constrained('afip_configurations')->onDelete('cascade');
            $table->integer('number')->comment('Número del punto de venta');
            $table->string('name')->nullable()->comment('Nombre descriptivo del punto de venta');
            $table->date('blocking_date')->nullable()->comment('Fecha de bloqueo (si aplica)');
            $table->boolean('is_active')->default(true)->comment('Indica si el punto de venta está activo');
            $table->text('description')->nullable()->comment('Descripción adicional');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['afip_configuration_id', 'number']);
            $table->index(['is_active', 'afip_configuration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_of_sales');
    }
};

