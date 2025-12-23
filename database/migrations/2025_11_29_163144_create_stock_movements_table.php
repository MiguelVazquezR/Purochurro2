<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            
            // Qué se movió
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // De dónde a dónde
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            
            // Cuánto
            $table->decimal('quantity');
            
            // Auditoría
            $table->foreignId('user_id')->constrained(); // El usuario logueado (quien registra)
            // Opcional: Si quieres registrar qué empleado físico hizo el movimiento si es diferente al usuario del sistema
            // $table->foreignId('employee_id')->nullable()->constrained(); 
            
            $table->text('notes')->nullable(); // Ej: "Surtido inicial del día"
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};