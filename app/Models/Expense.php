<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'concept',
        'amount',
        'date',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'date' => 'date',   // Convierte automáticamente a instancia Carbon
        'amount' => 'decimal:2',
    ];

    // Relación: Un gasto fue registrado por un Usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}