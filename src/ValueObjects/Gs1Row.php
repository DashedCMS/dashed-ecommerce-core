<?php

namespace Dashed\DashedEcommerceCore\ValueObjects;

/**
 * Een rij in een GS1 contract-Excel. De volgorde van velden komt
 * 1-op-1 overeen met de 13 kolommen die GS1 verplicht voor zowel
 * de download als de upload van het artikelbestand.
 */
class Gs1Row
{
    public function __construct(
        public ?string $gtin = null,
        public ?string $status = null,
        public ?string $classification = null,
        public ?string $consumerUnit = null,
        public ?string $packagingType = null,
        public ?string $country = null,
        public ?string $description = null,
        public ?string $language = null,
        public ?string $brand = null,
        public ?string $subBrand = null,
        public ?int $quantity = null,
        public ?string $unit = null,
        public ?string $imageUrl = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            gtin: self::clean($row[0] ?? null),
            status: self::clean($row[1] ?? null),
            classification: self::clean($row[2] ?? null),
            consumerUnit: self::clean($row[3] ?? null),
            packagingType: self::clean($row[4] ?? null),
            country: self::clean($row[5] ?? null),
            description: self::clean($row[6] ?? null),
            language: self::clean($row[7] ?? null),
            brand: self::clean($row[8] ?? null),
            subBrand: self::clean($row[9] ?? null),
            quantity: isset($row[10]) && $row[10] !== null && $row[10] !== '' ? (int) $row[10] : null,
            unit: self::clean($row[11] ?? null),
            imageUrl: self::clean($row[12] ?? null),
        );
    }

    public function toArray(): array
    {
        return [
            $this->gtin,
            $this->status,
            $this->classification,
            $this->consumerUnit,
            $this->packagingType,
            $this->country,
            $this->description,
            $this->language,
            $this->brand,
            $this->subBrand,
            $this->quantity,
            $this->unit,
            $this->imageUrl,
        ];
    }

    public function isInactive(): bool
    {
        return $this->status === 'Inactief';
    }

    public function isActive(): bool
    {
        return $this->status === 'Actief';
    }

    public function isPlaceholderGtin(): bool
    {
        if ($this->gtin === null) {
            return false;
        }

        return ctype_digit($this->gtin) && strlen($this->gtin) < 8;
    }

    public function hasRealGtin(): bool
    {
        if ($this->gtin === null) {
            return false;
        }

        return ctype_digit($this->gtin) && strlen($this->gtin) >= 8;
    }

    private static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
