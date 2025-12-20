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
     * @param array $periodStats Estadísticas acumuladas del periodo (retardos totales, faltas, etc.)
     * @param array $dailyStats Estadísticas desglosadas por día (para bonos de evaluación diaria)
     * @return array Estructura ['total_amount' => float, 'details' => array]
     */
    public function calculate(Employee $employee, array $periodStats, array $dailyStats): array
    {
        // Obtener bonos recurrentes activos (tanto el bono global como la asignación al empleado)
        $activeBonuses = $employee->recurringBonuses()
            ->where('bonuses.is_active', true)
            ->wherePivot('is_active', true)
            ->get();

        $totalAmount = 0;
        $details = [];

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

            // A. Evaluación Diaria (Ej: $50 por cada día que llegue temprano)
            if ($scope === 'daily') {
                foreach ($dailyStats as $date => $stat) {
                    // Validaciones de pre-requisitos
                    if ($concept === 'attendance' && empty($stat['is_attendance'])) continue;
                    if ($concept === 'late_minutes' && empty($stat['is_attendance'])) continue;

                    $value = $this->extractValue($concept, $stat);
                    
                    if ($this->checkRule($value, $config['operator'], $config['value'])) {
                        $bonusPay += $this->calculateAmount(
                            $config['behavior'], 
                            $baseAmount, 
                            $value, 
                            $config['value'], 
                            $config['operator']
                        );
                    }
                }
            } 
            // B. Evaluación Global del Periodo (Ej: $500 si en toda la semana tuvo 0 retardos)
            else {
                $value = $this->extractValue($concept, $periodStats);
                if ($this->checkRule($value, $config['operator'], $config['value'])) {
                    $bonusPay += $this->calculateAmount(
                        $config['behavior'], 
                        $baseAmount, 
                        $value, 
                        $config['value'], 
                        $config['operator']
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
                        'scope' => $scope
                    ]
                ];
            }
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'details' => $details
        ];
    }

    /**
     * Extrae el valor numérico a evaluar según el concepto configurado.
     */
    private function extractValue(string $concept, array $data): float
    {
        return match($concept) {
            'late_minutes' => (float) ($data['late_minutes'] ?? 0),
            'extra_minutes' => (float) ($data['extra_minutes'] ?? 0),
            'unjustified_absences' => (float) ($data['unjustified_absences'] ?? 0),
            // Si es daily stats, attendance_days no existe, usamos is_attendance booleano
            'attendance' => isset($data['attendance_days']) 
                ? (float) $data['attendance_days'] 
                : (float) ($data['is_attendance'] ? 1 : 0),
            default => 0.0
        };
    }

    /**
     * Calcula el monto final a pagar (Monto fijo o Pago por unidad).
     */
    private function calculateAmount(string $behavior, float $baseAmount, float $actualValue, float $targetValue, string $operator): float
    {
        // Opción A: Monto fijo si se cumple la regla
        if ($behavior === 'fixed_amount') {
            return $baseAmount;
        }
        
        // Opción B: Pago por unidad (Ej: $10 por cada minuto extra)
        if ($behavior === 'pay_per_unit') {
            // Si la regla es "Mayor que X", pagamos solo por el excedente
            if ($operator === '>') {
                $units = max(0, $actualValue - $targetValue);
                return $units * $baseAmount;
            }
            
            // En otros casos (ej: pagar por cada minuto extra sin umbral minimo, o donde umbral es 0)
            return $actualValue * $baseAmount;
        }

        return 0.0;
    }

    /**
     * Evalúa la condición lógica.
     */
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