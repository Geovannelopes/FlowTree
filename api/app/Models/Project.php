<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasUuid;

    /** Colunas padrão de um projeto novo (paleta do FlowTree). */
    public const DEFAULT_COLUMNS = [
        ['name' => 'Backlog', 'color' => '#64748b', 'position' => 0, 'is_done' => false],
        ['name' => 'Pendente', 'color' => '#f59e0b', 'position' => 1, 'is_done' => false],
        ['name' => 'Em front-end', 'color' => '#3b82f6', 'position' => 2, 'is_done' => false],
        ['name' => 'Em back-end', 'color' => '#8b5cf6', 'position' => 3, 'is_done' => false],
        ['name' => 'Em teste', 'color' => '#f97316', 'position' => 4, 'is_done' => false],
        ['name' => 'Concluído', 'color' => '#22c55e', 'position' => 5, 'is_done' => true],
    ];

    protected $fillable = [
        'name',
        'description',
        'color',
        'logo',
        'is_archived',
        'created_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderBy('position');
    }

    public function activeSprint(): HasOne
    {
        return $this->hasOne(Sprint::class)->where('status', 'active');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->logo);
    }
}
