<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia; 

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Configuración Inicial
        $filter = $request->query('filter', 'today'); // Default: Hoy
        
        // CORRECCIÓN: Eliminamos Carbon::setWeekStartsAt() que causa el error.
        // La lógica de inicio de semana (Domingo) se aplicará directamente en getDateRanges().

        // 2. Obtener Rangos de Fechas (Actual y Anterior para comparación)
        $ranges = $this->getDateRanges($filter);
        
        $currentStart = $ranges['current_start'];
        $currentEnd = $ranges['current_end'];
        $prevStart = $ranges['prev_start'];
        $prevEnd = $ranges['prev_end'];

        // 3. Consultas de Datos Actuales
        $currentSales = Sale::whereBetween('created_at', [$currentStart, $currentEnd])->sum('total');
        $currentExpenses = Expense::whereBetween('date', [$currentStart, $currentEnd])->sum('amount');
        $currentProfit = $currentSales - $currentExpenses;
        $transactionCount = Sale::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        
        // Ticket Promedio (Evitar división entre cero)
        $averageTicket = $transactionCount > 0 ? ($currentSales / $transactionCount) : 0;

        // 4. Consultas de Datos Anteriores (Para comparación)
        $prevSales = Sale::whereBetween('created_at', [$prevStart, $prevEnd])->sum('total');
        $prevExpenses = Expense::whereBetween('date', [$prevStart, $prevEnd])->sum('amount');
        $prevProfit = $prevSales - $prevExpenses;

        // 5. Cálculo de Variaciones (%)
        $variations = [
            'sales' => $this->calculateGrowth($currentSales, $prevSales),
            'expenses' => $this->calculateGrowth($currentExpenses, $prevExpenses),
            'profit' => $this->calculateGrowth($currentProfit, $prevProfit),
        ];

        // 6. Valor Agregado: Top 5 Productos Vendidos en el periodo
        $topProducts = SaleDetail::select(
                'products.name', 
                DB::raw('SUM(sale_details.quantity) as total_qty'),
                DB::raw('SUM(sale_details.subtotal) as total_money')
            )
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$currentStart, $currentEnd])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 7. Datos para Gráfica (Evolución temporal)
        $chartData = $this->getChartData($filter, $currentStart, $currentEnd);

        return Inertia::render('Report/Index', [
            'filter' => $filter,
            'currentSales' => (float) $currentSales,
            'currentExpenses' => (float) $currentExpenses,
            'currentProfit' => (float) $currentProfit,
            'averageTicket' => (float) $averageTicket,
            'prevSales' => (float) $prevSales,
            'prevExpenses' => (float) $prevExpenses,
            'prevProfit' => (float) $prevProfit,
            'variations' => $variations,
            'topProducts' => $topProducts,
            'chartData' => $chartData
        ]);
    }

    /**
     * Define las fechas de inicio y fin para el periodo actual y el anterior.
     */
    private function getDateRanges($filter)
    {
        $now = Carbon::now();
        
        switch ($filter) {
            case 'week':
                // CORRECCIÓN: Usamos startOfWeek(Carbon::SUNDAY) y endOfWeek(Carbon::SATURDAY) explícitamente
                
                // Esta semana (Dom - Hoy)
                $currentStart = $now->copy()->startOfWeek(Carbon::SUNDAY);
                $currentEnd = $now->copy()->endOfDay();
                
                // Semana Pasada (Dom - Sab)
                $prevStart = $now->copy()->subWeek()->startOfWeek(Carbon::SUNDAY);
                $prevEnd = $now->copy()->subWeek()->endOfWeek(Carbon::SATURDAY); 
                break;

            case 'month':
                // Este mes (1 - Hoy) vs Mes Pasado (Completo)
                $currentStart = $now->copy()->startOfMonth();
                $currentEnd = $now->copy()->endOfDay();

                $prevStart = $now->copy()->subMonth()->startOfMonth();
                $prevEnd = $now->copy()->subMonth()->endOfMonth();
                break;

            case 'year':
                // Este año vs Año pasado
                $currentStart = $now->copy()->startOfYear();
                $currentEnd = $now->copy()->endOfDay();

                $prevStart = $now->copy()->subYear()->startOfYear();
                $prevEnd = $now->copy()->subYear()->endOfYear();
                break;

            case 'today':
            default:
                // Hoy vs Ayer
                $currentStart = $now->copy()->startOfDay();
                $currentEnd = $now->copy()->endOfDay();

                $prevStart = $now->copy()->subDay()->startOfDay();
                $prevEnd = $now->copy()->subDay()->endOfDay();
                break;
        }

        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
        ];
    }

    /**
     * Calcula porcentaje de crecimiento o decrecimiento.
     */
    private function calculateGrowth($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0; 
        }
        
        return (($current - $previous) / abs($previous)) * 100;
    }

    /**
     * Genera datos para gráficas según la granularidad del filtro.
     */
    private function getChartData($filter, $start, $end)
    {
        $query = Sale::whereBetween('created_at', [$start, $end]);
        $labels = [];
        $values = [];

        if ($filter === 'today') {
            // Agrupar por hora
            $data = $query->select(
                DB::raw('HOUR(created_at) as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            // Rellenar horas vacías (8:00 a 22:00)
            for ($i = 8; $i <= 22; $i++) {
                $labels[] = sprintf('%02d:00', $i);
                $values[] = $data[$i] ?? 0;
            }

        } elseif ($filter === 'week') {
            // Agrupar por día de la semana
            $data = $query->select(
                DB::raw('DATE(created_at) as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            $period = \Carbon\CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                // isoFormat('ddd') requiere locale configurado, fallback a format('D') si es necesario
                $labels[] = $date->isoFormat('ddd'); 
                $values[] = $data[$date->format('Y-m-d')] ?? 0;
            }

        } elseif ($filter === 'month') {
            // Agrupar por día del mes
            $data = $query->select(
                DB::raw('DATE(created_at) as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            $period = \Carbon\CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $labels[] = $date->format('d'); 
                $values[] = $data[$date->format('Y-m-d')] ?? 0;
            }
        } else {
            // Año: Agrupar por meses
            $data = $query->select(
                DB::raw('MONTH(created_at) as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            for ($i = 1; $i <= 12; $i++) {
                $labels[] = Carbon::create()->month($i)->isoFormat('MMM'); 
                $values[] = $data[$i] ?? 0;
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }
}