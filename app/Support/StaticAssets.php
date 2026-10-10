<?php

namespace App\Support;

final class StaticAssets
{
    public static function url(string $filename): string
    {
        $path = public_path('assets/'.$filename);
        $version = filemtime($path).'-'.filesize($path);

        return asset('assets/'.$filename).'?v='.$version;
    }
}
