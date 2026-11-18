<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Address;

use ZipArchive;
use RuntimeException;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\Countries;

/**
 * Belgische straten en gemeentes voor het adres aanvullen, uit de gratis BeSt
 * Address open data van de federale overheid (BOSA): per gewest een CSV met
 * alle adressen.
 *
 * Een Belgische straat volgt niet uit postcode + huisnummer (onder één postcode
 * vallen veel straten). Daarom vult de checkout bij België de gemeente in op de
 * postcode, en stelt hij straten voor terwijl de klant typt.
 *
 * Aan per site met de instelling `checkout_belgian_addresses` (standaard uit).
 * Staat hij bij minstens één site aan, dan vult de planner de tabellen binnen
 * een dag en ververst hij ze daarna maandelijks (dashed:import-belgian-addresses).
 */
class BelgianAddresses
{
    public const SOURCES = [
        'https://opendata.bosa.be/download/best/openaddress-bevlg.zip',
        'https://opendata.bosa.be/download/best/openaddress-bebru.zip',
        'https://opendata.bosa.be/download/best/openaddress-bewal.zip',
    ];

    public const LANGUAGES = ['nl', 'fr', 'de'];

    public const SETTING = 'checkout_belgian_addresses';

    public const IMPORTED_AT_SETTING = 'belgian_addresses_imported_at';

    private const HAS_DATA_KEY = 'dashed:belgian-addresses:has-data';

    /** @var array<string, string> "postcode|language|street" => city */
    private array $streets = [];

    /** @var array<string, string> "postcode|language" => municipality */
    private array $postcodes = [];

    /** Aan voor de huidige site én er staat data klaar. */
    public static function active(?string $siteId = null): bool
    {
        return (bool) Customsetting::get(self::SETTING, $siteId) && app(self::class)->hasData();
    }

    public static function isBelgium(?string $country): bool
    {
        $country = trim((string) $country);
        if ($country === '') {
            return false;
        }

        if (strtoupper($country) === 'BE') {
            return true;
        }

        return rescue(fn () => Countries::getCountryIsoCode($country), null, false) === 'BE';
    }

    /** Minstens één site wil Belgische adressen, en de data ontbreekt of is ouder dan een maand. */
    public static function importDue(): bool
    {
        $enabled = collect(Sites::getSites())->contains(fn ($site) => (bool) Customsetting::get(self::SETTING, $site['id']));
        if (! $enabled) {
            return false;
        }

        $importedAt = Customsetting::get(self::IMPORTED_AT_SETTING);

        return ! app(self::class)->hasData() || blank($importedAt) || now()->subDays(30)->greaterThan($importedAt);
    }

    public function hasData(): bool
    {
        return (bool) Cache::remember(self::HAS_DATA_KEY, now()->addHour(), fn () => rescue(fn () => DB::table('dashed__belgian_postcodes')->exists(), false, false));
    }

    /** Gemeente bij een postcode, in de taal van de klant als die daar officieel is. */
    public function municipality(?string $postcode, ?string $language = null): ?string
    {
        $postcode = preg_replace('/\s+/', '', (string) $postcode);
        if (! preg_match('/^[1-9]\d{3}$/', $postcode)) {
            return null;
        }

        $rows = rescue(fn () => DB::table('dashed__belgian_postcodes')->where('postcode', $postcode)->pluck('municipality', 'language'), collect(), false);

        foreach ($this->preference($language) as $l) {
            if (filled($rows[$l] ?? null)) {
                return $rows[$l];
            }
        }

        return null;
    }

    /**
     * Straatsuggesties binnen een postcode: eerst namen die met de invoer beginnen,
     * dan namen die hem bevatten. Hoofdletter- en accentongevoelig (collation).
     *
     * @return list<array{street: string, city: string}>
     */
    public function streets(?string $postcode, ?string $search, ?string $language = null, int $limit = 8): array
    {
        $postcode = preg_replace('/\s+/', '', (string) $postcode);
        $search = trim((string) preg_replace('/\s+/', ' ', (string) $search));

        if (! preg_match('/^[1-9]\d{3}$/', $postcode) || mb_strlen($search) < 2) {
            return [];
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 80));

