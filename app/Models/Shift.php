<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'color',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i', // Formato limpio para inputs
        'end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];
    
    // Accessor para ver la duración o descripción rápida
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->name} (" . Carbon::parse($this->start_time)->format('H:i') . " - " . Carbon::parse($this->end_time)->format('H:i') . ")",
        );
    }
}