<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedCore\Mail\MailBranding;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Satisfaction\Satisfaction;
use Dashed\DashedCore\Models\SatisfactionRating;

/**
 * De vraag na levering: een korte tekst en vijf klikbare scores, in de
 * mailopmaak van de site (dashed-core::emails.layout).
 */
class DeliverySatisfactionMail extends Mailable
{
    public function __construct(public Order $order, public SatisfactionRating $rating)
    {
    }

    public function envelope(): Envelope
    {
        $siteId = $this->order->site_id;
        $siteName = (string) (Customsetting::get('site_name', $siteId) ?: config('app.name'));

        return new Envelope(
            from: new Address(
                Customsetting::get('site_from_email', $siteId) ?: config('mail.from.address'),
                $siteName,
            ),
            subject: Satisfaction::text('delivery-subject', 'Hoe tevreden ben je over je bestelling bij :site:?', ['site' => $siteName]),
        );
    }

    public function content(): Content
    {
        $branding = MailBranding::for($this->order->site_id);

        $intro = view('dashed-ecommerce-core::emails.satisfaction-intro', [
            'title' => Satisfaction::text('delivery-title', 'Is alles goed aangekomen?'),
            'intro' => Satisfaction::text('delivery-intro', 'Hoi :name:, je bestelling :invoice: is een paar dagen geleden bezorgd. We horen graag hoe tevreden je bent. Het kost je één klik.', [
                'name' => trim((string) $this->order->first_name) ?: '',
                'invoice' => (string) $this->order->invoice_id,
            ]),
        ])->render();

        return new Content(
            view: 'dashed-core::emails.layout',
            with: $branding + [
                'blocks' => [
                    $intro,
                    Satisfaction::mailBlock($this->rating, Satisfaction::text('question-delivery', 'Hoe tevreden ben je over je bestelling?'), $branding['primaryColor']),
                ],
            ],
        );
    }
}
