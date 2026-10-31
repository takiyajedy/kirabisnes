<?php

namespace App\Services;

use App\Models\Setting;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The icons a phone puts on its home screen.
 *
 * The business's own logo when one has been uploaded, and the built-in mark
 * otherwise — the same rule the sidebar and the sign-in pages follow.
 *
 * The upload is never pointed at directly. A home screen icon has to be a
 * square PNG of a declared size, and a logo is none of those things: it may
 * be a wide wordmark, a JPEG, any dimensions at all. An icon that does not
 * match what the manifest promises costs the app its install prompt. So
 * square PNGs are drawn from the logo once and kept beside it, and if
 * anything about that fails the built-in mark is used instead — an icon is
 * never worth a broken page.
 */
final class AppIconService
{
    /** The sizes a browser asks for. */
    private const ANY_SIZES = [192, 512];

    private const MASKABLE_SIZE = 512;

    private const DIRECTORY = 'business/icons';

    /**
     * How much of the box is left empty around the logo.
     *
     * Android crops a maskable icon to its own shape, keeping only a circle
     * 80% of the icon wide. A square that fits inside that circle is
     * 80% / sqrt(2) ≈ 57% of the icon, so the margin has to be much wider
     * than it looks necessary. The ordinary icon only needs enough room to
     * stop the logo touching the edges.
     */
    private const ANY_PADDING = 0.08;

    private const MASKABLE_PADDING = 0.215;

    /**
     * The manifest's icon list, in the order a browser prefers.
     *
     * @return list<array<string, string>>
     */
    public function manifestIcons(): array
    {
        $urls = $this->fromLogo();

        if ($urls === null) {
            return [
                $this->entry('/icons/icon-192.png', 192, 'any'),
                $this->entry('/icons/icon-512.png', 512, 'any'),
                $this->entry('/icons/icon-512.png', 512, 'maskable'),
            ];
        }

        return [
            $this->entry($urls[192], 192, 'any'),
            $this->entry($urls[512], 512, 'any'),
            $this->entry($urls['maskable'], self::MASKABLE_SIZE, 'maskable'),
        ];
    }

    /**
     * The one icon iOS reads, which never looks at the manifest.
     */
    public function appleTouchIconUrl(): string
    {
        $urls = $this->fromLogo();

        return $urls === null ? '/apple-touch-icon.png' : $urls[192];
    }

    /**
     * Drop the generated icons, so the built-in mark comes back.
     *
     * Called when the logo is removed or replaced; a replacement is drawn
     * again on the next request that needs one.
     */
    public function clear(): void
    {
        Storage::disk('public')->deleteDirectory(self::DIRECTORY);
    }

    /**
     * @return array<string, string>
     */
    private function entry(string $src, int $size, string $purpose): array
    {
        return [
            'src' => $src,
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];
    }

    /**
     * The icons drawn from the logo, or null when there is no usable logo.
     *
     * @return array<array-key, string>|null
     */
    private function fromLogo(): ?array
    {
        try {
            return $this->resolve();
        } catch (Throwable) {
            // An icon is never worth a failed page.
            return null;
        }
    }

    /**
     * @return array<array-key, string>|null
     */
    private function resolve(): ?array
    {
        $logo = Setting::get('logo_path');
        $disk = Storage::disk('public');

        if (! is_string($logo) || $logo === '' || ! $disk->exists($logo)) {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        // The logo's own timestamp both decides whether the icons are still
        // the right ones and tells a browser that cached the old set to
        // fetch again.
        $stamp = $disk->lastModified($logo);

        if (! $this->isFresh($disk, $stamp)) {
            $this->draw($disk, (string) $disk->get($logo));
        }

        return [
            192 => $this->url($disk, 'icon-192.png', $stamp),
            512 => $this->url($disk, 'icon-512.png', $stamp),
            'maskable' => $this->url($disk, 'icon-maskable-512.png', $stamp),
        ];
    }

    private function url(Filesystem $disk, string $file, int $stamp): string
    {
        return $disk->url(self::DIRECTORY."/{$file}")."?v={$stamp}";
    }

    /**
     * Whether the icons on disk were drawn from the logo that is there now.
     */
    private function isFresh(Filesystem $disk, int $stamp): bool
    {
        foreach ($this->files() as $file) {
            $path = self::DIRECTORY."/{$file}";

            if (! $disk->exists($path) || $disk->lastModified($path) < $stamp) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        return ['icon-192.png', 'icon-512.png', 'icon-maskable-512.png'];
    }

    /**
     * Draw the whole set from the uploaded logo.
     */
    private function draw(Filesystem $disk, string $binary): void
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('The uploaded logo is not an image GD can read.');
        }

        try {
            foreach (self::ANY_SIZES as $size) {
                $disk->put(
                    self::DIRECTORY."/icon-{$size}.png",
                    $this->square($source, $size, self::ANY_PADDING),
                );
            }

            $disk->put(
                self::DIRECTORY.'/icon-maskable-512.png',
                $this->square($source, self::MASKABLE_SIZE, self::MASKABLE_PADDING),
            );
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * The logo centred in a square box, as PNG bytes.
     *
     * Scaled to fit rather than to fill: a wordmark cropped to a square
     * would lose half its words.
     *
     * @param  positive-int  $size
     */
    private function square(GdImage $source, int $size, float $padding): string
    {
        $canvas = imagecreatetruecolor($size, $size);

        if ($canvas === false) {
            throw new RuntimeException("Could not make a {$size}px canvas.");
        }

        try {
            // Opaque white rather than transparent: iOS paints a transparent
            // home screen icon onto black, and a logo drawn for paper would
            // disappear into it.
            imagefilledrectangle($canvas, 0, 0, $size, $size, (int) imagecolorallocate($canvas, 255, 255, 255));
            imagealphablending($canvas, true);
            imagesavealpha($canvas, false);

            $width = imagesx($source);
            $height = imagesy($source);
            $box = $size * (1 - 2 * $padding);
            $scale = $box / max($width, $height);

            $drawn = [
                'width' => max(1, (int) round($width * $scale)),
                'height' => max(1, (int) round($height * $scale)),
            ];

            imagecopyresampled(
                $canvas,
                $source,
                intdiv($size - $drawn['width'], 2),
                intdiv($size - $drawn['height'], 2),
                0,
                0,
                $drawn['width'],
                $drawn['height'],
                $width,
                $height,
            );

            ob_start();
            imagepng($canvas);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($canvas);
        }
    }
}
