<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTutorial extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module_name',
        'is_completed',
        'completed_at',
        'meta', // Por si en el futuro guardas en qué paso se quedó
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Relación con el usuario que realiza el tutorial.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}