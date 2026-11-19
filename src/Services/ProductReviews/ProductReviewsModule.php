<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Dashed\DashedCore\Retention\Termijn;
use Dashed\DashedCore\Retention\Retention;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Models\ProductQuestion;
use Dashed\DashedEcommerceCore\Policies\ProductReviewPolicy;
use Dashed\DashedEcommerceCore\Policies\ProductQuestionPolicy;
use Dashed\DashedEcommerceCore\Commands\SendProductReviewRequestsCommand;
use Dashed\DashedEcommerceCore\Livewire\Frontend\ProductReviews\WriteReview;
use Dashed\DashedEcommerceCore\Livewire\Frontend\ProductReviews\ProductReviews;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;
use Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductReviewSettingsPage;
use Dashed\DashedEcommerceCore\Livewire\Frontend\ProductReviews\ProductQuestions;
use Dashed\DashedEcommerceCore\Listeners\ProductReviews\ScheduleProductReviewRequestListener;

/**
 * Alle bedrading van productreviews en productvragen op één plek, aangeroepen
 * vanuit bootingPackage() van de ServiceProvider (nooit uit register(): de
 * labels zijn __()-aanroepen).
 */
class ProductReviewsModule
{
    public static function boot(): void
    {
        Gate::policy(ProductReview::class, ProductReviewPolicy::class);
        Gate::policy(ProductQuestion::class, ProductQuestionPolicy::class);

        cms()->registerRolePermissions('E-commerce', [
            'view_product_review' => 'Productreviews bekijken',
            'edit_product_review' => 'Productreviews modereren',
            'delete_product_review' => 'Productreviews verwijderen',
            'view_product_question' => 'Productvragen bekijken',
            'edit_product_question' => 'Productvragen beantwoorden',
            'delete_product_question' => 'Productvragen verwijderen',
        ]);

        cms()->registerSettingsPage(ProductReviewSettingsPage::class, 'Productreviews en -vragen', 'star', 'Eigen productreviews, het reviewverzoek na levering en vragen op de productpagina');

        Event::listen(OrderFulfillmentStatusChangedEvent::class, ScheduleProductReviewRequestListener::class);

        Livewire::component('product-reviews.write-review', WriteReview::class);
        Livewire::component('product-reviews.product-reviews', ProductReviews::class);
        Livewire::component('product-reviews.product-questions', ProductQuestions::class);

        if (class_exists(\Dashed\DashedCore\Classes\RateLimits::class) && method_exists(\Dashed\DashedCore\Classes\RateLimits::class, 'extend')) {
            \Dashed\DashedCore\Classes\RateLimits::extend(
                'dashed-product-questions',
                'rate_limit_product_questions',
                3,
                __('Productvragen stellen'),
                __('Per IP-adres (ruimer) en per e-mailadres, per minuut.'),
            );
        }

        // Het reviewformulier is voor één koper bedoeld.
        if (class_exists(\Dashed\DashedCore\Classes\Seo\RobotsDecision::class)) {
            cms()->builder('noindexPaths', ['product-review']);
        }

        app()->booted(function (): void {
            app(Schedule::class)
                ->command(SendProductReviewRequestsCommand::class)
                ->hourlyAt(25)
                ->withoutOverlapping();
        });

        self::registreerBewaartermijnen();
    }

    public static function registreerBewaartermijnen(): void
    {
        if (! class_exists(Retention::class)) {
            return;
        }

        cms()->registerRetention(
            Retention::make('product_reviews_rejected')
                ->label(__('Afgewezen productreviews'))
                ->pakket('dashed-ecommerce-core', __('Webshop'))
                ->tabel('dashed__product_reviews')
                ->termijn(
                    Termijn::make('product_reviews_rejected', 180, 'updated_at')
                        ->label(__('Afgewezen productreviews bewaren (dagen)'))
                        ->uitleg(__('Goedgekeurde en nog te beoordelen reviews blijven altijd staan. Standaard: 180 dagen na het afwijzen.'))
                        ->filter(fn ($query) => $query->where('status', ProductReview::STATUS_REJECTED))
                )
        );

        cms()->registerRetention(
            Retention::make('product_questions')
                ->label(__('Afgewezen en onbeantwoorde productvragen'))
                ->pakket('dashed-ecommerce-core', __('Webshop'))
                ->tabel('dashed__product_questions')
                ->termijn(
                    Termijn::make('product_questions_rejected', 180, 'updated_at')
                        ->label(__('Afgewezen productvragen bewaren (dagen)'))
                        ->uitleg(__('Standaard: 180 dagen na het afwijzen.'))
                        ->filter(fn ($query) => $query->where('status', ProductQuestion::STATUS_REJECTED))
                )
                ->termijn(
                    Termijn::make('product_questions_unanswered', 365, 'created_at')
                        ->label(__('Onbeantwoorde productvragen bewaren (dagen)'))
                        ->uitleg(__('Vragen die nooit een antwoord kregen. Standaard: 365 dagen na het stellen.'))
                        ->filter(fn ($query) => $query->whereNull('answered_at')->where('status', '!=', ProductQuestion::STATUS_APPROVED))
                )
        );
    }
}
