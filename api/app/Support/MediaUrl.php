<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    /** URL pública de um caminho no disco (null quando não há arquivo). */
    public static function resolve(?string $path, ?string $disk = null): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk($disk ?? config('filesystems.default'))->url($path);
    }
}
