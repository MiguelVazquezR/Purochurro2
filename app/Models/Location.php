<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_sales_point'];

    protected $casts = [
        'is_sales_point' => 'boolean'
    ];

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }
}