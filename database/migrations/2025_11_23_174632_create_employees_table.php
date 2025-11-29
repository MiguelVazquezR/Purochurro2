<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // Relación opcional con User. 
            // onDelete('set null') permite borrar al usuario del sistema sin borrar el historial del empleado.
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Datos Personales
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->string('phone', 20);
            $table->text('address');
            $table->string('email')->unique()->nullable(); // Email personal, distinto al del sistema
            
            // Datos Laborales
            $table->date('hired_at');
            $table->decimal('base_salary', 10, 2); // Siempre decimal para dinero
            $table->boolean('is_active')->default(true);
            
            // AWS Rekognition y Archivos
            // Guardamos el FaceID que nos devuelve AWS para comparar rápidamente sin re-enviar la foto siempre
            $table->string('aws_face_id')->nullable(); 

            // Horarios y Preferencias
            // Aquí guardamos la "plantilla" de la semana típica de este empleado.
            // Ejemplo JSON: { "mon": "morning", "tue": "rest", "wed": "double", ... }
            $table->json('default_schedule_template')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // Importante para no perder historial de nómina si se despide
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};