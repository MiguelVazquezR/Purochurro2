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
        
        // 2. Obtener Rangos de Fechas (Actual y Anterior para comparación)
        // Pasamos todo el Request para manejar las fechas custom
        $ranges = $this->getDateRanges($filter, $request);
        
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

        // 6. Top 5 Productos
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

        // 7. Datos para Gráfica
        $chartData = $this->getChartData($filter, $currentStart, $currentEnd);

        return Inertia::render('Report/Index', [
            'filter' => $filter,
            'customStart' => $request->query('start_date'),
            'customEnd' => $request->query('end_date'),
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
    private function getDateRanges($filter, Request $request)
    {
        $now = Carbon::now();
        
        switch ($filter) {
            case 'week':
                // Esta semana (Dom - Hoy) vs Semana Pasada
                $currentStart = $now->copy()->startOfWeek(Carbon::SUNDAY);
                $currentEnd = $now->copy()->endOfDay();
                
                $prevStart = $now->copy()->subWeek()->startOfWeek(Carbon::SUNDAY);
                $prevEnd = $now->copy()->subWeek()->endOfWeek(Carbon::SATURDAY); 
                break;

            case 'month':
                // Este mes vs Mes Pasado
                $currentStart = $now->copy()->startOfMonth();
                $currentEnd = $now->copy()->endOfDay();

                $prevStart = $now->copy()->subMonth()->startOfMonth();
                $prevEnd = $now->copy()->subMonth()->endOfMonth();
                break;

            case 'last_3_months':
                // Últimos 3 meses vs los 3 meses anteriores a esos
                $currentStart = $now->copy()->subMonths(3)->startOfDay();
                $currentEnd = $now->copy()->endOfDay();

                $prevStart = $now->copy()->subMonths(6)->startOfDay();
                $prevEnd = $now->copy()->subMonths(3)->subSecond();
                break;

            case 'year':
                // Este año (YTD) vs Año pasado (Completo)
                $currentStart = $now->copy()->startOfYear();
                $currentEnd = $now->copy()->endOfDay();

                $prevStart = $now->copy()->subYear()->startOfYear();
                $prevEnd = $now->copy()->subYear()->endOfYear();
                break;

            case 'previous_year':
                // Año calendario anterior completo vs Hace 2 años
                $currentStart = $now->copy()->subYear()->startOfYear();
                $currentEnd = $now->copy()->subYear()->endOfYear();

                $prevStart = $now->copy()->subYears(2)->startOfYear();
                $prevEnd = $now->copy()->subYears(2)->endOfYear();
                break;

            case 'custom':
                if ($request->has(['start_date', 'end_date'])) {
                    $currentStart = Carbon::parse($request->start_date)->startOfDay();
                    $currentEnd = Carbon::parse($request->end_date)->endOfDay();
                    
                    // Calcular el periodo previo de la misma duración
                    $daysDiff = $currentStart->diffInDays($currentEnd) + 1;
                    
                    $prevEnd = $currentStart->copy()->subSecond();
                    $prevStart = $prevEnd->copy()->subDays($daysDiff)->startOfDay();
                } else {
                    // Fallback a hoy si fallan los params
                    $currentStart = $now->copy()->startOfDay();
                    $currentEnd = $now->copy()->endOfDay();
                    $prevStart = $now->copy()->subDay()->startOfDay();
                    $prevEnd = $now->copy()->subDay()->endOfDay();
                }
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

    private function calculateGrowth($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0; 
        }
        return (($current - $previous) / abs($previous)) * 100;
    }

    private function getChartData($filter, $start, $end)
    {
        $query = Sale::whereBetween('created_at', [$start, $end]);
        $labels = [];
        $values = [];

        // Lógica de agrupación dinámica según la duración del periodo
        $daysDiff = $start->diffInDays($end);

        if ($daysDiff <= 1) { // Hoy / Ayer
            // Por Hora
            $data = $query->select(
                DB::raw('HOUR(created_at) as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            for ($i = 8; $i <= 22; $i++) {
                $labels[] = sprintf('%02d:00', $i);
                $values[] = $data[$i] ?? 0;
            }

        } elseif ($daysDiff <= 31) { // Semana / Mes
            // Por Día
            $data = $query->select(
                DB::raw('DATE(created_at) as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            $period = \Carbon\CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                // Formato dd/mm para gráficas más limpias
                $labels[] = $date->format('d/m'); 
                $values[] = $data[$date->format('Y-m-d')] ?? 0;
            }

        } else { // Año / > 1 Mes
            // Por Mes
            $data = $query->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as label'), 
                DB::raw('SUM(total) as total')
            )->groupBy('label')->pluck('total', 'label');

            // Iterar por meses para llenar huecos
            $currentMonth = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();

            while ($currentMonth <= $endMonth) {
                $key = $currentMonth->format('Y-m');
                // Label legible: Ene 24, Feb 24...
                $labels[] = ucfirst($currentMonth->translatedFormat('M y'));
                $values[] = $data[$key] ?? 0;
                $currentMonth->addMonth();
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }
}