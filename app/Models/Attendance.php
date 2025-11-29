<?php

namespace App\Models;

use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Attendance extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'incident_type',
        'is_late',
        'late_ignored',
        'admin_notes',
        'extra_hours',
    ];

    protected $casts = [
        'date' => 'date',
        'is_late' => 'boolean',
        'late_ignored' => 'boolean',
        'incident_type' => IncidentType::class,
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Definimos colecciones separadas para entrada y salida
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('check_in_photo')->singleFile();
        $this->addMediaCollection('check_out_photo')->singleFile();
    }
}