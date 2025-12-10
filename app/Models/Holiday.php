<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'name', 'mandatory_rest', 'pay_multiplier'];

    protected $casts = [
        'date' => 'date',
        'mandatory_rest' => 'boolean',
        'pay_multiplier' => 'integer',
    ];
}