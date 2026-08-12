<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

/**
 * Appends each file's modification time to its URL.
 *
 * nginx serves the static locations with `Cache-Control: public, immutable,
 * max-age=30d`, which tells the browser not even to revalidate. Filenames stay
 * the same across releases, so without a changing URL a returning visitor keeps
 * the previous release's CSS, script or image for a month — that is exactly how
 * the replaced product photos stayed invisible after their deploy.
 *
 * mtime is used rather than a version string in configuration because there is
 * nothing to remember to bump, and rather than the CI commit SHA because that
 * would need a new variable plumbed into compose.prod — an empty one has taken
 * this application down before. Deployment is `rsync -rlpt`, which preserves
 * timestamps, so only files whose content actually changed get a new URL.
 */
final class MtimeVersionStrategy implements VersionStrategyInterface
{
    /** @var array<string, string> memoised per request; a listing hits the same paths repeatedly */
    private array $versions = [];

    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    public function getVersion(string $path): string
    {
        if (!isset($this->versions[$path])) {
            $file = $this->publicDir.'/'.ltrim($path, '/');
            $mtime = is_file($file) ? filemtime($file) : false;
            $this->versions[$path] = false === $mtime ? '' : (string) $mtime;
        }

        return $this->versions[$path];
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);
        if ('' === $version) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'v='.$version;
    }
}
