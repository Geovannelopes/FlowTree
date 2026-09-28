<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Flag extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'slug', 'color'];

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class)->withTimestamps();
    }
}
