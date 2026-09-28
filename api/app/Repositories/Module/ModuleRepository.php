<?php

namespace App\Repositories\Module;

use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;

class ModuleRepository
{
    public function all(): Collection
    {
        return Module::query()->orderBy('id')->get();
    }
}
