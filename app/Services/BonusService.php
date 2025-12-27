<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BonusService
{
    /**
     * Calcula los bonos recurrentes para un empleado basado en las estadísticas del periodo.
     *
     * @param Employee $employee
     * @param array $periodStats Estadísticas acumuladas (attendance_days, late_minutes, etc.)
     * @param array $dailyStats Estadísticas desglosadas por día
     * @return array Estructura ['total_amount' => float, 'details' => array]
     */
    public function calculate(Employee $employee, array $periodStats, array $dailyStats): array
    {
        // Obtener bonos recurrentes activos
        $activeBonuses = $employee->recurringBonuses()
            ->where('bonuses.is_active', true)
            ->wherePivot('is_active', true)
            ->get();

        $totalAmount = 0;
        $details = [];
        
        // Extraemos días trabajados del periodo para uso global
        $workedDays = (float) ($periodStats['attendance_days'] ?? 0);

        foreach ($activeBonuses as $bonus) {
            $baseAmount = (float) ($bonus->pivot->amount ?? $bonus->amount);
            $config = $bonus->rule_config;
            
            // --- CASO 1: Bono Fijo (Sin reglas condicionales) ---
            if (empty($config)) {
                $totalAmount += $baseAmount;
                $details[] = [
                    'name' => $bonus->name,
                    'amount' => $baseAmount,
                    'type' => 'recurring_fixed',
                    'description' => $bonus->description ?? 'Bono fijo recurrente'
                ];
                continue;
            }

            // --- CASO 2: Bono Condicional (Basado en Reglas) ---
            $bonusPay = 0;
            $scope = $config['scope'] ?? 'period_total';
            $concept = $config['concept'] ?? '';
            
            $behavior = $config['behavior'] ?? 'fixed_amount';
            // Forzar pago por unidad para minutos extra si no se especificó lo contrario
            if ($concept === 'extra_minutes' && $behavior !== 'fixed_amount') {
                $behavior = 'pay_per_unit';
            }

            // A. Evaluación Diaria
            if ($scope === 'daily') {
                foreach ($dailyStats as $date => $stat) {
                    if ($concept === 'attendance' && empty($stat['is_attendance'])) continue;
                    if (($concept === 'late_minutes' || $concept === 'extra_minutes') && empty($stat['is_attendance'])) continue;

                    $value = $this->extractValue($concept, $stat);
                    
                    if ($this->checkRule($value, $config['operator'], $config['value'])) {
                        // En scope daily, "días trabajados" es 1 si asistió ese día específico
                        $dayWorked = $stat['is_attendance'] ? 1 : 0;
                        
                        $bonusPay += $this->calculateAmount(
                            $behavior,
                            $baseAmount, 
                            $value, 
                            $config['value'], 
                            $config['operator'],
                            $dayWorked 
                        );
                    }
                }
            } 
            // B. Evaluación Global del Periodo
            else {
                $value = $this->extractValue($concept, $periodStats);
                
                if ($this->checkRule($value, $config['operator'], $config['value'])) {
                    $bonusPay += $this->calculateAmount(
                        $behavior,
                        $baseAmount, 
                        $value, 
                        $config['value'], 
                        $config['operator'],
                        $workedDays // Pasamos el total de días trabajados en el periodo
                    );
                }
            }

            // Si se generó monto, lo agregamos al total y al detalle
            if ($bonusPay > 0) {
                $totalAmount += $bonusPay;
                $details[] = [
                    'name' => $bonus->name,
                    'amount' => round($bonusPay, 2),
                    'type' => 'recurring_rule',
                    'meta' => [
                        'concept' => $concept,
                        'scope' => $scope,
                        'behavior' => $behavior
                    ]
                ];
            }
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'details' => $details
        ];
    }

    private function extractValue(string $concept, array $data): float
    {
        return match($concept) {
            'late_minutes' => (float) ($data['late_minutes'] ?? 0),
            'extra_minutes' => (float) ($data['extra_minutes'] ?? 0),
            'unjustified_absences' => (float) ($data['unjustified_absences'] ?? 0),
            'attendance' => isset($data['attendance_days']) 
                ? (float) $data['attendance_days'] 
                : (float) ($data['is_attendance'] ? 1 : 0),
            default => 0.0
        };
    }

    /**
     * Calcula el monto final considerando el comportamiento.
     * * @param string $behavior Tipo de pago (fixed_amount, pay_per_unit, per_day_worked)
     * @param float $baseAmount Monto base definido en el bono
     * @param float $actualValue Valor real de la métrica evaluada (ej. 10 minutos tarde)
     * @param float $targetValue Valor objetivo de la regla (ej. 15 minutos)
     * @param string $operator Operador de comparación
     * @param float $workedDays Días trabajados en el contexto (1 para diario, N para periodo)
     */
    private function calculateAmount(
        string $behavior, 
        float $baseAmount, 
        float $actualValue, 
        float $targetValue, 
        string $operator,
        float $workedDays = 0
    ): float {
        
        // Opción A: Monto fijo único si se cumple la regla
        // Ej: $500 totales si retardos < 15 min en la semana
        if ($behavior === 'fixed_amount') {
            return $baseAmount;
        }
        
        // Opción B: Pago por unidad de la métrica evaluada
        // Ej: $10 por cada minuto extra.
        if ($behavior === 'pay_per_unit') {
            if ($operator === '>') {
                $units = max(0, $actualValue - $targetValue);
                return $units * $baseAmount;
            }
            return $actualValue * $baseAmount;
        }

        // Opción C: Pago por día trabajado si se cumple la condición
        // Ej: Si retardos < 15 min en la semana, paga $50 por cada día asistido ($50 * 6 = $300)
        if ($behavior === 'per_day_worked') {
            return $baseAmount * $workedDays;
        }

        return 0.0;
    }

    private function checkRule($actual, $operator, $target): bool
    {
        $actual = (float)$actual;
        $target = (float)$target;

        return match($operator) {
            '=' => $actual == $target,
            '>' => $actual > $target,
            '<' => $actual < $target,
            '>=' => $actual >= $target,
            '<=' => $actual <= $target,
            default => false
        };
    }
}