<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    use HasUuid;

    protected $fillable = ['slug', 'name', 'color', 'position'];
}
