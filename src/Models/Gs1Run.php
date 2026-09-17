<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén verwerking van een mijnGS1-download: het origineel, de regels waar
 * iets mee moet, en het uploadbestand dat eruit komt.
 */
class Gs1Run extends Model
{
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_TOEGEWEZEN = 'toegewezen';
    public const STATUS_AFGESLOTEN = 'afgesloten';

    /** Map op de local-disk waar uploads en uploadbestanden staan. */
    public const DIRECTORY = 'gs1-runs';

    protected $table = 'dashed__gs1_runs';

    protected $fillable = [
        'site_id', 'user_id', 'file_path', 'contract_sheet', 'status', 'result_path', 'summary', 'reference_data',
    ];

    protected $casts = [
        'summary' => 'array',
        'reference_data' => 'array',
    ];

    protected static function booted(): void
    {
        // Via het model, niet via een kale databaseverwijdering: anders
        // blijven origineel en uploadbestand permanent op de schijf staan.
        static::deleting(function (Gs1Run $run) {
            // Alleen eigen bestanden: een pad dat ergens anders naar wijst
            // mag nooit tot een verwijdering leiden.
            $paths = array_filter(
                [$run->file_path, $run->result_path],
                fn ($path) => static::isOwnPath($path),
            );
            Storage::disk('local')->delete(array_values($paths));
            $run->lines()->delete();
        });
    }

    /**
     * Een relatief pad binnen de uploadmap, zonder omweg naar boven of een
     * absoluut begin. Zegt niets over of het bestand bestaat.
     */
    public static function isOwnPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        return str_starts_with($path, self::DIRECTORY . '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0");
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Gs1RunLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConcept(): bool
    {
        return $this->status === self::STATUS_CONCEPT;
    }

    public function isToegewezen(): bool
    {
        return $this->status === self::STATUS_TOEGEWEZEN;
    }
}
