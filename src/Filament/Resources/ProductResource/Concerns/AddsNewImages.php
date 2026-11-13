<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Concerns;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

/**
 * Het veld "Nieuwe afbeeldingen" op een product en een productgroep: de
 * geüploade bestanden gaan de mediabibliotheek in en het origineel op de
 * schijf wordt opgeruimd.
 */
trait AddsNewImages
{
    /**
     * @param  array<int, mixed>  $paths  de staat van het veld `new_images`
     * @return array<int, int> de id's van de items in de bibliotheek
     */
    protected function addNewImages(array $paths): array
    {
        $ids = [];

        foreach ($paths as $image) {
            // De paden komen uit de Livewire-staat en zijn dus door de
            // browser aan te passen. Wat niet van het uploadveld kan komen
            // wordt niet gelezen en ook niet verwijderd.
            if (! is_string($image) || ! static::isNewImageUpload($image)) {
                Log::warning('Nieuwe productafbeelding overgeslagen: het pad ligt buiten de uploadlocatie van het veld', [
                    'path' => is_scalar($image) ? (string) $image : get_debug_type($image),
                    'user' => auth()->id(),
                ]);

                continue;
            }

            try {
                if ($id = $this->storeNewImage($image)) {
                    $ids[] = (int) $id;
                }
            } catch (\Dashed\DashedFiles\Classes\Ingest\MediaRejectedException $exception) {
                $this->notifyNewImageFailed($exception->getMessage());
            } catch (Throwable $exception) {
                report($exception);
                $this->notifyNewImageFailed(__('Het bestand kon niet worden opgeslagen.'));
            } finally {
                // Ook na een fout: het bestand staat op de publieke schijf.
                rescue(fn () => Storage::disk('dashed')->delete($image));
            }
        }

        return $ids;
    }

    /**
     * Eigen map voor het veld `new_images`. Zonder map schreef het in de
     * wortel van de schijf, en was elk bestand daar een geldig pad voor wie
     * de Livewire-staat aanpast.
     */
    public const NEW_IMAGES_DIRECTORY = 'product-uploads';

    /**
     * Een geldige upload is precies één bestandsnaam binnen die map. Het pad
     * komt uit de Livewire-staat, dus al het andere wordt overgeslagen.
     */
    protected static function isNewImageUpload(string $path): bool
    {
        $prefix = self::NEW_IMAGES_DIRECTORY.'/';

        if (! str_starts_with($path, $prefix)) {
            return false;
        }

        $name = substr($path, strlen($prefix));

        return $name !== ''
            && ! str_starts_with($name, '.')
            && ! str_contains($name, '..')
            && preg_match('#[/\\\\\x00-\x1F]#', $name) !== 1;
    }

    private function storeNewImage(string $image): ?int
    {
        // Een oudere dashed-files kent de inname nog niet.
        if (! class_exists(\Dashed\DashedFiles\Classes\Ingest\IncomingMedia::class) || ! method_exists(\Dashed\DashedFiles\Classes\MediaLibrary::class, 'ingest')) {
            return mediaHelper()->uploadFromPath(Storage::disk('dashed')->url($image), 'producten', true);
        }

        // Het bestand staat al op de eigen schijf: rechtstreeks de inname
        // in, in plaats van het via zijn eigen url opnieuw te downloaden.
        $incoming = \Dashed\DashedFiles\Classes\Ingest\IncomingMedia::fromDisk('dashed', $image, 'upload', mediaHelper()->getFolderId('producten'))
            ->uploadedBy(is_numeric(auth()->id()) ? (int) auth()->id() : null);

        try {
            return app(\Dashed\DashedFiles\Classes\MediaLibrary::class)->ingest($incoming)->item->id;
        } finally {
            // De inname ruimt zijn tijdelijke kopie zelf op; dit is voor als hij daar niet aan toekwam.
            if (is_file($incoming->localPath)) {
                @unlink($incoming->localPath);
            }
        }
    }

    private function notifyNewImageFailed(string $reason): void
    {
        rescue(fn () => Notification::make()
            ->danger()
            ->title(__('Afbeelding niet toegevoegd'))
            ->body($reason)
            ->send());
    }
}
