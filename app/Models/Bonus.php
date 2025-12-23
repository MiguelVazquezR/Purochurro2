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
        'type', 
        'is_active',
        'rule_config',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'rule_config' => 'array',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_bonus')
            ->withPivot('assigned_date', 'amount')
            ->withTimestamps();
    }
}