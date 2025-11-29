<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'amount',
        'type', // 'fixed', 'percentage', etc.
        'is_active'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relación con empleados (Historial de asignaciones)
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_bonus')
            ->withPivot('assigned_date', 'amount')
            ->withTimestamps();
    }
}