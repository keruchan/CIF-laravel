<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class Assets
{
    /** Content versions invalidate browser/CDN caches whenever a deployed asset changes. */
    public static function cifAssetUrl(string $path): string
    {
        if (! preg_match('~^(?:(?:js|css)/[a-z0-9-]+\.(?:js|css)|assets/[a-z0-9-]+\.(?:png|jpe?g|webp|svg))$~iD', $path)) {
            throw new \InvalidArgumentException('Invalid application asset path.');
        }
        static $versions = [];
        if (! isset($versions[$path])) {
            $file = public_path($path);
            if (! is_file($file)) {
                throw new \RuntimeException('Missing application asset: '.$path);
            }
            $versions[$path] = substr(hash_file('sha256', $file), 0, 16);
        }

        return asset($path).'?v='.$versions[$path];
    }
}
