<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Employee extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'address',
        'email',
        'hired_at',
        'base_salary',
        'is_active',
        'aws_face_id',
        'default_schedule_template',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hired_at' => 'date',
        'base_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'default_schedule_template' => 'array',
    ];

    protected $appends = ['full_name', 'profile_photo_url'];

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // --- Accessors ---

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getFirstMediaUrl('avatar', 'thumb') ?: null,
        );
    }

    // --- Configuración Spatie MediaLibrary ---

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useDisk('public'); 
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->nonQueued();
            
        // Mantenemos la conversión optimizada por si decides enviar 
        // los bytes de esta imagen local a AWS Rekognition más adelante.
        $this->addMediaConversion('rekognition_optimized')
            ->width(800)
            ->height(800)
            ->nonQueued();
    }
}