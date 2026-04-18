<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla principal de bitácoras
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            // Autor de la bitácora
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Contenido/Cuerpo de la bitácora
            $table->text('content');
            $table->timestamps(); // created_at será nuestra fecha y hora de registro
        });

        // Tabla pivote para saber quién ya leyó la bitácora y cuándo
        Schema::create('logbook_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            
            // Un usuario solo puede registrar que leyó una bitácora una vez
            $table->unique(['logbook_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logbook_reads');
        Schema::dropIfExists('logbooks');
    }
};