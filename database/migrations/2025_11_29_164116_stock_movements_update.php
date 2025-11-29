<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // 1. Hacemos nuleables las ubicaciones porque:
            // - Compra: from_location_id es NULL
            // - Merma: to_location_id es NULL
            $table->foreignId('from_location_id')->nullable()->change();
            $table->foreignId('to_location_id')->nullable()->change();
            
            // 2. Agregamos el tipo de movimiento
            $table->string('type')->default('transfer')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Revertir cambios (Cuidado con datos existentes NULL)
            $table->foreignId('from_location_id')->nullable(false)->change();
            $table->foreignId('to_location_id')->nullable(false)->change();
            $table->dropColumn('type');
        });
    }
};