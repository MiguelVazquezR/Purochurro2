<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Catálogo de Turnos (Ej: Matutino, Vespertino, Cocina)
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: "Matutino A", "Vespertino B"
            $table->time('start_time');
            $table->time('end_time');
            $table->string('color', 7)->default('#3B82F6'); // Para mostrar en el calendario visual (Hex)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Asignación de Horarios (Día a día)
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->nullable()->constrained()->onDelete('set null'); 
            // Si shift_id es NULL, se considera día de descanso.
            
            $table->date('date')->index(); // Indice para búsquedas rápidas por rango
            
            $table->text('notes')->nullable(); // Ej: "Cambio de turno aprobado por Gerente"
            $table->boolean('is_published')->default(false); // Para borradores de horarios antes de avisar al empleado
            
            // Un empleado solo puede tener un registro de horario por día
            $table->unique(['employee_id', 'date']);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('shifts');
    }
};