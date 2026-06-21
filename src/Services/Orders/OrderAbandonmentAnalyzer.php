<?php

namespace Dashed\DashedEcommerceCore\Services\Orders;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

/**
 * Bepaalt regelgebaseerd (deterministisch) de waarschijnlijke oorzaak dat een
 * bestelling niet (volledig) is afgerekend. Leest uitsluitend bestaande data
 * (status, betalingen, order-logs). Geeft null terug als er niets aan de hand is.
 */
class OrderAbandonmentAnalyzer
{
    public function analyze(Order $order): ?OrderAbandonmentDiagnosis
    {
        // Volledig betaald: niets aan de hand.
        if ($order->status === 'paid') {
            return null;
        }

        // Retouren vallen buiten "niet afrekenen".
        if ($order->status === 'return') {
            return null;
        }

        if ($order->status === 'cancelled') {
            return new OrderAbandonmentDiagnosis(
                'cancelled',
                'Bestelling geannuleerd',
                'high',
                $this->withRecoveryContext($order, [$this->cancelledEvidence($order)]),
            );
        }

        if ($order->status === Order::STATUS_CONCEPT) {
            return new OrderAbandonmentDiagnosis(
                'concept_never_submitted',
                'Concept — nooit afgerond',
                'medium',
                $this->withRecoveryContext($order, ['De bestelling bleef een concept en is nooit ingediend.']),
            );
        }

        if ($order->status === 'partially_paid') {
            return new OrderAbandonmentDiagnosis(
                'partial_payment',
                'Gedeeltelijk betaald',
                'high',
                $this->withRecoveryContext($order, [
                    'Betaald: ' . CurrencyHelper::formatPrice($order->paidAmount)
                        . ' van ' . CurrencyHelper::formatPrice($order->total)
                        . ' (openstaand: ' . CurrencyHelper::formatPrice($order->openAmount) . ').',
                ]),
            );
        }

        if ($order->status === 'waiting_for_confirmation') {
            return new OrderAbandonmentDiagnosis(
                'awaiting_manual_payment',
                'Wacht op handmatige betaling',
                'high',
                $this->withRecoveryContext($order, [
                    'Betaalmethode vereist handmatige bevestiging (bijv. overboeking) die nog niet binnen is.',
                ]),
            );
        }

        // Vanaf hier: m.n. 'pending' (en eventuele overige niet-betaalde statussen).

        $failedLog = $order->logs()
            ->where('tag', 'order.payment-start.failed')
            ->latest()
            ->first();

        if ($failedLog) {
            return new OrderAbandonmentDiagnosis(
                'payment_start_failed',
                'Starten van de betaling mislukt',
                'high',
                $this->withRecoveryContext($order, array_filter([
                    'De betaalprovider gaf een fout terug bij het starten van de betaling.',
                    $failedLog->note ? 'Foutmelding: ' . $failedLog->note : null,
                ])),
            );
        }

        $payments = $order->orderPayments;

        $startedAtPsp = $payments->first(fn ($payment) => ! empty($payment->psp_id) && $payment->status !== 'paid');

        if ($startedAtPsp) {
            return new OrderAbandonmentDiagnosis(
                'abandoned_at_psp',
                'Afgehaakt op de betaalpagina',
                'medium',
                $this->withRecoveryContext($order, [
                    'De betaling is wel gestart bij de provider (' . ($startedAtPsp->psp ?: 'onbekend')
                        . '), maar de klant is niet teruggekeerd om af te ronden.',
                ]),
            );
        }

        if ($payments->isEmpty()) {
            return new OrderAbandonmentDiagnosis(
                'no_payment_attempt',
                'Geen betaalpoging gestart',
                'medium',
                $this->withRecoveryContext($order, [
                    'Er is een bestelling aangemaakt, maar er is geen enkele betaling gestart.',
                ]),
            );
        }

        return new OrderAbandonmentDiagnosis(
            'unknown_unpaid',
            'Onbetaald — oorzaak onbekend',
            'low',
            $this->withRecoveryContext($order, ['De bestelling is niet betaald; er is geen eenduidig signaal voor de oorzaak.']),
        );
    }

    /**
     * @param  array<int, string>  $evidence
     * @return array<int, string>
     */
    private function withRecoveryContext(Order $order, array $evidence): array
    {
        if ($order->abandoned_cart_recovery) {
            $evidence[] = 'Deze bestelling komt uit een verlaten-winkelwagen-herstel.';
        }

        return array_values($evidence);
    }

    private function cancelledEvidence(Order $order): string
    {
        $log = $order->logs()
            ->where('tag', 'order.cancelled')
            ->latest()
            ->first();

        if ($log) {
            return 'Geannuleerd op ' . $log->created_at->format('d-m-Y H:i')
                . ($log->note ? ' — ' . $log->note : '') . '.';
        }

        return 'De bestelling is geannuleerd.';
    }
}
