<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Facades\Storage;

final class MrfImportStaging
{
    private static function path(int $actor, string $token): string
    {
        if ($actor < 1 || ! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new \InvalidArgumentException('Upload the document again.');
        }

        return 'recruitment-imports/'.$actor.'-'.$token.'.bin';
    }

    public static function store(array $source, int $actor, string $token): array
    {
        $path = self::path($actor, $token);
        if (! Storage::disk('local')->put($path, $source['bytes'])) {
            throw new \RuntimeException('Unable to stage the uploaded document.');
        }
        unset($source['bytes']);

        return $source + ['staged_path' => $path];
    }

    public static function restore(array $source, int $actor, string $token): array
    {
        $path = self::path($actor, $token);
        if (($source['staged_path'] ?? '') !== $path || ! Storage::disk('local')->exists($path)) {
            throw new \InvalidArgumentException('The upload review expired. Upload the document again.');
        }
        $source['bytes'] = Storage::disk('local')->get($path);
        if (! hash_equals($source['sha256'], hash('sha256', $source['bytes']))) {
            throw new \InvalidArgumentException('The uploaded document could not be verified. Upload it again.');
        }
        unset($source['staged_path']);

        return $source;
    }

    public static function discard(array $source, string $token): void
    {
        if (isset($source['staged_path'], $source['admin_id'])) {
            $path = self::path((int) $source['admin_id'], $token);
            if ($source['staged_path'] === $path) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    public static function prune(): int
    {
        $removed = 0;
        foreach (Storage::disk('local')->files('recruitment-imports') as $path) {
            if (preg_match('~^recruitment-imports/[1-9][0-9]*-[a-f0-9]{64}\.bin$~D', $path)
                && Storage::disk('local')->lastModified($path) < time() - 1200) {
                $removed += (int) Storage::disk('local')->delete($path);
            }
        }

        return $removed;
    }
}