        return rescue(fn () => DB::table('dashed__belgian_streets')
            ->where('postcode', $postcode)
            ->where('street', 'like', "%{$like}%")
            ->orderByRaw('CASE WHEN street LIKE ? THEN 0 ELSE 1 END', ["{$like}%"])
            ->orderByRaw('FIELD(language, ?, ?, ?)', $this->preference($language))
            ->orderBy('street')
            ->limit($limit * 2)
            ->get(['street', 'city'])
            ->unique(fn ($row) => mb_strtolower($row->street))
            ->take($limit)
            ->map(fn ($row) => ['street' => (string) $row->street, 'city' => (string) $row->city])
            ->values()
            ->all(), [], false);
    }

    /**
     * Alle straten binnen een postcode (voor een datalist in de kassa).
     *
     * @return list<string>
     */
    public function streetNames(?string $postcode, ?string $language = null): array
    {
        $postcode = preg_replace('/\s+/', '', (string) $postcode);
        if (! preg_match('/^[1-9]\d{3}$/', $postcode)) {
            return [];
        }

        return rescue(fn () => DB::table('dashed__belgian_streets')
            ->where('postcode', $postcode)
            ->orderByRaw('FIELD(language, ?, ?, ?)', $this->preference($language))
            ->orderBy('street')
            ->limit(1500)
            ->pluck('street')
            ->unique()
            ->sort()
            ->values()
            ->all(), [], false);
    }

    /**
     * Downloadt de drie gewestbestanden en vervangt de tabellen in één keer.
     *
     * @param  list<string>|null  $files  lokale zip- of csv-bestanden in plaats van de downloads
     * @return array{streets: int, postcodes: int}
     */
    public function import(?array $files = null, ?callable $progress = null): array
    {
        $this->streets = [];
        $this->postcodes = [];
        $temporary = [];

        try {
            foreach ($files ?: self::SOURCES as $source) {
                if (str_starts_with($source, 'http')) {
                    $progress && $progress("Downloaden: {$source}");
                    $path = tempnam(sys_get_temp_dir(), 'dashed-belgian-addresses-');
                    $temporary[] = $path;
                    Http::timeout(900)->connectTimeout(30)->sink($path)->get($source)->throw();
                    $source = $path;
                }

                $progress && $progress('Inlezen: ' . basename($source));
                foreach ($this->rows($source) as $row) {
                    $this->add($row);
                }
            }
        } finally {
            foreach ($temporary as $path) {
                @unlink($path);
            }
        }

        $postcodeCount = count(array_unique(array_map(fn ($key) => strtok($key, '|'), array_keys($this->postcodes))));

        // Een kapotte of lege download mag nooit een werkende set wissen.
        if (! $files && $postcodeCount < 500) {
            throw new RuntimeException("Te weinig Belgische postcodes gevonden ({$postcodeCount}), tabellen niet vervangen.");
        }

        $this->write();
        Cache::forget(self::HAS_DATA_KEY);
        Customsetting::set(self::IMPORTED_AT_SETTING, now()->toDateTimeString());

        return ['streets' => count($this->streets), 'postcodes' => $postcodeCount];
    }

    /**
     * Eén rij uit de BeSt open-address-export (kolomnamen als sleutels).
     *
     * @param  array<string, string>  $row
     */
    public function add(array $row): void
    {
        if (($row['status'] ?? 'current') !== 'current') {
            return;
        }

        $postcode = trim($row['postcode'] ?? '');
        if (! preg_match('/^[1-9]\d{3}$/', $postcode)) {
            return;
        }

        $municipalities = [];
        foreach (self::LANGUAGES as $l) {
            $municipalities[$l] = trim($row["municipality_name_{$l}"] ?? '');
        }
        $firstMunicipality = collect($municipalities)->first(fn ($m) => $m !== '') ?? '';

        foreach (self::LANGUAGES as $l) {
            $street = trim($row["streetname_{$l}"] ?? '');
            if ($street === '') {
                continue;
            }

            $municipality = $municipalities[$l] !== '' ? $municipalities[$l] : $firstMunicipality;
            if ($municipality === '') {
                continue;
            }

            // Wallonië noemt de deelgemeente (Jambes, Wépion); Vlaanderen somt er soms
            // meerdere op ("Nossegem/ZAVENTEM") of zet de hoofdgemeente in hoofdletters
            // ("GENT"), dan is de gemeentenaam de veilige keus.
            $postname = trim($row["postname_{$l}"] ?? '');
            $usable = $postname !== '' && ! str_contains($postname, '/') && mb_strtoupper($postname) !== $postname;
            $city = $usable ? $postname : $municipality;

            $this->streets["{$postcode}|{$l}|" . mb_substr($street, 0, 150)] ??= mb_substr($city, 0, 100);
            $this->postcodes["{$postcode}|{$l}"] ??= mb_substr($municipality, 0, 100);
        }
    }

    /** @return iterable<array<string, string>> */
    private function rows(string $file): iterable
    {
        $stream = $this->open($file);

        try {
            $header = fgetcsv($stream, null, ',', '"', '');
            if (! $header) {
                return;
            }
            $header = array_map(fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header);
            $count = count($header);

            while (($fields = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if (count($fields) === $count) {
                    yield array_combine($header, $fields);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return resource */
    private function open(string $file)
    {
        if (! $this->isZip($file)) {
            return fopen($file, 'r') ?: throw new RuntimeException("Kan {$file} niet openen.");
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException("Kan zip {$file} niet openen.");
        }

        $name = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.csv')) {
                $name = $zip->getNameIndex($i);

                break;
            }
        }
        $zip->close();

        if (! $name) {
            throw new RuntimeException("Geen csv in {$file}.");
        }

        return fopen("zip://{$file}#{$name}", 'r') ?: throw new RuntimeException("Kan {$name} in {$file} niet lezen.");
    }

    private function isZip(string $file): bool
    {
        $handle = @fopen($file, 'r');
        $start = $handle ? fread($handle, 2) : '';
        $handle && fclose($handle);

        return $start === 'PK';
    }

    private function write(): void
    {
        DB::transaction(function () {
            DB::table('dashed__belgian_streets')->delete();
            DB::table('dashed__belgian_postcodes')->delete();

            // insertOrIgnore: de collation ziet "Rue des Bœufs" en "Rue des Boeufs" als dezelfde straat.
            foreach (array_chunk(array_keys($this->streets), 2000) as $keys) {
                DB::table('dashed__belgian_streets')->insertOrIgnore(array_map(function ($key) {
                    [$postcode, $language, $street] = explode('|', $key, 3);

                    return ['postcode' => $postcode, 'language' => $language, 'street' => $street, 'city' => $this->streets[$key]];
                }, $keys));
            }

            foreach (array_chunk(array_keys($this->postcodes), 2000) as $keys) {
                DB::table('dashed__belgian_postcodes')->insertOrIgnore(array_map(function ($key) {
                    [$postcode, $language] = explode('|', $key, 2);

                    return ['postcode' => $postcode, 'language' => $language, 'municipality' => $this->postcodes[$key]];
                }, $keys));
            }
        });
    }

    /** @return list<string> */
    private function preference(?string $language): array
    {
        $language = strtolower(substr((string) ($language ?: app()->getLocale()), 0, 2));

        return array_values(array_unique(array_merge(in_array($language, self::LANGUAGES, true) ? [$language] : [], self::LANGUAGES)));
    }
}
