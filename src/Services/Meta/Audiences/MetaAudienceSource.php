<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Meta\Audiences;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\MetaAudience;
use Dashed\DashedEcommerceCore\Services\CustomerMatch\CustomerMatchHasher;

/**
 * Bepaalt wie er in een Meta-doelgroep hoort, als hashes.
 *
 * Alleen wie marketingtoestemming gaf komt erin: een actief contact op een
 * nieuwsbrieflijst van dezelfde site. Een geblokkeerd adres (bounce, klacht,
 * marktplaats) nooit, en een adres met een Bol-bestelling ook nooit, ook als
 * het op een of andere manier toch op een lijst staat. Zonder het
 * nieuwsbriefpakket is toestemming niet vast te stellen en blijft elke
 * doelgroep leeg.
 */
class MetaAudienceSource
{
    private const CHUNK = 1000;

    public function __construct(
        private readonly CustomerMatchHasher $hasher = new CustomerMatchHasher(),
    ) {
    }

    /**
     * @return array<string, string> e-mailhash => telefoonhash (leeg als onbekend)
     */
    public function members(MetaAudience $audience): array
    {
        if (! class_exists(\Dashed\DashedNewsletter\Models\NewsletterSubscriber::class)) {
            return [];
        }

        $emails = $this->candidateEmails($audience);
        $members = [];

        foreach (array_chunk($emails, self::CHUNK) as $chunk) {
            $chunk = $this->withConsent($audience->site_id, $chunk);
            $chunk = $this->withoutSuppressed($audience->site_id, $chunk);
            $chunk = $this->withoutMarketplace($chunk);

            $phones = $this->phonesFor($chunk);

            foreach ($chunk as $email) {
                $members[$this->hasher->hashEmail($email)] = isset($phones[$email])
                    ? hash('sha256', $phones[$email])
                    : '';
            }
        }

        return $members;
    }

    /** @return list<string> genormaliseerd, uniek, geldig */
    private function candidateEmails(MetaAudience $audience): array
    {
        $query = match ($audience->source_type) {
            MetaAudience::SOURCE_NEWSLETTER_LIST => \Dashed\DashedNewsletter\Models\NewsletterSubscriber::query()
                ->where('newsletter_list_id', $audience->source_id)
                ->where('status', \Dashed\DashedNewsletter\Models\NewsletterSubscriber::STATUS_ACTIVE)
                ->toBase(),
            MetaAudience::SOURCE_NEWSLETTER_SEGMENT => $this->segmentQuery($audience),
            MetaAudience::SOURCE_PRICE_GROUP => User::query()
                ->where('price_group_id', $audience->source_id)
                ->toBase(),
            default => null,
        };

        if ($query === null) {
            return [];
        }

        $emails = [];
        foreach ($query->select('email')->distinct()->pluck('email') as $email) {
            $email = mb_strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }

    private function segmentQuery(MetaAudience $audience): ?\Illuminate\Database\Query\Builder
    {
        $segment = \Dashed\DashedNewsletter\Models\NewsletterSegment::find($audience->source_id);
        if (! $segment) {
            throw new MetaAudienceException(__('Het segment van deze doelgroep bestaat niet meer.'));
        }

        try {
            $query = \Dashed\DashedNewsletter\Segments\SegmentQuery::for($segment);
        } catch (\Throwable $e) {
            // Een segment zonder voorwaarden is nooit "de hele lijst".
            throw new MetaAudienceException($e->getMessage());
        }

        // Zelf op status filteren: een segment zonder statusvoorwaarde levert
        // ook uitgeschreven contacten op.
        return $query
            ->where('status', \Dashed\DashedNewsletter\Models\NewsletterSubscriber::STATUS_ACTIVE)
            ->toBase();
    }

    /**
     * Alleen adressen die als actief contact op een lijst van deze site staan.
     *
     * @param list<string> $emails
     * @return list<string>
     */
    private function withConsent(string $siteId, array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $consented = DB::table('dashed__newsletter_subscribers as s')
            ->join('dashed__newsletter_lists as l', 'l.id', '=', 's.newsletter_list_id')
            ->where('l.site_id', $siteId)
            ->where('s.status', \Dashed\DashedNewsletter\Models\NewsletterSubscriber::STATUS_ACTIVE)
            ->whereIn('s.email', $emails)
            ->distinct()
            ->pluck('s.email')
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->flip();

        return array_values(array_filter($emails, fn (string $email) => isset($consented[$email])));
    }

    /**
     * @param list<string> $emails
     * @return list<string>
     */
    private function withoutSuppressed(string $siteId, array $emails): array
    {
        if ($emails === [] || ! class_exists(\Dashed\DashedNewsletter\Models\NewsletterSuppression::class)) {
            return $emails;
        }

        $blocked = \Dashed\DashedNewsletter\Models\NewsletterSuppression::query()
            ->where('site_id', $siteId)
            ->whereIn('email', $emails)
            ->pluck('email')
            ->flip();

        return array_values(array_filter($emails, fn (string $email) => ! isset($blocked[$email])));
    }

    /**
     * Een Bol-klant is klant van Bol en heeft ons geen toestemming gegeven.
     *
     * @param list<string> $emails
     * @return list<string>
     */
    private function withoutMarketplace(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $bol = Order::query()
            ->whereIn('email', $emails)
            ->where('order_origin', 'Bol')
            ->distinct()
            ->pluck('email')
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->flip();

        return array_values(array_filter($emails, fn (string $email) => ! isset($bol[$email])));
    }

    /**
     * Het telefoonnummer van de nieuwste eigen bestelling per adres, genormaliseerd
     * zoals Meta het wil: cijfers met landcode, zonder plus of voorloopnullen.
     *
     * @param list<string> $emails
     * @return array<string, string>
     */
    private function phonesFor(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $latestIds = Order::query()
            ->whereIn('email', $emails)
            ->where('order_origin', '!=', 'Bol')
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->groupBy('email')
            ->select(DB::raw('MAX(id) as id'));

        $phones = [];
        $rows = Order::query()
            ->whereIn('id', $latestIds)
            ->get(['id', 'email', 'phone_number', 'country', 'invoice_country']);

        foreach ($rows as $order) {
            $normalized = $this->hasher->normalizedPhone(
                $order->phone_number,
                $order->invoice_country ?: $order->country,
            );
            $digits = ltrim($normalized, '+');
            if ($digits !== '') {
                $phones[mb_strtolower(trim((string) $order->email))] = $digits;
            }
        }

        return $phones;
    }
}
