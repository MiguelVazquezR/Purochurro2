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
        'vacation_balance',
        'is_active',
        'aws_face_id',
        'default_schedule_template',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hired_at' => 'date',
        'base_salary' => 'decimal:2',
        'vacation_balance' => 'decimal:4',
        'is_active' => 'boolean',
        'default_schedule_template' => 'array',
    ];

    protected $appends = ['full_name', 'profile_photo_url'];

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(Bonus::class, 'employee_bonus')
            ->withPivot('assigned_date', 'amount')
            ->withTimestamps();
    }
    
    // AGREGADO: Relación con logs de vacaciones
    public function vacationLogs()
    {
        return $this->hasMany(VacationLog::class)->latest();
    }

    // --- Métodos Helper ---

    /**
     * Ajusta el saldo de vacaciones y crea el log correspondiente.
     */
    public function adjustVacationBalance(float $days, string $type, string $description, ?int $userId = null)
    {
        $before = $this->vacation_balance;
        $this->vacation_balance += $days;
        $this->save();

        $this->vacationLogs()->create([
            'user_id' => $userId, // Admin que hizo el cambio
            'type' => $type,
            'days' => $days,
            'balance_before' => $before,
            'balance_after' => $this->vacation_balance,
            'description' => $description,
        ]);
    }

    // ... Accessors y MediaLibrary (sin cambios) ...
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
            
        $this->addMediaConversion('rekognition_optimized')
            ->width(800)
            ->height(800)
            ->nonQueued();
    }
}