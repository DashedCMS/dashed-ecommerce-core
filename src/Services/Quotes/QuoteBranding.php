<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedTranslations\Models\Translation;

/**
 * Logo, kleur en afzendergegevens van een offerte, gedeeld door de PDF en de
 * publieke pagina. Alles wordt gelezen op de site van de offerte en niet op
 * de actieve site: een PDF wordt ook vanuit een wachtrij of het CMS van een
 * andere site gerenderd.
 */
class QuoteBranding
{
    public function __construct(public readonly Quote $quote)
    {
    }

    public static function for(Quote $quote): self
    {
        return new self($quote);
    }

    public function setting(string $key): ?string
    {
        $value = Customsetting::get($key, $this->quote->site_id);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function companyName(): string
    {
        return $this->setting('site_name') ?? (string) config('app.name');
    }

    /** Dezelfde volgorde als de factuur: eigen factuurlogo, dan het sitelogo. */
    public function logoUrl(): ?string
    {
        $logo = Translation::get('invoice-logo', 'invoice', '', 'image')
            ?: $this->setting('site_logo')
            ?: $this->setting('logo');

        if (! $logo) {
            return null;
        }

        return mediaHelper()->getSingleMedia($logo, 'original')->url ?? null;
    }

    public function color(): string
    {
        $color = $this->setting('mail_primary_color')
            ?: Translation::get('primary-color-code', 'emails', '#A0131C');

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#A0131C';
    }

    /** @return array<int, string> adresregels van de afzender, zonder lege regels */
    public function senderLines(): array
    {
        return array_values(array_filter([
            trim(($this->setting('company_street') ?? '').' '.($this->setting('company_street_number') ?? '')),
            trim(($this->setting('company_postal_code') ?? '').' '.($this->setting('company_city') ?? '')),
            collect([$this->setting('site_from_email'), $this->setting('company_phone_number')])->filter()->implode('  |  '),
            $this->setting('company_kvk') ? Translation::get('kvk', 'quote', 'KvK').' '.$this->setting('company_kvk') : '',
            $this->setting('company_btw') ? Translation::get('tax-id', 'quote', 'BTW').' '.$this->setting('company_btw') : '',
        ]));
    }

    public function website(): ?string
    {
        $host = parse_url(Sites::url('', $this->quote->site_id), PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }

    /** Een regel voor de voet van de PDF: naam, adres, e-mail, website. */
    public function footerLine(): string
    {
        return collect([
            $this->companyName(),
            trim(($this->setting('company_street') ?? '').' '.($this->setting('company_street_number') ?? '').', '.($this->setting('company_postal_code') ?? '').' '.($this->setting('company_city') ?? ''), ' ,'),
            $this->setting('site_from_email'),
            $this->website(),
        ])->filter()->implode('  ·  ');
    }

    /**
     * Vrije tekst (intro, voorwaarden) als blokken: regels die met een
     * streepje, sterretje of bolletje beginnen worden een opsomming, de rest
     * alinea's. Een "Kop: tekst" aan het begin van een opsommingsregel wordt
     * vet, zoals in een getypte offerte.
     *
     * @return array<int, array{type: string, items?: array<int, array{label: ?string, text: string}>, text?: string}>
     */
    public static function blocks(?string $text): array
    {
        $blocks = [];

        foreach (preg_split('/\R/', (string) $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                $blocks[] = ['type' => 'break'];

                continue;
            }

            if (preg_match('/^[-*•]\s*(.+)$/u', $line, $m)) {
                $label = null;
                $body = $m[1];

                if (preg_match('/^([^:]{1,40}):\s+(.+)$/u', $body, $parts)) {
                    [$label, $body] = [$parts[1], $parts[2]];
                }

                $last = array_key_last($blocks);
                if ($last !== null && $blocks[$last]['type'] === 'list') {
                    $blocks[$last]['items'][] = ['label' => $label, 'text' => $body];
                } else {
                    $blocks[] = ['type' => 'list', 'items' => [['label' => $label, 'text' => $body]]];
                }

                continue;
            }

            $last = array_key_last($blocks);
            if ($last !== null && $blocks[$last]['type'] === 'paragraph') {
                $blocks[$last]['text'] .= "\n".$line;
            } else {
                $blocks[] = ['type' => 'paragraph', 'text' => $line];
            }
        }

        return array_values(array_filter($blocks, fn ($b) => $b['type'] !== 'break'));
    }
}
