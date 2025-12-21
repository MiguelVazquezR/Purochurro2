<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
        'aws_face_id', // ID biométrico de AWS
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

    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(Bonus::class, 'employee_bonus')
            ->withPivot('assigned_date', 'amount')
            ->withTimestamps();
    }

    public function recurringBonuses()
    {
        return $this->belongsToMany(Bonus::class, 'recurring_bonuses')
            ->withPivot('amount', 'is_active')
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
        // Regla: 6 días mínimos fijos para este negocio
        return 6;
    }

    // --- Métodos de Negocio ---

    public function adjustVacationBalance(float $days, string $type, string $description, ?int $userId = null)
    {
        $oldBalance = $this->vacation_balance;
        
        $this->vacation_balance += $days;
        $this->save();

        $this->vacationLogs()->create([
            'user_id' => $userId,
            'type' => $type,
            'days' => $days,
            'balance_before' => $oldBalance,
            'balance_after' => $this->vacation_balance,
            'description' => $description,
        ]);
    }

    // --- Accessors ---

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    /**
     * Accesor CORREGIDO:
     * Ya no busca en la colección 'avatar' del Employee.
     * Solo devuelve la foto del User asociado o el placeholder de UI Avatars.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->user) {
                    return $this->user->profile_photo_url;
                }
                
                return 'https://ui-avatars.com/api/?name='.urlencode($this->full_name).'&color=7F9CF5&background=EBF4FF';
            },
        );
    }

    // NOTA: Se eliminaron registerMediaCollections y registerMediaConversions para 'avatar'
    // ya que ahora la foto vive en el modelo User.
}