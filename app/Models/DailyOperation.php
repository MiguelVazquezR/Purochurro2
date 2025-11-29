<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyOperation extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'is_closed', 'cash_start', 'cash_end', 'notes'];

    protected $casts = [
        'date' => 'date',
        'is_closed' => 'boolean',
        'cash_start' => 'decimal:2',
        'cash_end' => 'decimal:2',
    ];

    // Relación Muchos a Muchos para saber quién trabajó y dónde
    public function staff()
    {
        return $this->belongsToMany(Employee::class, 'daily_operation_employee')
            ->withPivot('location_id')
            ->using(DailyOperationEmployee::class);
    }
}