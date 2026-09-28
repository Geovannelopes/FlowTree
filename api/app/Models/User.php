<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\MediaUrl;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'position',
        'avatar',
        'is_admin',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    public function assignedCards(): HasMany
    {
        return $this->hasMany(Card::class, 'assignee_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->avatar);
    }

    /** Admin tem acesso a todos os módulos. */
    public function hasModule(string $slug): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if ($this->relationLoaded('modules')) {
            return $this->modules->contains('slug', $slug);
        }

        return $this->modules()->where('slug', $slug)->exists();
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
