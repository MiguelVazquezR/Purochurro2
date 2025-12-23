<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            
            // Tipo de incidencia solicitada
            $table->string('incident_type'); // Usaremos los valores del Enum IncidentType
            
            $table->date('start_date');
            $table->date('end_date');
            
            // Estado del flujo
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Motivos
            $table->text('employee_reason')->nullable(); // "Tengo cita médica"
            $table->text('admin_response')->nullable(); // "Rechazado porque hay mucho trabajo"
            
            // Auditoría de quién aprobó/rechazó
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_requests');
    }
};