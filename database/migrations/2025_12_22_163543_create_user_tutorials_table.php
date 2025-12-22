<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tutorials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('module_name'); // Ej: 'pos_terminal', 'payroll', 'kitchen'
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable(); // Para guardar paso actual si quisieras pausar y reanudar
            $table->timestamps();

            // Evitar duplicados por módulo/usuario
            $table->unique(['user_id', 'module_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tutorials');
    }
};