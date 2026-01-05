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
    protected $description = 'Suma la parte proporcional semanal de vacaciones basada en los días laborados por semana de cada empleado activo.';

    /**
     * Ejecuta el comando.
     */
    public function handle()
    {
        $this->info("Iniciando acumulación semanal de vacaciones personalizada...");
        
        // Obtenemos todos los empleados activos
        $employees = Employee::where('is_active', true)->get();
        $count = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                // 1. Determinar días trabajados por semana según su plantilla
                // La estructura es tipo: {"monday":2, "tuesday":null, ...}
                $schedule = $employee->default_schedule_template;
                
                $daysWorkedPerWeek = 0;

                if (is_array($schedule)) {
                    // Filtramos el array para quitar los valores nulos (días de descanso)
                    // y contamos cuántos días tienen asignado un turno (valor no nulo)
                    $activeDays = array_filter($schedule, function($val) {
                        return !is_null($val);
                    });
                    $daysWorkedPerWeek = count($activeDays);
                }

                // Si no tiene días registrados, saltamos este empleado
                if ($daysWorkedPerWeek <= 0) {
                    $skipped++;
                    // Opcional: Loguear advertencia solo en modo debug
                    // Log::warning("Empleado {$employee->id} omitido: Sin días laborales configurados.");
                    continue; 
                }

                // 2. Definir la regla de negocio dinámica
                // Días de vacaciones al año = Días trabajados por semana
                $annualDays = $daysWorkedPerWeek;
                
                // Cálculo proporcional semanal
                $weeklyAccrual = $annualDays / 52;

                // 3. Aplicar el ajuste
                // userId = null indica que fue el "Sistema"
                $employee->adjustVacationBalance(
                    $weeklyAccrual, 
                    'accrual', 
                    "Acumulación Semanal (Base {$daysWorkedPerWeek} días/año)", 
                    null 
                );
                
                $this->line("Empleado: {$employee->id} | Días Laborales: {$daysWorkedPerWeek} | Sumado: " . number_format($weeklyAccrual, 5));
                $count++;
            }

            DB::commit();
            
            $this->info("------------------------------------------------");
            $this->info("¡Éxito!");
            $this->info("Empleados procesados: {$count}");
            if ($skipped > 0) {
                $this->warn("Empleados omitidos (sin horario o 0 días trabajados): {$skipped}");
            }
            
            Log::info("Vacaciones semanales acumuladas para {$count} empleados (dinámico según horario).");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Ocurrió un error: " . $e->getMessage());
            Log::error("Error acumulando vacaciones: " . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}