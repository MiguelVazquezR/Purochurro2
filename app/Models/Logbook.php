<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Logbook extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'content',
    ];

    // --- Relaciones ---

    /**
     * El autor de la bitácora
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Usuarios que han leído esta bitácora
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'logbook_reads')
            ->withPivot('read_at'); 
            // Eliminamos ->withTimestamps() para que no busque created_at y updated_at
    }

    // --- Spatie MediaLibrary ---
    
    /**
     * Opcional: Puedes definir colecciones para restringir tipos de archivos
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence')
             ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
             ->useDisk('public');
    }
}