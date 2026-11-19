<?php

namespace Dashed\DashedEcommerceCore\Support;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Referral;
use Dashed\DashedEcommerceCore\Policies\ReferralPolicy;
use Dashed\DashedEcommerceCore\Mail\ReferralRewardMail;
use Dashed\DashedEcommerceCore\Commands\ProcessReferralsCommand;
use Dashed\DashedEcommerceCore\Filament\Pages\CustomerProfilePage;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Mail\EmailBlocks\OrderReorderBlock;
use Dashed\DashedEcommerceCore\Mail\EmailBlocks\ReferralInviteBlock;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;
use Dashed\DashedEcommerceCore\Listeners\Referrals\RecordReferralListener;

/**
 * Alles rond klantenbinding op één plek: opnieuw bestellen, het
 * verwijsprogramma en het klantprofiel (Klant 360). Aangeroepen vanuit
 * bootingPackage(), nooit vanuit register(): de labels zijn __()-aanroepen.
 */
class CustomerLoyalty
{
    public static function boot(): void
    {
        Gate::policy(Referral::class, ReferralPolicy::class);

        cms()->registerRolePermissions('E-commerce', [
            'view_referral' => 'Verwijzingen bekijken',
            'edit_referral' => 'Verwijzingen belonen of afwijzen',
            'delete_referral' => 'Verwijzingen verwijderen',
        ]);

        Event::listen(OrderMarkedAsPaidEvent::class, RecordReferralListener::class);

        if (method_exists(cms(), 'emailBlock')) {
            cms()
                ->emailBlock('order-reorder', OrderReorderBlock::class)
                ->emailBlock('referral-invite', ReferralInviteBlock::class);
        }

        if (method_exists(cms(), 'registerMailable')) {
            cms()->registerMailable(ReferralRewardMail::class);
        }

        // Klantprofiel: de eigen secties van ec-core. Andere pakketten melden
        // zich zelf aan op dezelfde sleutel, zie CustomerProfile.
        cms()->builder('customerProfileSections', [
            'orders' => \Dashed\DashedEcommerceCore\CustomerProfile\Sections\OrdersSection::class,
            'on-account' => \Dashed\DashedEcommerceCore\CustomerProfile\Sections\OnAccountSection::class,
            'returns' => \Dashed\DashedEcommerceCore\CustomerProfile\Sections\ReturnsSection::class,
            'quotes' => \Dashed\DashedEcommerceCore\CustomerProfile\Sections\QuotesSection::class,
            'referrals' => \Dashed\DashedEcommerceCore\CustomerProfile\Sections\ReferralSection::class,
        ]);

        // Knop op de gebruikerslijst van dashed-core (die leest deze builder
        // vanaf core met de klantprofiel-haak; een oudere core negeert hem).
        cms()->builder('userResourceRecordActions', [
            'customer-profile' => fn () => Action::make('customerProfile')
                ->label(__('Klantprofiel'))
                ->icon('heroicon-o-identification')
                ->visible(fn () => CustomerProfilePage::canAccess())
                ->url(fn ($record) => CustomerProfilePage::urlFor($record->id)),
        ]);

        // De checkout weigert een verwijscode voor de verwijzer zelf en voor
        // wie al eerder bestelde, zie Checkout::submitCheckout().
        ecommerce()->builder('checkoutDiscountGuards', [
            'referral' => [ReferralProgram::class, 'checkoutRefusal'],
        ]);

        if (class_exists(\Dashed\DashedCore\Classes\Seo\RobotsDecision::class)) {
            cms()->builder('noindexPaths', ['reorder', 'verwijzing']);
        }

        // Drempel van de gratis-verzendbalk: per site gecachet, en leeg zodra
        // een verzendmethode of -zone wijzigt (anders tot een uur oud).
        foreach (['saved', 'deleted'] as $event) {
            \Dashed\DashedEcommerceCore\Models\ShippingMethod::{$event}(fn () => \Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper::flush());
            \Dashed\DashedEcommerceCore\Models\ShippingZone::{$event}(fn () => \Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper::flush());
        }

        app()->booted(function (): void {
            app(Schedule::class)->command(ProcessReferralsCommand::class)
                ->dailyAt('06:20')
                ->name('dashed:process-referrals')
                ->withoutOverlapping();
        });
    }
}
