<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

/**
 * Evalueert de voorwaarden van een automatiseringsregel tegen een kant-en-
 * klare waardecontext (zie AutomationContext::forOrder()). Bewust puur: geen
 * DB-calls, geen facades, geen kennis van Order — enkel arrays in, boolean
 * uit. Dat maakt hem exhaustief testbaar.
 *
 * Fail-safe by design: een automatiseringsregel triggert reële acties
 * (labels, prints, mails), dus een onduidelijke situatie mag nooit alsnog
 * vuren. Een onbekend veld, een onbekende operator of een malformed
 * voorwaarde levert daarom altijd "geen match" op — nooit een exception en
 * nooit "absent-therefore-true".
 */
class ConditionEvaluator
{
    /**
     * Meerdere voorwaarden zijn een EN: zodra er één niet matcht, matcht de
     * hele set niet. Een lege lijst matcht altijd (geen voorwaarden = altijd
     * vuren).
     *
     * @param  array<int, array{field?: mixed, operator?: mixed, value?: mixed}>  $conditions
     * @param  array<string, mixed>  $context
     */
    public static function matches(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (! self::matchesCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{field?: mixed, operator?: mixed, value?: mixed}  $condition
     * @param  array<string, mixed>  $context
     */
    private static function matchesCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $expected = $condition['value'] ?? null;

        if (! is_string($field) || $field === '' || ! is_string($operator)) {
            return false;
        }

        // Een veld dat niet in de context zit is onbekend (bv. een verwijderd
        // trigger-veld of een typefout in een oude regel) — de regel vuurt
        // dan niet, in plaats van te crashen of "afwezig = waar" aan te nemen.
        if (! array_key_exists($field, $context)) {
            return false;
        }

        $actual = $context[$field];

        return match ($operator) {
            'eq' => $actual == $expected,
            'neq' => $actual != $expected,
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'is_true' => $actual === true,
            'is_false' => $actual === false,
            default => false,
        };
    }
}
