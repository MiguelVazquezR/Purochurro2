<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            
            // Periodo del recibo
            $table->date('start_date');
            $table->date('end_date');
            
            // Totales congelados (Snapshot)
            // Guardamos el sueldo base que tenía el empleado EN ESE MOMENTO
            $table->decimal('base_salary_snapshot', 10, 2); 
            
            $table->decimal('total_pay', 10, 2);
            $table->integer('days_worked');
            $table->decimal('total_bonuses', 10, 2);
            
            // Guardamos todo el array de 'breakdown' que genera el servicio
            // Esto nos permite generar el PDF exacto meses después.
            $table->json('breakdown_data'); 
            
            $table->timestamp('paid_at')->nullable(); // Fecha real de pago/transferencia
            
            $table->timestamps();
            
            // Evitar duplicar recibos para el mismo periodo
            $table->unique(['employee_id', 'start_date', 'end_date'], 'receipt_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_receipts');
    }
};