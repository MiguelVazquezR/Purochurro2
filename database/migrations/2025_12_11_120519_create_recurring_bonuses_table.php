<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla para bonos recurrentes (Configuración permanente del empleado)
        Schema::create('recurring_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('bonus_id')->constrained()->onDelete('cascade');
            
            // Permite personalizar el monto para este empleado específico
            // Si es null, se usa el monto default del catálogo de bonos
            $table->decimal('amount', 10, 2)->nullable(); 
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Evitar duplicados
            $table->unique(['employee_id', 'bonus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_bonuses');
    }
};