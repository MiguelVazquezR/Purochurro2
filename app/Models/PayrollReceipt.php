<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'base_salary_snapshot',
        'total_pay',
        'days_worked',
        'total_bonuses',
        'breakdown_data',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'paid_at' => 'datetime',
        'breakdown_data' => 'array', // Convertir JSON a Array automáticamente
        'total_pay' => 'decimal:2',
        'total_bonuses' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}