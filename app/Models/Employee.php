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
        'termination_date',
        'termination_reason',
        'termination_notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hired_at' => 'date',
        'termination_date' => 'date',
        'base_salary' => 'decimal:2',
        'vacation_balance' => 'decimal:4',
        'is_active' => 'boolean',
        'default_schedule_template' => 'array',
    ];

    protected $appends = ['full_name', 'profile_photo_url', 'years_of_service', 'vacation_days_entitled'];

    // --- Relaciones ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con Bonos (Historial de bonos asignados)
     */
    public function bonuses()
    {
        return $this->belongsToMany(Bonus::class, 'employee_bonus')
            ->withPivot('assigned_date', 'amount')
            ->withTimestamps();
    }
    
    public function vacationLogs()
    {
         return $this->hasMany(VacationLog::class)->latest();
    }

    // --- Lógica Mexicana ---

    public function getYearsOfServiceAttribute()
    {
        $end = $this->termination_date ?? now();
        return $this->hired_at ? $this->hired_at->floatDiffInYears($end) : 0;
    }

    public function getVacationDaysEntitledAttribute()
    {
        $years = floor($this->years_of_service);

        if ($years < 1) return 0;
        if ($years == 1) return 12;
        if ($years == 2) return 14;
        if ($years == 3) return 16;
        if ($years == 4) return 18;
        if ($years == 5) return 20;
        if ($years >= 6 && $years <= 10) return 22;
        if ($years >= 11 && $years <= 15) return 24;
        if ($years >= 16 && $years <= 20) return 26;
        
        return 28;
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

    // --- Media Library ---

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
    }
}