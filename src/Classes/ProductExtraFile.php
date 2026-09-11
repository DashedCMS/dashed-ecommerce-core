<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Rules\SafeUploadedFile;

/**
 * Opslag van klant-uploads bij product-extra's van het type "file".
 *
 * Deze uploads komen ongeauthenticeerd vanaf de front-end en landen op de
 * media-disk, die in de projecten publiek staat. Daarom: een vaste allowlist
 * van extensies (afgeleid van de inhoud, niet van de client-naam), een
 * servergegenereerde bestandsnaam zonder de client-naam erin, het object
 * prive weggeschreven met een tijdelijke URL voor de beheerder, en bij een
 * al opgeslagen pad alleen paden binnen de eigen map, zodat een klant via
 * Livewire geen ander bestand (een factuur, bijvoorbeeld) aan zijn
 * bestelling kan hangen.
 */
class ProductExtraFile
{
    public const DIRECTORY = 'dashed/product-extras';

    public const DISK = 'dashed';

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    public const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * @return array{value: string, path: string}|null  null als het bestand of pad niet is toegestaan
     */
    public static function resolve(mixed $value): ?array
    {
        if (is_string($value)) {
            return static::resolveExistingPath($value);
        }

        if ($value instanceof UploadedFile) {
            return static::storeUpload($value);
        }

        return null;
    }

    /**
     * URL waarmee een beheerder het bestand kan openen: tijdelijk en
     * ondertekend op een S3-achtige disk, de gewone URL op een lokale disk.
     */
    public static function adminUrl(string $path, int $minutes = 30): string
    {
        $disk = Storage::disk(static::DISK);

        if ($disk->providesTemporaryUrls()) {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes));
        }

        return $disk->url($path);
    }

    protected static function resolveExistingPath(string $path): ?array
    {
        $path = ltrim($path, '/');

        if (str_contains($path, '..') || ! str_starts_with($path, static::DIRECTORY . '/')) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), static::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        if (! Storage::disk(static::DISK)->exists($path)) {
            return null;
        }

        return [
            'value' => basename($path),
            'path' => $path,
        ];
    }

    protected static function storeUpload(UploadedFile $file): ?array
    {
        if (! $file->isValid() || $file->getSize() > static::MAX_BYTES) {
            return null;
        }

        if (SafeUploadedFile::rejectionReason($file) !== null) {
            return null;
        }

        // De inhoud is leidend; de client-extensie telt alleen als finfo niets weet.
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $clientExtension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, static::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        // Een "afbeelding" met een andere client-extensie dan de allowlist gaat er ook uit.
        if ($clientExtension !== '' && ! in_array($clientExtension, static::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        // Prive op de media-disk: de bucket zelf staat op public, dus expliciet
        // private zetten; de beheerder krijgt een tijdelijke, ondertekende URL.
        $name = Str::uuid() . '.' . $extension;
        $path = $file->storeAs(static::DIRECTORY, $name, [
            'disk' => static::DISK,
            'visibility' => 'private',
        ]);

        if (! $path) {
            return null;
        }

        return [
            'value' => $name,
            'path' => $path,
        ];
    }
}
