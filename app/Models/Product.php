<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'category_id',
        'name',
        'barcode',
        'description',
        'price',
        'employee_price',
        'cost',
        'is_sellable',
        'track_inventory',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'employee_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_sellable' => 'boolean',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relación con Inventarios (Stock en Cocina, Carrito, etc.)
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    // Helper para obtener stock de una ubicación específica
    public function stockIn(Location $location)
    {
        return $this->inventories->where('location_id', $location->id)->first()?->quantity ?? 0;
    }
    
    // Spatie Media para foto del producto
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_image')
            ->singleFile()
            ->useDisk('public'); 
    }
    
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->nonQueued();
    }
}