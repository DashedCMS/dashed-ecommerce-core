<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewSettings;

/**
 * Productreviews en productvragen per site. Alles staat standaard uit.
 */
class ProductReviewSettingsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-star';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Productreviews en -vragen';
    protected static string | UnitEnum | null $navigationGroup = 'Systeem';
    protected static ?string $title = 'Productreviews en -vragen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $formData["product_reviews_enabled_{$id}"] = (bool) Customsetting::get('product_reviews_enabled', $id, '0');
            $formData["product_reviews_request_enabled_{$id}"] = (bool) Customsetting::get('product_reviews_request_enabled', $id, '0');
            $formData["product_reviews_request_delay_days_{$id}"] = ProductReviewSettings::delayDays($id);
            $formData["product_questions_enabled_{$id}"] = (bool) Customsetting::get('product_questions_enabled', $id, '0');
        }
        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $tabs[] = Tab::make($id)->label(ucfirst($site['name']))->schema([
                Toggle::make("product_reviews_enabled_{$id}")
                    ->label(__('Productreviews aan'))
                    ->helperText(__('Toont goedgekeurde reviews met gemiddelde en verdeling op de productpagina en zet aggregateRating in het productschema. Het thema moet het blok product-reviews.product-reviews tonen.'))
                    ->live(),
                Toggle::make("product_reviews_request_enabled_{$id}")
                    ->label(__('Na levering om een review vragen'))
                    ->helperText(__('Een mail met per gekocht product een link naar het reviewformulier, zodra de bestelling op Afgehandeld staat plus de wachttijd. Nooit naar Bol-klanten of naar wie zich voor de opvolgmails heeft afgemeld.'))
                    ->visible(fn (Get $get) => (bool) $get("product_reviews_enabled_{$id}"))
                    ->dehydratedWhenHidden(),
                TextInput::make("product_reviews_request_delay_days_{$id}")
                    ->label(__('Wachttijd na levering (dagen)'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(90)
                    ->required()
                    ->visible(fn (Get $get) => (bool) $get("product_reviews_enabled_{$id}"))
                    ->dehydratedWhenHidden(),
                Toggle::make("product_questions_enabled_{$id}")
                    ->label(__('Productvragen aan'))
                    ->helperText(__('Bezoekers kunnen op de productpagina een vraag stellen; beantwoorde en goedgekeurde vragen staan erbij. Het thema moet het blok product-reviews.product-questions tonen.')),
            ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            Customsetting::set('product_reviews_enabled', ! empty($state["product_reviews_enabled_{$id}"]) ? 1 : 0, $id);
            Customsetting::set('product_reviews_request_enabled', ! empty($state["product_reviews_request_enabled_{$id}"]) ? 1 : 0, $id);
            Customsetting::set('product_reviews_request_delay_days', (string) max(0, (int) ($state["product_reviews_request_delay_days_{$id}"] ?? ProductReviewSettings::DEFAULT_DELAY_DAYS)), $id);
            Customsetting::set('product_questions_enabled', ! empty($state["product_questions_enabled_{$id}"]) ? 1 : 0, $id);
        }
        Notification::make()->title(__('Instellingen opgeslagen'))->success()->send();
    }
}
