<?php

namespace App\Models;

use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'incident_type',
        'start_date',
        'end_date',
        'status',
        'employee_reason',
        'admin_response',
        'processed_by',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'processed_at' => 'datetime',
        'incident_type' => IncidentType::class,
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}