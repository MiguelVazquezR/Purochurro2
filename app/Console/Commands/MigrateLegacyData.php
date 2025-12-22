<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Location;
use App\Models\Inventory;
use App\Models\DailyOperation;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\Bonus;

class MigrateLegacyData extends Command
{
    protected $signature = 'migrate:legacy';
    protected $description = 'Migra datos de la v1.0.0 a la v2.0.0';

    public function handle()
    {
        $this->info('Iniciando migración de datos...');

        try {
            $oldUsers = DB::connection('mysql_old')->table('users')->count();
            $this->info("Conexión exitosa. Usuarios encontrados en DB vieja: $oldUsers");
        } catch (\Exception $e) {
            $this->error("No se pudo conectar a la base de datos antigua 'mysql_old'. Revisa tu config/database.php");
            return;
        }

        DB::beginTransaction();

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            $this->migrateUsersAndEmployees();
            $this->migrateProductsAndInventory();
            // $this->migrateOperationsAndSales();
            // $this->migrateExpenses();
            $this->migrateHolidaysAndBonuses();
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::commit();
            $this->info('¡Migración completada con éxito!');
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error('Error durante la migración: ' . $e->getMessage());
            $this->error('Línea: ' . $e->getLine());
            $this->error('Archivo: ' . $e->getFile());
        }
    }

    private function migrateUsersAndEmployees()
    {
        $this->line('Migrando Usuarios y creando Empleados...');
        $oldUsers = DB::connection('mysql_old')->table('users')->get();

        foreach ($oldUsers as $oldUser) {
            $existingUser = User::where('email', $oldUser->email)->first();

            if (!$existingUser) {
                $newUser = new User();
                $newUser->id = $oldUser->id;
                $newUser->name = $oldUser->name;
                $newUser->email = $oldUser->email;
                $newUser->password = $oldUser->password;
                $newUser->created_at = $oldUser->created_at;
                $newUser->updated_at = $oldUser->updated_at;
                $newUser->save();
            } else {
                $newUser = $existingUser;
            }

            // Restricción explícita para ID 1
            if ($newUser->id == 1) {
                continue;
            }

            $parts = explode(' ', $oldUser->name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? 'Doe';

            if (!Employee::where('user_id', $newUser->id)->exists()) {
                Employee::create([
                    'user_id' => $newUser->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $newUser->email,
                    'phone' => '0000000000',
                    'address' => 'Dirección migrada',
                    'birth_date' => '2000-01-01',
                    'hired_at' => $oldUser->created_at,
                    'base_salary' => 1200,
                    'is_active' => true,
                    'created_at' => $oldUser->created_at,
                    'updated_at' => $oldUser->updated_at,
                ]);
            }
        }
        $this->info('Usuarios migrados.');
    }

    private function migrateProductsAndInventory()
    {
        $this->line('Migrando Productos e Inventarios...');
        
        // 1. Crear Ubicación fija: Cocina
        $locCocina = Location::firstOrCreate(
            ['slug' => 'cocina'],
            ['name' => 'Cocina', 'is_sales_point' => false]
        );
        $this->info(" -> Ubicación creada/encontrada: Cocina (ID: {$locCocina->id})");

        // Nota: La ubicación 'Carrito' se creará dinámicamente más abajo basada en la tabla 'carts'

        $oldProducts = DB::connection('mysql_old')->table('products')->get();
        $productMap = []; // Para mapear ID Viejo => ID Nuevo

        foreach ($oldProducts as $oldProd) {
            $latestPriceRow = DB::connection('mysql_old')->table('prices')
                ->where('product_id', $oldProd->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $finalPrice = $latestPriceRow->price ?? $oldProd->public_price ?? $oldProd->price ?? 0;
            $code = $oldProd->code ?? 'MIG-' . $oldProd->id;
            $description = $oldProd->description ?? null; 

            $product = Product::updateOrCreate(
                ['name' => $oldProd->name],
                [
                    'barcode' => $code,
                    'description' => $description,
                    'price' => $finalPrice,
                ]
            );
            
            // Guardamos la referencia del ID viejo al nuevo
            $productMap[$oldProd->id] = $product->id;
        }
        
        // Item legado
        Product::firstOrCreate(
            ['barcode' => 'LEGACY_ITEM'],
            ['name' => 'Item Migrado (Histórico)', 'price' => 0]
        );

        // --- MIGRACIÓN DE INVENTARIOS ---

        // A) Inventario de COCINA (desde tabla 'warehouses')
        // Corrección aplicada: Se busca 'Cocina 1'
        $cocinaWarehouse = DB::connection('mysql_old')->table('warehouses')->where('name', 'Cocina 1')->first();
        
        if ($cocinaWarehouse && !empty($cocinaWarehouse->products)) {
            $productsJson = json_decode($cocinaWarehouse->products, true);
            
            if (is_array($productsJson)) {
                $countCocina = 0;
                foreach ($productsJson as $oldProdId => $qty) {
                    if (isset($productMap[$oldProdId]) && $qty > 0) {
                        Inventory::updateOrCreate(
                            ['product_id' => $productMap[$oldProdId], 'location_id' => $locCocina->id],
                            ['quantity' => $qty]
                        );
                        $countCocina++;
                    }
                }
                $this->info(" -> Inventario Cocina migrado: $countCocina productos.");
            }
        }

        // B) Inventario de CARRITOS (desde tabla 'carts')
        $carts = DB::connection('mysql_old')->table('carts')->get();
        $this->info(" -> Encontrados " . $carts->count() . " registros en tabla 'carts'. Procesando...");

        foreach ($carts as $index => $cart) {
            $cartName = $cart->name ?? ('Carrito ' . ($carts->count() > 1 ? ($index + 1) : 'Principal'));
            
            $locCart = Location::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($cartName)],
                [
                    'name' => $cartName, 
                    'is_sales_point' => true
                ]
            );

            $this->info("   -> Procesando ubicación: $cartName (ID V2: {$locCart->id})");

            if (!empty($cart->products)) {
                $cartProductsJson = json_decode($cart->products, true);
                
                if (is_array($cartProductsJson)) {
                    $countCartItems = 0;
                    foreach ($cartProductsJson as $oldProdId => $qty) {
                        if (isset($productMap[$oldProdId])) {
                            Inventory::updateOrCreate(
                                ['product_id' => $productMap[$oldProdId], 'location_id' => $locCart->id],
                                ['quantity' => $qty]
                            );
                            $countCartItems++;
                        }
                    }
                    $this->info("      -> Inventario migrado: $countCartItems productos en $cartName.");
                } else {
                    $this->warn("      -> El campo 'products' no es un JSON válido para el carrito ID {$cart->id}.");
                }
            } else {
                $this->warn("      -> Carrito ID {$cart->id} sin productos.");
            }
        }

        $this->info('Productos e Inventarios (Cocina y Carritos) migrados correctamente.');
    }

    private function migrateOperationsAndSales()
    {
        $this->line('Migrando Operaciones y Ventas...');

        $oldSales = DB::connection('mysql_old')->table('sales')->orderBy('created_at')->get();
        $legacyProduct = Product::where('barcode', 'LEGACY_ITEM')->first();

        $salesByDate = $oldSales->groupBy(function($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        });

        foreach ($salesByDate as $date => $sales) {
            
            $cashRegister = DB::connection('mysql_old')
                ->table('cash_registers')
                ->whereDate('date', $date)
                ->first();

            $fallbackTotal = $sales->sum(function($sale) {
                return $sale->total ?? $sale->amount ?? 0;
            });

            $dailyOp = DailyOperation::firstOrCreate(
                ['date' => $date],
                [
                    'is_closed' => true,
                    'cash_start' => 250, 
                    'cash_end' => $cashRegister ? $cashRegister->cash : $fallbackTotal,
                    'notes' => 'Operación generada por migración'
                ]
            );

            foreach ($sales as $oldSale) {
                $oldUserId = $oldSale->user_id ?? 1;
                $userId = User::find($oldUserId) ? $oldUserId : 1;

                $v1ProductId = $oldSale->product_id ?? null;
                $v2Product = null;

                if ($v1ProductId) {
                    $v1ProductName = DB::connection('mysql_old')
                        ->table('products')
                        ->where('id', $v1ProductId)
                        ->value('name');
                    
                    if ($v1ProductName) {
                        $v2Product = Product::where('name', $v1ProductName)->first();
                    }
                }

                if (!$v2Product) {
                    $v2Product = $legacyProduct;
                }

                $historicalPrice = 0;
                if ($v1ProductId) {
                    $historicalPrice = DB::connection('mysql_old')->table('prices')
                        ->where('product_id', $v1ProductId)
                        ->where('created_at', '<=', $oldSale->created_at)
                        ->orderBy('created_at', 'desc')
                        ->value('price');
                }

                $unitPrice = $historicalPrice ?: ($oldSale->price ?? $oldSale->unit_price ?? 0);
                
                $quantity = $oldSale->quantity ?? 1;
                
                if ($unitPrice == 0 && isset($oldSale->total) && $quantity > 0) {
                     $unitPrice = $oldSale->total / $quantity;
                }

                $subtotal = $unitPrice * $quantity;

                $newSale = Sale::create([
                    'daily_operation_id' => $dailyOp->id,
                    'user_id' => $userId,
                    'total' => $subtotal, 
                    'payment_method' => 'cash',
                    'created_at' => $oldSale->created_at,
                    'updated_at' => $oldSale->updated_at,
                ]);

                SaleDetail::create([
                    'sale_id' => $newSale->id,
                    'product_id' => $v2Product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }
        }
        $this->info('Ventas migradas.');
    }

    private function migrateExpenses()
    {
        $this->line('Migrando Gastos...');
        
        $oldOutcomes = DB::connection('mysql_old')->table('outcomes')->get();

        foreach ($oldOutcomes as $outcome) {
            
            $conceptValue = $outcome->concept ?? null;

            if (!$conceptValue) {
                try {
                    $conceptId = $outcome->outcome_concept_id ?? null;
                    if ($conceptId) {
                        $conceptValue = DB::connection('mysql_old')
                            ->table('outcome_concepts')
                            ->where('id', $conceptId)
                            ->value('name');
                    }
                } catch (\Exception $e) {
                    // Ignorar
                }
            }

            $finalConcept = $conceptValue ?? 'Gasto General';
            
            $outcomeUserId = $outcome->user_id ?? 1;
            $userId = User::find($outcomeUserId) ? $outcomeUserId : 1;

            Expense::create([
                'concept' => $finalConcept,
                'description' => $outcome->description ?? ($finalConcept . ' (Migrado)'), 
                'amount' => $outcome->quantity ?? $outcome->amount ?? 0, 
                'date' => $outcome->created_at,
                'user_id' => $userId,
                'created_at' => $outcome->created_at,
                'updated_at' => $outcome->updated_at ?? $outcome->created_at,
            ]);
        }
        $this->info('Gastos migrados.');
    }

    private function migrateHolidaysAndBonuses()
    {
        $this->line('Migrando Días Festivos y Bonos...');

        // 1. BONOS
        $oldBonuses = DB::connection('mysql_old')->table('bonuses')->get();
        foreach ($oldBonuses as $oldBonus) {
            Bonus::create([
                'name' => $oldBonus->name,
                'description' => $oldBonus->description ?? null,
                'amount' => $oldBonus->amount ?? 0,
                'type' => 'fixed',
                'rule_config' => null,
                'is_active' => $oldBonus->is_active ?? true, 
                'created_at' => $oldBonus->created_at,
                'updated_at' => $oldBonus->updated_at,
            ]);
        }
        $this->info(" -> " . $oldBonuses->count() . " bonos migrados.");

        // 2. DÍAS FESTIVOS
        // CORRECCIÓN: Parseo de fecha formato 'd-m' (01-01) a 'Y-m-d'
        $oldHolidays = DB::connection('mysql_old')->table('holidays')->get();
        foreach ($oldHolidays as $oldHoliday) {
            
            try {
                // Carbon parseará '01-01' usando el año actual automáticamente
                // El formato 'd-m' indica Dia-Mes
                $dateObject = Carbon::createFromFormat('d-m', $oldHoliday->date);
            } catch (\Exception $e) {
                // Fallback: Si no es d-m, quizás es texto o d/m. Usamos fecha actual como seguridad.
                $this->warn(" -> Error parseando fecha '{$oldHoliday->date}'. Usando fecha actual.");
                $dateObject = now();
            }

            Holiday::create([
                'name' => $oldHoliday->name,
                // Formateamos a Y-m-d para que MySQL V2 lo acepte
                'date' => $dateObject->format('Y-m-d'), 
                'mandatory_rest' => 1,
                'pay_multiplier' => 2.0,
                'created_at' => $oldHoliday->created_at,
                'updated_at' => $oldHoliday->updated_at,
            ]);
        }
        $this->info(" -> " . $oldHolidays->count() . " días festivos migrados.");
    }
}