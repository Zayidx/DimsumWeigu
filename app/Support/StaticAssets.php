<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class StaticAssets
{
    public static function url(string $filename): string
    {
        $hash = File::hash(public_path('assets/'.$filename), 'sha256');

        return asset('assets/'.$filename).'?v='.substr($hash, 0, 12);
    }
}
