<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Agregamos campos para controlar la baja
            $table->date('termination_date')->nullable()->after('is_active');
            $table->string('termination_reason')->nullable()->after('termination_date'); // unjustified, justified, resignation
            $table->text('termination_notes')->nullable()->after('termination_reason');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['termination_date', 'termination_reason', 'termination_notes']);
        });
    }
};