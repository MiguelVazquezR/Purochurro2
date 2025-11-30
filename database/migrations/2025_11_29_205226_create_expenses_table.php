<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            
            $table->string('concept');
            $table->decimal('amount', 10, 2); // Hasta 99 millones
            $table->date('date');
            $table->text('notes')->nullable();
            
            // Auditoría: Quién registró el gasto
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};