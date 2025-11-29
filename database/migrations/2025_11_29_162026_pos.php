<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ubicaciones Físicas (Cocina, Carrito 1, etc.)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: Cocina, Carrito Principal
            $table->string('slug')->unique(); // cocina, carrito
            $table->boolean('is_sales_point')->default(false); // ¿Se puede vender aquí? (Cocina: No, Carrito: Si)
            $table->timestamps();
        });

        // 2. Categorías de Productos
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->nullable(); // Para botones en el POS
            $table->timestamps();
        });

        // 3. Catálogo Maestro de Productos
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            
            $table->string('name');
            $table->string('barcode')->nullable()->unique(); // Para escaner
            $table->text('description')->nullable();
            
            // Precios (Decimales para dinero)
            $table->decimal('price', 10, 2)->default(0); // Precio Público
            $table->decimal('employee_price', 10, 2)->default(0); // Precio Empleado (Descuento)
            $table->decimal('cost', 10, 2)->default(0); // Costo real (para calcular ganancia)

            // Flags de control
            $table->boolean('is_sellable')->default(true); // True: Sabritas. False: Azúcar/Insumos.
            $table->boolean('track_inventory')->default(true); // Si queremos contar stock estricto
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Inventario Físico (Stock por Ubicación)
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            $table->decimal('quantity')->default(0);
            $table->decimal('min_stock')->default(5); // Alerta para re-surtir del Cocina al Carrito
            
            $table->unique(['location_id', 'product_id']); // Un producto solo aparece una vez por ubicación
            $table->timestamps();
        });

        // 5. Registro de Operación Diaria (Asignación de Staff)
        Schema::create('daily_operations', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique(); // Una operación por día
            $table->boolean('is_closed')->default(false); // Cierre de caja realizado
            $table->decimal('cash_start', 10, 2)->default(0); // Dinero inicial en caja (fondo)
            $table->decimal('cash_end', 10, 2)->nullable(); // Corte final
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pivote: Qué empleado estuvo en qué lugar ese día
        Schema::create('daily_operation_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_operation_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->constrained(); // ¿Estuvo en Cocina o Carrito?
            
            $table->unique(['daily_operation_id', 'employee_id']); // Un empleado solo un lugar por día (o eliminar si rotan)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_operation_employee');
        Schema::dropIfExists('daily_operations');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('locations');
    }
};