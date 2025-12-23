<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar saldo de vacaciones a Empleados
        Schema::table('employees', function (Blueprint $table) {
            // Decimal con 4 digitos de precisión para acumulación semanal (0.1153...)
            $table->decimal('vacation_balance', 8, 4)->default(0)->after('base_salary');
        });

        // 2. Historial de movimientos de vacaciones
        Schema::create('vacation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained(); // Quién hizo el movimiento (Admin)
            
            $table->string('type'); // 'usage' (tomó vacaciones), 'accrual' (ganó semana), 'adjustment' (corrección manual)
            $table->decimal('days', 8, 4); // Cantidad (+ o -)
            $table->decimal('balance_before', 8, 4);
            $table->decimal('balance_after', 8, 4);
            $table->string('description')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_logs');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('vacation_balance');
        });
    }
};