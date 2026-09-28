<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'project_id',
        'board_column_id',
        'sprint_id',
        'assignee_id',
        'created_by',
        'title',
        'description',
        'priority',
        'due_date',
        'started_at',
        'completed_at',
        'position',
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function flags(): BelongsToMany
    {
        return $this->belongsToMany(Flag::class)->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CardMovement::class)->orderBy('created_at')->orderBy('id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->completed_at === null
            && $this->due_date->lt(now()->startOfDay());
    }
}
