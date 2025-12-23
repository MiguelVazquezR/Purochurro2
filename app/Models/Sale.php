<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_operation_id',
        'user_id',
        'total',
        'payment_method',
        'is_employee_sale',
        'created_at',
    ];

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function dailyOperation()
    {
        return $this->belongsTo(DailyOperation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
