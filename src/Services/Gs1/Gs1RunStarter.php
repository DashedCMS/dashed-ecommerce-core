<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Gs1Run;

/**
 * Maakt van een geüpload bestand een run en analyseert die direct. Kan
 * het bestand niet gelezen worden, dan blijft er geen halve run achter.
 */
class Gs1RunStarter
{
    public function __construct(private readonly Gs1RunAnalyzer $analyzer)
    {
    }

    public function start(string $localPath, ?int $userId, string $siteId): Gs1Run
    {
        // Het pad komt uit de Livewire-staat en is dus door de client te
        // kiezen. Zonder deze controle leest een run een willekeurig bestand,
        // en verwijdert hij het bij een leesfout of bij het opruimen.
        if (! Gs1Run::isOwnPath($localPath) || ! Storage::disk('local')->fileExists($localPath)) {
            throw new Gs1InvalidUploadException(__('Het geüploade bestand is niet gevonden. Upload het opnieuw.'));
        }

        $run = Gs1Run::create([
            'site_id' => $siteId,
            'user_id' => $userId,
            'file_path' => $localPath,
            'status' => Gs1Run::STATUS_CONCEPT,
        ]);

        try {
            $this->analyzer->analyze($run);
        } catch (\Throwable $exception) {
            // Niet alleen RuntimeException: op PhpSpreadsheet 1.30 erft een
            // leesfout van \Exception.
            $run->delete();

            throw $exception;
        }

        return $run->refresh();
    }
}
