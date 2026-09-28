<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasUuid;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'project_id',
        'name',
        'goal',
        'starts_at',
        'ends_at',
        'status',
        'position',
        'completed_at',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** Cards já concluídos (com completed_at). */
    public function doneCards(): HasMany
    {
        return $this->hasMany(Card::class)->whereNotNull('completed_at');
    }
}
