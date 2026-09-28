<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * `uuid` público gerado na criação e usado como chave de rota. O `id`
 * auto-incremento continua sendo a PK interna e a base das foreign keys.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
