<?php

namespace Database\Seeders;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Bonus;
use App\Models\Category;
use App\Models\DailyOperation;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\IncidentRequest;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\PayrollReceipt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Ejecutar Seeders Dependientes
        $this->call(LocationSeeder::class);
        $locationCarrito = Location::where('slug', 'carrito')->first();

        // 1. Crear Turnos
        $shiftMorning = Shift::create([
            'name' => 'Matutino',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'color' => '#3b82f6', // Azul
            'is_active' => true,
        ]);

        $shiftEvening = Shift::create([
            'name' => 'Vespertino',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'color' => '#f97316', // Naranja
            'is_active' => true,
        ]);

        // 2. Crear Bonos
        $bonusPunctuality = Bonus::create([
            'name' => 'Bono de Puntualidad',
            'description' => 'Cero retardos en el periodo',
            'amount' => 200.00,
            'type' => 'fixed',
            'is_active' => true,
            'rule_config' => [
                'concept' => 'late_minutes',
                'operator' => '<=',
                'value' => 0,
                'scope' => 'period_accumulated',
                'behavior' => 'fixed_amount'
            ]
        ]);

        $bonusSales = Bonus::create([
            'name' => 'Bono Venta Semanal',
            'description' => 'Meta de venta superada',
            'amount' => 300.00,
            'type' => 'fixed',
            'is_active' => true,
        ]);

        // 3. Crear Días Festivos (Próximos)
        Holiday::create([
            'name' => 'Año Nuevo',
            'date' => Carbon::now()->startOfYear()->addYear(),
            'mandatory_rest' => true,
            'pay_multiplier' => 3.0,
        ]);
        
        // Un festivo simulado la semana pasada para probar pago doble
        $pastHolidayDate = Carbon::now()->subWeek()->startOfWeek()->addDay(2); // Miércoles pasado
        Holiday::create([
            'name' => 'Festivo Local (Prueba)',
            'date' => $pastHolidayDate,
            'mandatory_rest' => true,
            'pay_multiplier' => 2.0,
        ]);

        // 4. Catálogo de Productos (Para POS)
        $catChurros = Category::create(['name' => 'Churros']);
        $catBebidas = Category::create(['name' => 'Bebidas']);

        $products = [
            ['name' => 'Churro Clásico', 'price' => 25, 'cat' => $catChurros->id],
            ['name' => 'Churro Relleno', 'price' => 35, 'cat' => $catChurros->id],
            ['name' => 'Chocolate Caliente', 'price' => 45, 'cat' => $catBebidas->id],
            ['name' => 'Café Americano', 'price' => 30, 'cat' => $catBebidas->id],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $prod = Product::create([
                'name' => $p['name'],
                'category_id' => $p['cat'],
                'price' => $p['price'],
                'cost' => $p['price'] * 0.4,
                'is_sellable' => true,
                'track_inventory' => true,
                'is_active' => true,
            ]);
            
            // Inventario Inicial
            Inventory::create([
                'location_id' => $locationCarrito->id,
                'product_id' => $prod->id,
                'quantity' => 100
            ]);
            
            $productModels[] = $prod;
        }

        // 5. Crear Usuarios y Empleados
        $adminUser = User::create([
            'name' => 'Administrador',
            'email' => 'admin@purochurro.com',
            'password' => Hash::make('password'),
        ]);

        // Empleados
        $employeesData = [
            // Nombre, Apellido, Email, Salario, FechaNacimiento (Agregado explícito para evitar error 1364)
            ['Juan', 'Vendedor', 'juan@test.com', 300.00, '1995-05-15'], 
            ['Maria', 'Encargada', 'maria@test.com', 450.00, '1992-08-20'],
            ['Pedro', 'Nuevo', 'pedro@test.com', 250.00, '2000-01-10'],
        ];

        $employeesModels = [];

        foreach ($employeesData as $idx => $data) {
            $user = User::create([
                'name' => "$data[0] $data[1]",
                'email' => $data[2],
                'password' => Hash::make('password'),
            ]);

            $emp = Employee::create([
                'user_id' => $user->id,
                'first_name' => $data[0],
                'last_name' => $data[1],
                'birth_date' => $data[4], // Usamos el dato explícito del array
                'phone' => '333333333' . $idx,
                'address' => 'Conocido',
                'hired_at' => Carbon::now()->subMonths(6),
                'base_salary' => $data[3],
                'vacation_balance' => 6 + $idx, // Días disponibles
                'is_active' => true,
                'default_schedule_template' => [
                    'monday' => $shiftMorning->id,
                    'tuesday' => $shiftMorning->id,
                    'wednesday' => $shiftMorning->id,
                    'thursday' => $shiftMorning->id,
                    'friday' => $shiftMorning->id,
                    'saturday' => $shiftEvening->id,
                    'sunday' => null // Descanso
                ]
            ]);

            // Asignar bono recurrente
            $emp->recurringBonuses()->attach($bonusPunctuality->id, [
                'amount' => 200,
                'is_active' => true
            ]);

            $employeesModels[] = $emp;
        }

        // 6. SIMULACIÓN DE HISTORIAL (2 SEMANAS)
        
        // Rango: Hace 2 semanas hasta hoy
        $simulationStart = Carbon::now()->subWeeks(1)->startOfWeek(Carbon::SUNDAY); // Semana pasada
        $today = Carbon::now();

        $currentDate = $simulationStart->copy();

        while ($currentDate <= $today) {
            $dateStr = $currentDate->format('Y-m-d');
            $isPast = $currentDate < Carbon::now()->startOfDay();
            
            // A. Crear Operación Diaria (Caja)
            // Simular que abren caja todos los días excepto domingos (opcional)
            $dailyOp = DailyOperation::create([
                'date' => $dateStr,
                'cash_start' => 500.00,
                'cash_end' => $isPast ? 500.00 : null, // Si ya pasó, cerramos caja luego
                'is_closed' => false, // La cerramos al final del loop si es pasado
                'notes' => 'Operación normal'
            ]);

            // Asignar personal a la caja (Pivot DailyOperationEmployee)
            foreach ($employeesModels as $emp) {
                // Solo si es su turno según template (simplificado)
                $dayName = strtolower($currentDate->format('l'));
                $shiftId = $emp->default_schedule_template[$dayName] ?? null;

                if ($shiftId) {
                    $dailyOp->staff()->attach($emp->id, ['location_id' => $locationCarrito->id]);
                    
                    // Crear Horario (WorkSchedule)
                    WorkSchedule::firstOrCreate([
                        'employee_id' => $emp->id,
                        'date' => $dateStr
                    ], [
                        'shift_id' => $shiftId,
                        'is_published' => true
                    ]);

                    // B. Simular Asistencia e Incidencias
                    if ($isPast) {
                        $checkIn = '09:00:00';
                        $checkOut = '17:00:00';
                        $incident = IncidentType::ASISTENCIA;
                        $late = false;

                        // Escenarios de prueba
                        if ($emp->first_name === 'Pedro' && $currentDate->isWednesday()) {
                            $checkIn = '09:40:00'; // Retardo
                            $late = true;
                        }
                        if ($emp->first_name === 'Maria' && $currentDate->dayOfWeek === $pastHolidayDate->dayOfWeek && $currentDate->weekOfYear === $pastHolidayDate->weekOfYear) {
                            // Maria descansó el festivo (pagado)
                            $incident = IncidentType::DIA_FESTIVO;
                            $checkIn = null;
                            $checkOut = null;
                        }

                        Attendance::create([
                            'employee_id' => $emp->id,
                            'date' => $dateStr,
                            'check_in' => $checkIn,
                            'check_out' => $checkOut,
                            'incident_type' => $incident,
                            'is_late' => $late
                        ]);
                    }
                }
            }

            // C. Simular Ventas (Para generar comisiones)
            if ($isPast || $currentDate->isToday()) {
                $numSales = rand(5, 15);
                $dayTotal = 0;

                for ($s = 0; $s < $numSales; $s++) {
                    $randomEmp = $employeesModels[rand(0, 2)];
                    $sale = Sale::create([
                        'daily_operation_id' => $dailyOp->id,
                        'user_id' => $randomEmp->user_id, // Quién vendió
                        'payment_method' => rand(0, 1) ? 'cash' : 'card',
                        'total' => 0
                    ]);

                    $totalSale = 0;
                    $itemsCount = rand(1, 4);
                    
                    for ($d = 0; $d < $itemsCount; $d++) {
                        $prod = $productModels[rand(0, count($productModels)-1)];
                        $qty = rand(1, 2);
                        $sub = $qty * $prod->price;
                        
                        SaleDetail::create([
                            'sale_id' => $sale->id,
                            'product_id' => $prod->id,
                            'quantity' => $qty,
                            'unit_price' => $prod->price,
                            'subtotal' => $sub
                        ]);
                        $totalSale += $sub;
                    }
                    $sale->update(['total' => $totalSale]);
                    $dayTotal += $totalSale;
                }

                // Cerrar la operación si es día pasado
                if ($isPast) {
                    $dailyOp->update([
                        'cash_end' => 500 + $dayTotal, // Cuadre perfecto
                        'is_closed' => true,
                        'notes' => 'Cierre automático seed. Ventas: $' . $dayTotal
                    ]);
                }
            }

            $currentDate->addDay();
        }

        // 7. CERRAR NÓMINA DE LA SEMANA PASADA
        // Usamos el PayrollService para calcular y congelar los datos
        $payrollService = new PayrollService();
        $startLastWeek = Carbon::now()->subWeeks(1)->startOfWeek(Carbon::SUNDAY);
        $endLastWeek = $startLastWeek->copy()->endOfWeek(Carbon::SATURDAY);

        foreach ($employeesModels as $emp) {
            $calc = $payrollService->calculate($emp, $startLastWeek, $endLastWeek);
            
            // Preparar JSON breakdown
            $finalBreakdown = $calc['breakdown'];
            $finalBreakdown['totals_breakdown'] = $calc['totals_breakdown'];
            $finalBreakdown['commissions_total'] = $calc['total_commissions'];

            PayrollReceipt::create([
                'employee_id' => $emp->id,
                'start_date' => $startLastWeek->format('Y-m-d'),
                'end_date' => $endLastWeek->format('Y-m-d'),
                'base_salary_snapshot' => $emp->base_salary,
                'total_pay' => $calc['total_pay'],
                'days_worked' => $calc['days_worked'],
                'total_bonuses' => $calc['total_bonuses'],
                'breakdown_data' => $finalBreakdown,
                'paid_at' => Carbon::now()->subDays(2), // Pagado hace 2 días
            ]);
        }

        // 8. Crear Solicitudes de Incidencia (Pruebas)
        IncidentRequest::create([
            'employee_id' => $employeesModels[0]->id, // Juan
            'incident_type' => IncidentType::VACACIONES,
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(7),
            'employee_reason' => 'Viaje familiar',
            'status' => 'pending'
        ]);

        IncidentRequest::create([
            'employee_id' => $employeesModels[1]->id, // Maria
            'incident_type' => IncidentType::PERMISO_CON_GOCE,
            'start_date' => Carbon::now()->addDays(1),
            'end_date' => Carbon::now()->addDays(1),
            'employee_reason' => 'Cita médica',
            'status' => 'approved',
            'admin_response' => 'Aprobado, traer justificante',
            'processed_by' => $adminUser->id,
            'processed_at' => Carbon::now()
        ]);
    }
}