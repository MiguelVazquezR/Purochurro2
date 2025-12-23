<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'user_id',
        'type',
        'days',
        'balance_before',
        'balance_after',
        'description',
        'created_at',
    ];

    protected $casts = [
        'days' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}