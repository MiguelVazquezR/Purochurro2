<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\DailyOperation;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\IncidentRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\WorkSchedule;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $today = Carbon::today();

        // ----------------------------------------------------
        // VISTA ADMINISTRADOR (ID 1)
        // ----------------------------------------------------
        if ($user->id === 1) {
            // ... (Código de Admin sin cambios) ...
            $salesToday = Sale::whereBetween('created_at', [$today->startOfDay(), $today->endOfDay()])->sum('total');
            $expensesToday = Expense::whereBetween('date', [$today->startOfDay(), $today->endOfDay()])->sum('amount');

            $lowStockProducts = Product::withSum('inventories as stock', 'quantity')
                ->orderBy('stock', 'asc')->take(5)->get()
                ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'stock' => $p->stock ?? 0, 'price' => $p->price]);

            $pendingRequests = IncidentRequest::where('status', 'pending')->count();
            $activeEmployeesCount = Employee::where('is_active', true)->count();
            
            $presentEmployees = Attendance::whereDate('date', $today)
                ->where('incident_type', IncidentType::ASISTENCIA)
                ->with('employee')->get()
                ->map(fn($a) => [
                    'id' => $a->employee->id,
                    'name' => $a->employee->first_name . ' ' . $a->employee->last_name,
                    'photo' => $a->employee->profile_photo_url,
                    'check_in' => $a->check_in ? Carbon::parse($a->check_in)->format('H:i') : '--:--',
                ]);

            $vacationEmployees = Attendance::whereDate('date', $today)
                ->where('incident_type', IncidentType::VACACIONES)
                ->with('employee')->get()
                ->map(fn($a) => [
                    'id' => $a->employee->id,
                    'name' => $a->employee->first_name . ' ' . $a->employee->last_name,
                    'photo' => $a->employee->profile_photo_url,
                ]);

            $scheduledToday = WorkSchedule::whereDate('date', $today)->whereNotNull('shift_id')->with(['employee', 'shift'])->get();
            $registeredIds = Attendance::whereDate('date', $today)->pluck('employee_id')->toArray();

            $absentEmployees = $scheduledToday->filter(fn($s) => !in_array($s->employee_id, $registeredIds))
                ->map(fn($s) => [
                    'id' => $s->employee->id,
                    'name' => $s->employee->first_name . ' ' . $s->employee->last_name,
                    'photo' => $s->employee->profile_photo_url,
                    'shift' => $s->shift ? $s->shift->name : 'Turno',
                    'start_time' => $s->shift ? Carbon::parse($s->shift->start_time)->format('H:i') : ''
                ])->values();

            $upcomingBirthdays = Employee::where('is_active', true)
                ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') >= ?", [$today->format('m-d')])
                ->orderByRaw("DATE_FORMAT(birth_date, '%m-%d') ASC")->take(3)->get()
                ->map(fn($e) => [
                    'name' => $e->first_name . ' ' . $e->last_name,
                    'date' => $e->birth_date->format('d M'),
                    'photo' => $e->profile_photo_url
                ]);

            return Inertia::render('Dashboard', [
                'isAdmin' => true,
                'stats' => [
                    'sales_today' => $salesToday,
                    'expenses_today' => $expensesToday,
                    'net_today' => $salesToday - $expensesToday,
                    'active_employees' => $activeEmployeesCount,
                    'present_list' => $presentEmployees,
                    'vacation_list' => $vacationEmployees,
                    'absent_list' => $absentEmployees,
                    'pending_requests' => $pendingRequests,
                    'low_stock_products' => $lowStockProducts,
                    'upcoming_birthdays' => $upcomingBirthdays,
                ]
            ]);
        }

        // ----------------------------------------------------
        // VISTA EMPLEADO
        // ----------------------------------------------------
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return Inertia::render('Dashboard', ['isAdmin' => false, 'stats' => []]);
        }

        $startOfWeek = $today->copy()->startOfWeek(Carbon::SUNDAY);
        $endOfWeek = $today->copy()->endOfWeek(Carbon::SATURDAY);

        // 1. Próximo Turno
        $nextSchedule = WorkSchedule::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $today)
            ->whereNotNull('shift_id')
            ->with('shift')
            ->orderBy('date')
            ->first();
        
        // 2. Horario Completo de la Semana (Para el modal)
        // Obtenemos todos los días de la semana, incluso si no hay turno asignado (aunque WorkSchedule suele crearse para todos)
        $weeklySchedule = WorkSchedule::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->orderBy('date')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'date_label' => $s->date->isoFormat('dddd D'), // Ej: "Lunes 25"
                    'shift_name' => $s->shift ? $s->shift->name : 'Descanso',
                    // Formato 12 horas (h:i A)
                    'start_time' => $s->shift ? Carbon::parse($s->shift->start_time)->format('h:i A') : null,
                    'end_time' => $s->shift ? Carbon::parse($s->shift->end_time)->format('h:i A') : null,
                    'color' => $s->shift ? $s->shift->color : '#9ca3af', // Gris si es descanso
                    'is_today' => $s->date->isToday(),
                    'is_rest' => is_null($s->shift_id),
                ];
            });

        // 3. Asistencia de Hoy
        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        // 4. Nómina Estimada
        $payrollService = new PayrollService();
        $payrollCalc = $payrollService->calculate($employee, $startOfWeek, $endOfWeek);

        // 5. Cumpleaños
        $upcomingBirthdays = Employee::where('is_active', true)
            ->where('id', '!=', $employee->id)
            ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') >= ?", [$today->format('m-d')])
            ->orderByRaw("DATE_FORMAT(birth_date, '%m-%d') ASC")
            ->take(3)
            ->get()
            ->map(fn($e) => [
                'name' => $e->first_name,
                'date' => $e->birth_date->format('d M'),
                'photo' => $e->profile_photo_url
            ]);

        return Inertia::render('Dashboard', [
            'isAdmin' => false,
            'employee' => [
                'first_name' => $employee->first_name,
                'vacation_balance' => $employee->vacation_balance,
            ],
            'stats' => [
                'next_shift' => $nextSchedule ? [
                    'date' => $nextSchedule->date->isoFormat('dddd D [de] MMMM'),
                    'start_time' => Carbon::parse($nextSchedule->shift->start_time)->format('h:i A'),
                    'end_time' => Carbon::parse($nextSchedule->shift->end_time)->format('h:i A'),
                    'is_today' => $nextSchedule->date->isToday(),
                    'shift_name' => $nextSchedule->shift->name,
                    'color' => $nextSchedule->shift->color 
                ] : null,
                'check_in_time' => $todayAttendance && $todayAttendance->check_in 
                    ? Carbon::parse($todayAttendance->check_in)->format('h:i A') 
                    : null,
                'estimated_pay' => $payrollCalc['total_pay'],
                'worked_days' => $payrollCalc['days_worked'],
                'weekly_schedule' => $weeklySchedule, // Nuevo dato para el modal
                'upcoming_birthdays' => $upcomingBirthdays,
            ]
        ]);
    }
}