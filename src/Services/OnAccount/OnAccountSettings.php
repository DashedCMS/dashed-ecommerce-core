<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;

/**
 * De enige plek die termijn, limiet, blokkadedrempel en herinneringstappen
 * leest. Een lege waarde bij de klant betekent: winkelstandaard.
 */
class OnAccountSettings
{
    public const DEFAULT_TERM_DAYS = 30;

    public const DEFAULT_BLOCK_AFTER_DAYS = 14;

    public static function termDaysFor(User $user): int
    {
        if ($user->payment_term_days !== null && $user->payment_term_days !== '') {
            return (int) $user->payment_term_days;
        }

        return (int) Customsetting::get('on_account_default_term_days', null, (string) self::DEFAULT_TERM_DAYS);
    }

    public static function creditLimitFor(User $user): ?float
    {
        if ($user->credit_limit !== null && $user->credit_limit !== '') {
            return (float) $user->credit_limit;
        }

        $default = Customsetting::get('on_account_default_credit_limit');

        return $default === null || $default === '' ? null : (float) $default;
    }

    public static function blockAfterDays(?string $siteId = null): int
    {
        return (int) Customsetting::get('on_account_block_after_days', $siteId, (string) self::DEFAULT_BLOCK_AFTER_DAYS);
    }

    /**
     * @return array<int, array{days: int, subject: string, body: string}> stap 1 is de eerste
     */
    public static function reminderStages(?string $siteId = null, ?string $locale = null): array
    {
        $stored = Customsetting::get('on_account_reminder_stages', $siteId, null, $locale);

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        $stages = is_array($stored) ? $stored : self::defaultStages();

        $stages = array_values(array_filter($stages, fn ($stage) => isset($stage['days']) && $stage['days'] !== ''));
        usort($stages, fn ($a, $b) => (int) $a['days'] <=> (int) $b['days']);

        $result = [];
        foreach ($stages as $i => $stage) {
            $result[$i + 1] = [
                'days' => (int) $stage['days'],
                'subject' => (string) ($stage['subject'] ?? ''),
                'body' => (string) ($stage['body'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{days: int, subject: string, body: string}>
     */
    public static function defaultStages(): array
    {
        return [
            [
                'days' => 3,
                'subject' => 'Herinnering: factuur :invoiceId:',
                'body' => '<p>Beste :customerFirstName:,</p><p>Wij hebben de betaling van factuur :invoiceId: nog niet ontvangen. Deze stond open tot :dueDate:. Het openstaande bedrag is :outstandingAmountFormatted:.</p>',
            ],
            [
                'days' => 14,
                'subject' => 'Tweede herinnering: factuur :invoiceId:',
                'body' => '<p>Beste :customerFirstName:,</p><p>Factuur :invoiceId: staat :daysOverdue: dagen over de vervaldatum. Wij verzoeken u :outstandingAmountFormatted: zo snel mogelijk te voldoen.</p>',
            ],
            [
                'days' => 30,
                'subject' => 'Laatste aanmaning: factuur :invoiceId:',
                'body' => '<p>Beste :customerFirstName:,</p><p>Ondanks eerdere herinneringen staat factuur :invoiceId: nog open voor :outstandingAmountFormatted:. Zolang deze niet betaald is, kunt u niet meer op rekening bestellen.</p>',
            ],
        ];
    }
}
