<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'created_at'];

    // Relación inversa (opcional, pero útil si quieres ver qué productos tiene una categoría)
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}