<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DailyOperationEmployee extends Pivot
{
    // Importante: Como en la migración definimos $table->id(), 
    // debemos decirle a Eloquent que este pivote sí tiene ID autoincremental.
    public $incrementing = true;

    protected $table = 'daily_operation_employee';

    protected $fillable = [
        'daily_operation_id',
        'employee_id',
        'location_id'
    ];

    // Relación opcional para acceder a la ubicación directamente desde el pivote
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function dailyOperation()
    {
        return $this->belongsTo(DailyOperation::class);
    }
}