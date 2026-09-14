<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Controllers\Frontend\WishlistController;

/**
 * "Je verlanglijst is bewaard": de bevestiging na het invullen van een
 * e-mailadres op de lijstpagina, met de link die de lijst op elk apparaat
 * terughaalt. Een themaview in de app gaat voor de pakketview.
 */
class WishlistSavedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Wishlist $wishlist)
    {
    }

    public function build(): static
    {
        $siteName = Customsetting::get('site_name', Sites::getActive(), config('app.name'));
        $theme = config('dashed-core.site_theme', 'dashed') . '.emails.wishlist-saved';

        return $this
            ->from(Customsetting::get('site_from_email', Sites::getActive()) ?: config('mail.from.address'), $siteName)
            ->subject(__('Je verlanglijst bij :site', ['site' => $siteName]))
            ->view(view()->exists($theme) ? $theme : 'dashed-ecommerce-core::emails.wishlist-saved')
            ->with([
                'siteName' => $siteName,
                'items' => $this->wishlist->publicItems(),
                'restoreUrl' => WishlistController::restoreUrl($this->wishlist),
                'primaryColor' => Customsetting::get('mail_primary_color', Sites::getActive(), '#111827'),
            ]);
    }
}
