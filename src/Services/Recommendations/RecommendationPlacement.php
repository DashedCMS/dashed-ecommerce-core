<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations;

/**
 * Where in the funnel a recommendation is being requested. Each placement
 * has its own strategy-stack weighting (see RecommendationService::PLACEMENT_DEFAULTS).
 *
 * The string values are the Customsetting key suffix - `'cart'` reads
 * `recommendation_stack_cart`. Keep them stable.
 */
enum RecommendationPlacement: string
{
    case ProductDetail = 'product_detail';
    case Cart = 'cart';
    case Checkout = 'checkout';
    case EmailOrderHandled = 'email_order_handled';
    case EmailAbandonedCart = 'email_abandoned_cart';
    case EmailPopupFollowUp = 'email_popup_follow_up';
    case Popup = 'popup';

    public function isEmail(): bool
    {
        return str_starts_with($this->value, 'email_');
    }

    /**
     * Default heading shown above the products grid for this placement.
     * Customers should immediately understand WHY these products are here.
     * Override per-call via RecommendationContextBuilder::withHeading().
     */
    public function heading(): string
    {
        return match ($this) {
            self::ProductDetail => 'Vaak samen gekocht',
            self::Cart => 'Anderen kochten ook',
            self::Checkout => 'Misschien vergeten?',
            self::EmailOrderHandled => 'Producten die je mogelijk leuk vindt',
            self::EmailAbandonedCart => 'Vergeet deze niet',
            self::EmailPopupFollowUp => 'Onze aanraders',
            self::Popup => 'Aanbevolen voor jou',
        };
    }
}
