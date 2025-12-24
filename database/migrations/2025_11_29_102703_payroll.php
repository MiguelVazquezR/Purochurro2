<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Días Feriados
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->boolean('mandatory_rest')->default(true); // Si es descanso obligatorio oficial
            $table->decimal('pay_multiplier', 3, 1)->default(2.0); // Ej: 2.0 (doble), 3.0 (triple)
            $table->timestamps();
        });

        // 2. Catálogo de Bonos
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: Bono Puntualidad
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->nullable(); // Monto fijo si aplica
            $table->string('type')->default('fixed'); // fixed, percentage, rule_based
            // Guardará la configuración lógica. Ej:
            // { 
            //   "concept": "late_minutes", 
            //   "operator": "<=", 
            //   "value": 15, 
            //   "scope": "period_accumulated",
            //   "behavior": "fixed_amount" 
            // }
            $table->json('rule_config')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // 3. Registro de Asistencias e Incidencias (La realidad del día a día)
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date')->index();
            
            // Tiempos reales (pueden ser nulos si faltó)
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            
            // Incidencia (Enum en código, string en DB)
            // Default 'asistencia' si checó, o se llena con 'falta' si no.
            $table->string('incident_type')->default('asistencia'); 
            
            // Flags administrativas
            $table->boolean('is_late')->default(false); // Calculado automáticamente al checar
            $table->boolean('late_ignored')->default(false); // Admin decide perdonar retardo
            $table->text('admin_notes')->nullable(); // Justificación
            
            // Calculados para nómina (se pueden llenar al cierre de semana)
            $table->decimal('daily_salary_snapshot', 10, 2)->nullable(); // Sueldo de ese día (histórico)
            $table->decimal('extra_hours', 4, 2)->default(0);

            $table->unsignedSmallInteger('commission_amount')->default(0); 

            $table->unique(['employee_id', 'date']);
            $table->timestamps();
        });

        // 4. Asignación de Bonos a Empleados (Pivot para historial)
        Schema::create('employee_bonus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('bonus_id')->constrained();
            $table->date('assigned_date'); // Fecha del periodo
            $table->decimal('amount', 10, 2); // Monto pagado final
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bonus');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('bonuses');
        Schema::dropIfExists('holidays');
    }
};