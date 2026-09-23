<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedEcommerceCore\Models\Quote;

class QuoteAnsweredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Quote $quote)
    {
    }

    public function build()
    {
        $accepted = $this->quote->status === Quote::STATUS_ACCEPTED;

        $lines = [
            '<p>'.__('Offerte :nummer is :status.', [
                'nummer' => e($this->quote->displayNumber()),
                'status' => $accepted ? __('geaccepteerd') : __('afgewezen'),
            ]).'</p>',
            '<p>'.__('Klant').': '.e($this->quote->company_name ?: $this->quote->fullName() ?: $this->quote->email).'</p>',
        ];

        if ($accepted) {
            $lines[] = '<p>'.__('Akkoord gegeven door :naam op :datum vanaf :ip.', [
                'naam' => e((string) $this->quote->accepted_name),
                'datum' => $this->quote->accepted_at?->format('d-m-Y H:i'),
                'ip' => e((string) $this->quote->accepted_ip),
            ]).'</p>';
        } elseif ($this->quote->rejection_reason) {
            $lines[] = '<p>'.__('Reden').': '.e($this->quote->rejection_reason).'</p>';
        }

        return $this->subject(__('Offerte :nummer is :status', [
            'nummer' => $this->quote->displayNumber(),
            'status' => $accepted ? __('geaccepteerd') : __('afgewezen'),
        ]))->html(implode('', $lines));
    }
}
