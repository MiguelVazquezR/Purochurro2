<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccrueVacationDays extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'vacation:accrue-weekly';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Suma la parte proporcional semanal de vacaciones (basado en 6 días anuales) a todos los empleados activos.';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        // 1. Definir la regla de negocio
        // 6 días al año / 52 semanas = ~0.11538 días por semana
        $annualDays = 6;
        $weeklyAccrual = $annualDays / 52;

        $this->info("Iniciando acumulación semanal de vacaciones...");
        $this->info("Monto a sumar por empleado: " . number_format($weeklyAccrual, 5) . " días.");

        $employees = Employee::where('is_active', true)->get();
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                // Usamos el método que ya tienes en el modelo Employee
                // userId = null indica que fue el "Sistema" quien hizo la acción
                $employee->adjustVacationBalance(
                    $weeklyAccrual, 
                    'accrual', 
                    'Acumulación Semanal Automática (Sistema)', 
                    null 
                );
                
                $count++;
            }

            DB::commit();
            
            $this->info("¡Éxito! Se actualizaron las vacaciones de {$count} empleados.");
            Log::info("Vacaciones semanales acumuladas para {$count} empleados.");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Ocurrió un error: " . $e->getMessage());
            Log::error("Error acumulando vacaciones: " . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}